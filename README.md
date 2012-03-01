# [PHP MySQL] Node Data Framework

Reminder: This framework is developed and named as-is since 2008, and has nothing to do with Node.js

## Basics

This framework aims to provide simple select and upsert upon a database, seamlessly manipulating data across virtual (JSON encoded) columns and rational columns.

This project is more a prove of concept than production use, I made this for an easy code transition for my team from MySQL to MongoDB.

Some of the ideas might seems irrational, feel free to discuss every tiny bit in the project.

## Usage
### Node

This class reads from and writes to a single table, which name is defined with constant `NODE_TABLENAME`. By default it is named `Nodes`.

Assume we have the following data structure,

	+----------------------+------------+---------------------------------------+---------------------+
	| ID                   | identifier | content                               | timestamp           |
	+----------------------+------------+---------------------------------------+---------------------+
	| 00000000000000000001 | User       | {"username":"root","password":"..."}  | 2012-03-01 11:59:55 |
	| 00000000000000000002 | User       | {"username":"Peter","password":"..."} | 2012-03-01 12:00:10 |
	+----------------------+------------+---------------------------------------+---------------------+

We can search the database with these code,

	// Remarks: `ID` and `identifier` is required to be rational column at the moment,
	//          as these are derived from the primary ideas when building this thing.
	//          They are meant to be optional in later time.

	$filter = Array(
		'identifier' => 'User',
		'username' => 'root'
	);
	
	$result = Node::get($filter);
	
	print_r($result);
	
And the result will be look like these,

	Array(
		[0] => Array(
			'username' => 'root'
			'password' => '...'
		)
	)

### Session

Make use of table type MEMORY, take advantage of memory storage and clears sessions upon reboot.

	// Authenticate a user and returns a session ID string.
	//
	// If a current session with this user exists, constant SESSION_ERR_EXISTS 
	// is returned unless parameter $overrideExists is true.
	Session::validate($username, $password, $overrideExists);
	
	// Deletes a session with specified session ID string if exists, do nothing otherwise.
	Session::invalidate($sid);
	
	// Validates and extends an existing session.
	//
	// An optional one-time token can be passed for extended security.
	// See Session::generateToken() for more details.
	//
	// Note that sessions are meant to expire after 30 minutes of inactivity.
	Session::ensure($sid, $token = NULL);
	
	// Revives an expired session.
	//
	// Returns FALSE if no such session exists.
	Session::restore($sid);
	
	// Generates a unique token for a request next time.
	// Validation fails if such a token exists and no $token is specified on Session::ensure();
	Session::generateToken($sid);

### Log

Basic logging into database.

	// Overloaded function write()
	
	// This writes the content without logging a specific user.
	Log::write($contents);
	
	// This validates a session with 'sid' and acquires user info when logging.
	Log::write($sid, $identifier, $action, $remarks = NULL);

## Low level classes

The `Database` class is build way earlier then Node frameworks, and has a lower level of simplicity upon rational uses.

### Database

Read the code for more info.