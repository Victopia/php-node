<?php
/*! System.php | Operating system related functions. */

namespace framework;

class System {

  //----------------------------------------------------------------------------
  //
  //  Constants
  //
  //----------------------------------------------------------------------------

  const ENV_PRODUCTION = 'production';

  const ENV_DEBUG = 'debug';

  const DEFAULT_SERVICE_PATH = '.private/services';

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * Accessor to the current environment, defaults to debug.
   *
   * @param {?string} $value The new environment value.
   * @return {?string} When $value is omitted, it returns the current value.
   */
  public static function environment($value = null) {
    static $environment = self::ENV_DEBUG;

    if ( $value === null ) {
      return $environment;
    }

    $enum = array(
        self::ENV_PRODUCTION
      , self::ENV_DEBUG
      );

    if ( !in_array($value, $enum) ) {
      return false;
    }

    $environment = $value;
  }

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  /**
   * Returns a designated type of hostname from configuration.
   */
  public static function getHostname($type = null) {
    static $domains = array();

    $domain = &$domains[$type];
    if ( !$domain ) {
      switch ( strtolower($type) ) {
        case 'secure':
          $domain = Configuration::get('system.domains::hostname_secure');
          break;

        case 'service':
          $domain = Configuration::get('system.domains::hostname_service');
          break;

        case 'local':
          $domain = Configuration::get('system.domains::hostname_local', 'localhost');
          break;

        default:
          $domain = Configuration::get('system.domains::hostname', gethostname());
          break;
      }
    }

    return $domain;
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
   * Get the system root path.
   *
   * Approach 1: Based on relative directory of this script file.
   *
   * Approach 2: The last element of debug_backtrace(), may consume more memories
   *             so cache it with static.
   *
   * @param {string} $type Configuration path to the base path relative to system root.
   */
  public static function getRoot($type = '') {
    static $root;
    if ( !$root ) {
      // Approach 1
      $root = implode(DS, [__DIR__, '..', '..', '..']);
      $root = realpath($root);

      // Approach 2
      // $root = debug_backtrace();
      // $root = array_pop($root);
      // $root = dirname($root['file']);
    }

    if ( $type ) {
      $type = (string) Configuration::get($type);
      if ( !$type ) {
        $type = self::DEFAULT_SERVICE_PATH;
      }
      return $root . DS . $type;
    }

    return $root;

    // $path = implode(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', '..']);
    // return realpath($path);
  }

}
