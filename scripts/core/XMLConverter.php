<?php
/*! XMLConverter.php
 *
 * Conversion between XML and PHP array.
 *
 * Inspired by XML2Array and Array2XML by lalit.lab.
 * http://www.lalit.org
 *
 * @author Vicary Archangel
 */

namespace core;

class XMLConverter {

  /**
   * Parses source XML into a PHP array.
   *
   * @param $source This can be raw XML source, or path to the XML.
   */
  public static function fromXML($source) {
    $reader = new \XMLReader();
    $method = 'open';

    if (preg_match('/^\s*\</', $source)) {
      $method = 'xml';
    }

    $method = new \ReflectionMethod($reader, $method);
    $method = $method->invoke($reader, $source);

    if ($method === FALSE) {
      return FALSE;
    }

    $nodeTree = array();

    // Start reading the first node
    self::walkXML($reader, $nodeTree);

    $reader->close();

    return $nodeTree;
  }

  private static function walkXML($reader, &$node) {
    // Failsafe
    if ($node === NULL) {
      return;
    }

    $nodeName = $reader->prefix ?
      "$reader->prefix:$reader->name" : $reader->name;

    if (!isset($node['@value'])) {
      $node['@value'] = array();
    }

    $_value = &$node['@value'];

    switch ($reader->nodeType) {
      case \XMLReader::ELEMENT:                // #1
        $currentNode = array();

        if (!isset($_value[$nodeName])) {
          $_value[$nodeName] = array();
        }

        $_value[$nodeName][] = &$currentNode;

        // Attributes
        if ($reader->hasAttributes) {
          $attr = array();

          while ($reader->moveToNextAttribute()) {
            $attrName = $reader->prefix ?
              "$reader->prefix:$reader->name" : $reader->name;

            $attr[$attrName] = $reader->value;
          }

          $currentNode['@attributes'] = &$attr;
        }

        $isEmpty = $reader->isEmptyElement;

        $reader->read();

        if (!$isEmpty) {
          unset($isEmpty);

          if (self::walkXML($reader, $currentNode) !== TRUE) {
            // Root cleanup
            if (count($node) == 1) {
              unset($node['@value']);
              $node[$nodeName] = $_value[$nodeName][0];
            }

            return;
          }
        }
        else {
          $currentNode = '';
        }

        break;

      case \XMLReader::TEXT:                   // #3
      case \XMLReader::CDATA:                  // #4
        if ($reader->value) {
          $value = $reader->value;

          if (is_numeric($value) &&
              $value < PHP_INT_MAX &&
              strlen($value) < 15) {
            $value = floatval($value);
          }
          elseif ($value === 'true') {
            $value = TRUE;
          }
          elseif ($value === 'false') {
            $value = FALSE;
          }

          $_value[] = $value;
        }

        $reader->read();
        break;

      case \XMLReader::COMMENT:
        if ($reader->value) {
          if (!isset($node['@comments'])) {
            $node['@comments'] = array();
          }

          $node['@comments'][] = $reader->value;
        }

        $reader->read();
        break;

      case \XMLReader::END_ELEMENT:            // #15
        if (is_array($_value)) {
          // Flatten numeric arrays with one element.
          $_value = Utility::unwrapAssoc($_value);

          // Empty string as value (last resort), when element has no value (self-closing).
          if (!$_value) {
            $_value = '';
          }

          // Flatten name-value pairs with their content as single element array.
          if (is_array($_value)) {
            $_value = array_map(array('core\\Utility', 'unwrapAssoc'), $_value);
          }
        }

        // Directly use the contents when there is no attributes in the XML.
        /* Note by Eric @ 8 Jan, 2013
           Property @value is set by default, so there must be @value when count($node) == 1.
        */
        // "count($node) == 1" allows more than attributes in metadata in future.
        if (count($node) == 1) {
          unset($node['@value']);

          $node = $_value;
        }
        else {
          foreach ($node as $key => &$value) {
            if (is_array($value)) {
              switch (count($value)) {
                case 0:
                  unset($node[$key], $value);
                  break;
                case 1:
                  $value = Utility::unwrapAssoc($value);
                  break;
              }
            }
          }
        }

        return $reader->read();
/* Unused
      case XMLReader::NONE:                   // #0
      case XMLReader::ATTRIBUTE:              // #2
      case XMLReader::PI:                     // #7
      case XMLReader::DOC:                    // #9
      case XMLReader::DOC_TYPE:               // #10
      case XMLReader::DOC_FRAGMENT:           // #11
      case XMLReader::WHITESPACE:             // #13
      case XMLReader::SIGNIFICANT_WHITESPACE: // #14
      case XMLReader::XML_DECLARATION:        // #17

      case XMLReader::ENTITY_REF:             // #5
      case XMLReader::ENTITY:                 // #6
      case XMLReader::NOTATION:               // #12
      case XMLReader::END_ENTITY:             // #16
*/
      default:
        $reader->read();
        break;
    }

    // Chain return value upwards.
    return self::walkXML($reader, $node);
  }

