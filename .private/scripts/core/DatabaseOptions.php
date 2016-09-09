<?php
/* DatabaseOptions.php | https://github.com/victopia/php-node */

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
  public $driverAttributes = array();

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
                       $driverAttributes = array(),
                       $username = null,
                       $password = null,
                       $options = array()) {
    $this->driver = $driver;
    $this->driverAttributes = $driverAttributes;
    $this->username = $username;
    $this->password = $password;
    $this->options = $options;
  }

  /**
   * Construct a PDO compatible data source name (DSN)
   *
   * We don't restrict driver attributes because PDO documentation is just not stable enough.
   */
  function toPdoDsn() {
    $attrs = array();

    foreach ( array_filter($this->driverAttributes) as $key => $value ) {
      $attrs[] = "$key=$value";
    }

    return $this->driver . ':' . implode(';', $attrs);
  }

}
