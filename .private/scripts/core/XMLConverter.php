<?php
/* XMLConverter.php | Converts between XML and PHP arrays. */

namespace core;

use XMLReader;
use XMLWriter;

/**
 * XMLConverter class.
 *
 * Conversion between XML and PHP array.
 *
 * Inspired by XML2Array and Array2XML by lalit.lab.
 * http://www.lalit.org
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
class XMLConverter {

  /**
   * @private
   *
   * String values exceed this length will be written as CDATA tag.
   */
  const CDATA_THRESHOLD = 70;

  /**
   * Parses source XML into a PHP array.
   *
   * @param $source This can be raw XML source, or path to the XML.
   */
  public static function fromXML($source) {
    $reader = new XMLReader();
    $method = preg_match('/^\s*\</', $source) ? 'xml' : 'open';

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
      case XMLReader::ELEMENT:                // #1
        $currentNode = array();

        if (!isset($_value[$nodeName])) {
          $_value[$nodeName] = array();
        }

        $_value[$nodeName][] = &$currentNode;

        $isEmpty = $reader->isEmptyElement;

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

        $reader->read();

        if (!$isEmpty) {
          unset($isEmpty);

          if (self::walkXML($reader, $currentNode) !== TRUE) {
            // Root cleanup
            unset($node['@value']);
            $node[$nodeName] = $_value[$nodeName][0];
            return;
          }
        }
        else if (!@$currentNode['@attributes']) {
          $currentNode = '';
        }

        break;

      case XMLReader::TEXT:                   // #3
      case XMLReader::CDATA:                  // #4
        $value = $reader->value;

        if ( is_numeric($value) && $value < PHP_INT_MAX && strlen($value) < 15 ) {
          // Only converts when exact match on numeric value and string value,
          // this prevents values with preceding zeros like 001.
          if ( "$value" === (string) doubleval($value) ) {
            $value = doubleval($value);
          }
        }
        elseif (strcasecmp($value, 'true') === 0) {
          $value = TRUE;
        }
        elseif (strcasecmp($value, 'false') === 0) {
          $value = FALSE;
        }

        $_value[] = $value;

        $reader->read();
        break;

      case XMLReader::COMMENT:
        if ($reader->value) {
          if (!isset($node['@comments'])) {
            $node['@comments'] = array();
          }

          $node['@comments'][] = $reader->value;
        }

        $reader->read();
        break;

      case XMLReader::END_ELEMENT:            // #15
        if (is_array($_value)) {
          // Flatten numeric arrays with one element.
          if (!Utility::isAssoc($_value)) {

            switch (count($_value)) {
              case 0:
                $_value = '';
                break;
              case 1:
                $_value = $_value[0];
                break;
            }
          }

          // Flatten name-value pairs with their content as single element array.
          if (is_array($_value)) {
            foreach ($_value as $key => &$value) {
              if (is_array($value) && count($value) == 1) {
                $value = $value[0];
              }
            }
          }
        }

        if (count($node) === 1) {
          unset($node['@value']);

          $node = $_value;
        }
        else {
          foreach ($node as $key => &$value) {
            if (count($value) === 0) {
              unset($value);
            }
            elseif (is_array($value) &&
                count($value) === 1 &&
                isset($value[0])) {
              $value = $value[0];
            }

            continue;
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
    $writer = new XMLWriter();

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
      if (strlen($node) > self::CDATA_THRESHOLD) {
        $writer->writeCData($node);
      }
      else {
        $writer->text($node);
      }
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

    if (Utility::isAssoc($node)) {
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

    /*! Note
     *  As XMLWriter does not do smart self closing tags depends on the value
     *  itself, we need to do this threshold check twice. One here, and one
     *  at the start of walkArray().
     */
    if (strlen($value) > self::CDATA_THRESHOLD) {
      if (count($nodeName) == 2) {
        $writer->startElementNS($nodeName[0], $nodeName[1], $ns[$nodeName[0]]);
      }
      else {
        $writer->startElement($nodeName[0]);
      }

      // Single point of logic for text writing
      self::walkArray($value, $writer, $ns);

      $writer->endElement();
    }
    else {
      // null for self closing tag
      if (!$value) {
        $value = null;
      }

      if (count($nodeName) == 2) {
        $writer->writeElementNS($nodeName[0], $nodeName[1], $ns[$nodeName[0]], $value);
      }
      else {
        $writer->writeElement($nodeName[0], $value);
      }
    }
  }
}
