<?php
/*! System.php | Operating system related functions. */

namespace framework;

use framework\Configuration as conf;

use framework\exceptions\FrameworkException;

class System {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

  const ENV_DEVELOPMENT = 'development';
  const ENV_TESTING = 'testing';
  const ENV_QUALITY_ASSURANCE = 'quality_assurance';
  const ENV_STAGING = 'staging';
  const ENV_PRODUCTION = 'production';
  const ENV_DEBUG = 'debug';

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * @protected
   *
   * Paths used when autoloading.
   *
   * Intialized with default lookup path.
   */
  protected static $pathPrefixes = array( '*' => '.private/scripts' );

  /**
   * Accessor to the current environment, defaults to debug.
   *
   * @param {?string} $value The new environment value.
   * @return {?string} When $value is omitted, it returns the current value.
   */
  public static function environment($validate = true) {
    static $isValid = null;

    $value = conf::get('system::environment');

    if ( $validate ) {
      if ( $isValid === null ) {
        $isValid = in_array($value, array(
          self::ENV_DEVELOPMENT,
          self::ENV_TESTING,
          self::ENV_QUALITY_ASSURANCE,
          self::ENV_STAGING,
          self::ENV_PRODUCTION,
          self::ENV_DEBUG,
          ));
      }

      if ( !$isValid ) {
        throw new FrameworkException('Invalid deployment stage "' . $value .
          '", please revise system configuration.');
      }
    }

    return $value;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Returns a designated type of hostname from configuration.
   */
  public static function getHostname($type = 'default') {
    static $domains = array();

    $domain = &$domains[$type];
    if ( !$domain ) {
      switch ( strtolower($type) ) {
        case 'secure':
          $domain = conf::get('system::domains.secure');
          break;

        case 'service':
          $domain = conf::get('system::domains.service');
          break;

        case 'local':
          $domain = conf::get('system::domains.local', 'localhost');
          break;

        default:
          $domain = conf::get("system::domains.$type", gethostname());
          break;
      }
    }

    return $domain;
  }

  /**
   * Get the system root path.
   *
   * Approach 1: Based on relative directory of this script file.
   * Approach 2: The last element of debug_backtrace() assumes "gateway.php",
   *             may consume more memories so cache it with static.
   *
   * @param {string} $type Configuration path to the base path relative to system root.
   */
  public static function getPathname($type = '') {
    static $root;
    if ( !$root ) {
      // Approach 1
      $root = realpath(__DIR__ . '/../../..');

      // Approach 2
      // $root = debug_backtrace();
      // $root = array_pop($root);
      // $root = dirname($root['file']);
    }

    if ( $type ) {
      $type = (string) conf::get("system.paths::$type");
      if ( $type ) {
        return "$root/" . str_replace(DIRECTORY_SEPARATOR, '/', $type);
      }
    }

    return $root;

    // $path = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..']);
    // return realpath($path);
  }

  /**
   * Inspired by os.networkInterfaces() from node.js.
   *
   * @return {array} Returns a brief info of available network interfaces.
   */
  public static function networkInterfaces() {
    switch ( strtoupper(PHP_OS) ) {
      case 'DARWIN': // MAC OS X
        $res = preg_split('/\n/', @`ifconfig`);
        $res = array_filter(array_map('trim', $res));

        $result = array();

        foreach ( $res as $row ) {
          if ( preg_match('/^(\w+\d+)\:\s+(.+)/', $row, $matches) ) {
            $result['__currentInterface'] = $matches[1];

            $result[$result['__currentInterface']]['__internal'] = false !== strpos($matches[2], 'LOOPBACK');
          }
          else if ( preg_match('/^inet(6)?\s+([^\/\s]+)(?:%.+)?/', $row, $matches) ) {
            $iface = &$result[$result['__currentInterface']];

            @$iface[] = array(
                'address' => $matches[2]
              , 'family' => $matches[1] ? 'IPv6' : 'IPv4'
              , 'internal' => $iface['__internal']
              );

            unset($iface);
          }

          unset($matches);
        } unset($row, $res);

        unset($result['__currentInterface']);

        return array_filter(array_map(compose('array_filter', removes('__internal')), $result));

      case 'LINUX':
        // $ifaces = `ifconfig -a | sed 's/[ \t].*//;/^\(lo\|\)$/d'`;
        // $ifaces = preg_split('/\s+/', $ifaces);
        $res = preg_split('/\n/', @`ip addr`);
        $res = array_filter(array_map('trim', $res));

        $result = array();

        foreach ( $res as $row ) {
          if ( preg_match('/^\d+\:\s+(\w+)/', $row, $matches) ) {
            $result['__currentInterface'] = $matches[1];
          }
          else if ( preg_match('/^link\/(\w+)/', $row, $matches) ) {
            $result[$result['__currentInterface']]['__internal'] = strtolower($matches[1]) == 'loopback';
          }
          else if ( preg_match('/^inet(6)?\s+([^\/]+)(?:\/\d+)?.+\s([\w\d]+)(?:\:\d+)?$/', $row, $matches) ) {
            @$result[$matches[3]][] = array(
                'address' => $matches[2]
              , 'family' => $matches[1] ? 'IPv6' : 'IPv4'
              , 'internal' => Utility::cascade(@$result[$matches[3]]['__internal'], false)
              );
          }

          unset($matches);
        } unset($row, $res);

        unset($result['__currentInterface']);

        return array_filter(array_map(compose('array_filter', removes('__internal')), $result));

      case 'WINNT': // Currently not supported.
      default:
        return false;
    }
  }

  /**
   * Autoload PHP classes
   */
  public static function __autoload($name) {
    // Namespace path fix
    $name = str_replace('\\', '/', ltrim($name, '\\'));

    // Classname path fix
    // Note: Partially abide to PSR-0, ignoring starting and trailing underscores.
    $name = dirname($name) . '/' . preg_replace('/(\w+)_(\w+)/', '$1/$2', basename($name));

    // Current directory fix
    /*! Note: This is due to an old bug when destructors are called, the working
     *  directory will change to the drive root.
     */
    if ( strpos(getcwd(), System::getPathname()) === false ) {
      chdir(System::getPathname());
    }

    // Look up current folder
    if ( file_exists("./$name.php") ) {
      require_once("./$name.php");
    }

    // Loop up script folder by namespace prefixes
    else {
      $lookupPaths = (array) self::$pathPrefixes;

      // Assumption: wildcards are always shorter than exact matches because "*" only has one character.
      $prefix = array_reduce(
        array_keys($lookupPaths),
        function($result, $prefix) use(&$name) {
          // Wildcards
          if ( strpos($prefix, '*') !== false ) {
            if ( !preg_match('/^' . preg_replace('/\\\\\*/', '.*', preg_quote($prefix)) . '/', $name) ) {
              unset($prefix);
            }
          }
          else if ( strpos($name, $prefix) === false ) {
            unset($prefix);
          }

          if ( isset($prefix) && strlen($result) < strlen($prefix) ) {
            $result = $prefix;
          }

          return $result;
        });

      if ( !$prefix ) {
        return;
      }

      // Exact matches should remove prefix portion (PSR-4)
      if ( strpos($prefix, '*') === false ) {
        $name = trim(substr($name, strlen($prefix)), '/');
      }

      $lookupPaths = (array) @$lookupPaths[$prefix];

      foreach ( $lookupPaths as $lookupPath ) {
        $lookupPath = "$lookupPath/$name.php";
        if ( file_exists($lookupPath) ) {
          require_once($lookupPath);
        }
      }
    }
  }

  /**
   * Bootstrap system autoloading.
   */
  public static function bootstrap() {
    if ( function_exists('spl_autoload_register') ) {
      spl_autoload_register(array(__CLASS__, '__autoload'));
    }

    // Make sure working directory the same as gateway.php
    @chdir(self::getPathname());

    $prefixes = (array) conf::get('system::paths.autoload');
    if ( $prefixes ) {
      self::$pathPrefixes = $prefixes;
    }

    // additional files
    foreach ( (array) @conf::get('system::paths.requires') as $file ) {
      require_once($file);
    }

    foreach ( (array) @conf::get('system::paths.includes') as $file ) {
      include_once($file);
    }
  }

}
