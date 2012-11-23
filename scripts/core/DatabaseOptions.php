<?php

namespace core;

/**
 * Settings for database connections.
 *
 * Must be set before Database can function.
 */
class DatabaseOptions {
	public function __construct($driver = null,
              								$host = null,
              								$port = null,
            								 	$schema = null,
            								 	$username = null,
            								 	$password = null) {
		if (!is_null($driver))
			$this->driver = $driver;
		
		if (!is_null($host))
			$this->host = $host;
		
		if (!is_null($port))
			$this->port = $port;
		
		if (!is_null($schema))
			$this->schema = $schema;
		
		if (!is_null($username))
			$this->username = $username;
		
		if (!is_null($password))
			$this->password = $password;
	}
	
	public $driver = 'mysql';
	
	public $host = 'localhost';
	
	public $port = 3306;
	
	public $schema;
	
	public $username;
	
	public $password;
	
	public $driverOptions;
}