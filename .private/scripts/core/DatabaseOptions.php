<?php /* DatabaseOptions.php | https://github.com/victopia/php-node */

namespace core;

/**
 * Settings for database connections.
 *
 * Must be set before the Database class can function.
 *
 * @author Vicary Archangel <vicary@victopia.org>
 */
final class DatabaseOptions {

  //----------------------------------------------------------------------------
  //
  //  Properties
  //
  //----------------------------------------------------------------------------

  /**
   * PDO DSN prefix, such as "mysql", "oci" or "odbc". Do not add colon.
   */
  public $driver;

  /**
   * PDO driver attributes.
   */
  public $attributes = array();

  /**
   * PDO driver specific options.
   */
  public $options = array();

  /**
   * Login username.
   */
  public $username;

  /**
   * Login password.
   */
  public $password;

  //----------------------------------------------------------------------------
  //
  //  Methods
  //
  //----------------------------------------------------------------------------

  function __construct($driver = 'mysql',
                       $attributes = array(),
                       $username = null,
                       $password = null,
                       $options = array()) {
    $this->driver = $driver;
    $this->attributes = $attributes;
    $this->username = $username;
    $this->password = $password;
    $this->options = $options;
  }

  /**
   * Shorthand method for reading JSON/static array options.
   */
  static function fromArray(array $options = []) {
    if ( is_array(@$options['options']) ) {
      $driverOptions = [];

      foreach ( (array) @$options['options'] as $key => $value ) {
        if ( defined("PDO::$key") ) {
          $driverOptions[constant("PDO::$key")] = $value;
        }
      }

      $options['options'] = $driverOptions;

      unset($key, $value, $driverOptions);
    }

    if ( empty($options['options']) ) {
      unset($options['options']);
    }

    $options = filter_var_array($options, [
      'driver' => FILTER_SANITIZE_STRING,
      'attributes' => [ 'flags' => FILTER_FORCE_ARRAY ],
      'username' => FILTER_SANITIZE_STRING,
      'password' => FILTER_SANITIZE_STRING,
      'options' => [ 'flags' => FILTER_FORCE_ARRAY ]
    ]);

    return new static(
      $options['driver'],
      $options['attributes'],
      $options['username'],
      $options['password'],
      $options['options']
    );
  }

  /**
   * Construct a PDO compatible data source name (DSN)
   *
   * We don't restrict driver attributes because PDO documentation is just not stable enough.
   */
  function toPdoDsn() {
    $attributes = array();

    foreach ( array_filter($this->attributes) as $key => $value ) {
      $attributes[] = "$key=$value";
    }

    return $this->driver . ':' . implode(';', $attributes);
  }

}