  public static function toXML($source) {
    $writer = new \XMLWriter();
    $writer->openMemory();

    $writer->startDocument('1.0', 'utf-8');

    self::walkArray($source, $writer);

    $writer->endDocument();

    return $writer->outputMemory(true);
  }

  static $i = 0;

  private static function walkArray($node, &$writer, $ns = NULL, $currentNode = NULL) {
    $ns = (array) $ns;

    // Text value
    if (!is_array($node)) {
      $writer->text($node);
      return;
    }

    $nodeStarted = FALSE;

    if ($currentNode !== NULL) {

      //------------------------------
      //  startElement()
      //------------------------------
      // 1. Metadata exists
      // 2. Not numeric array
      if (Utility::isAssoc($node)) {
        self::startElement($currentNode, $writer, $ns);

        //------------------------------
        //  Metadata
        //-----------------------------
        // @attributes
        if (isset($node['@attributes'])) {
          foreach ($node['@attributes'] as $key => $value) {
            if (preg_match('/^(xmlns\:?)(.*)$/', $key, $matches)) {
              $ns[$matches[2]] = $value;
            }

            $key = explode(':', $key);

            if (count($key) == 2) {
              $writer->writeAttributeNS($key[0], $key[1], $ns[$key[0]], $value);
            }
            else {
              $writer->writeAttribute($key[0], $value);
            }
          }
          unset($node['@attributes']);
        }

        // @comments
        if (isset($node['@comments'])) {
          foreach ((array) $node['@comments'] as $value) {
            $writer->writeComment($value);
          }
          unset($node['@comments']);
        }

        $nodeStarted = TRUE;
      }

    }

    if (isset($node['@value'])) {
      $node = (array) $node['@value'];
    }

    //------------------------------
    //  Children
    //------------------------------

    if (Utility::isAssoc($node)) {;
      foreach ($node as $key => $value) {
        if (!is_array($value)) {
          self::writeElement($key, $writer, $ns, $value);
        }
        else {
          self::walkArray($value, $writer, $ns, $key);
        }
      }
    }
    else {
      foreach ($node as $value) {
        if (!$nodeStarted && !is_array($value)) {
          self::writeElement($currentNode, $writer, $ns, $value);
        }
        else {
          self::walkArray($value, $writer, $ns, $currentNode);
        }
      }
    }

    if ($nodeStarted) {
      $writer->endElement();
    }
  }

  private static function startElement($nodeName, $writer, &$ns) {
    $nodeName = explode(':', $nodeName);

    if (count($nodeName) == 2) {
      $writer->startElementNS($nodeName[0], $nodeName[1], $ns[$nodeName[0]]);
    }
    else {
      $writer->startElement($nodeName[0]);
    }
  }

  private static function writeElement($nodeName, $writer, &$ns, $value = NULL) {
    $nodeName = explode(':', $nodeName);

    // scalar to string conversion
    if (is_bool($value)) {
      $value = $value ? 'true' : 'false';
    }
    else {
      $value = (string) $value;
    }

    if (count($nodeName) == 2) {
      $writer->writeElementNS($nodeName[0], $nodeName[1], $ns[$nodeName[0]], $value);
    }
    else {
      $writer->writeElement($nodeName[0], $value);
    }
  }
}