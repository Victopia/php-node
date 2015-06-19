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

  function __construct($driver = 'mysql',
                       $host = 'localhost',
                       $port = 3306,
                       $schema = null,
                       $username = null,
                       $password = null,
                       $driverOptions = array()) {
    $this->driver = $driver;
    $this->host = $host;
    $this->port = $port;
    $this->schema = $schema;
    $this->username = $username;
    $this->password = $password;
    $this->driverOptions = $driverOptions;
  }

  /**
   * PDO DSN prefix, such as "mysql", "oci" or "odbc". Do not add colon.
   */
  public $driver;

  /**
   * PDO Driver options.
   */
  public $driverOptions = array();

  /**
   * Database connection hostname.
   */
  public $host;

  /**
   * Database connection port.
   */
  public $port;

  /**
   * The database name.
   */
  public $schema;

  /**
   * Login username.
   */
  public $username;

  /**
   * Login password.
   */
  public $password;

}
