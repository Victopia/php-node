# [PHP MySQL] Node Data Framework

This framework is developed and named since 2008, and has nothing to do with Node.js

This is in on-going development and actively used by me on casual websites, but you're free to use it.

Just create issues in github as usual, I am open for discussions.

## Basics

This framework aims to provide simple select and upsert upon a database, seamlessly manipulating data across virtual (JSON encoded) columns and rational columns.

This project is more a prove of concept than production use, I made this for an easy code transition for my team from MySQL to MongoDB.

Some of the ideas might seems irrational, feel free to discuss every tiny bit in the project.

## Usage

This is a [Front Controller pattern](http://en.wikipedia.org/wiki/Front_Controller_pattern) implementation of PHP using apache's .htaccess file.

All scripts are initialized with gateway and their corresponding resolvers.

### Special directories

These directories behave differently as normal folders, they are configurable via the .htaccess file. The documented names are only defaults.

#### .private
This directory is prevented from access. For any reason you don't want some files to be public, place them here.

#### scripts
This directory contains framework scripts, and is not supposed to be accessed by public.

Feel free to add your own scripts here.

#### services
PHP scripts in this folder are served as RESTful methods, for more information see the example script inside and scripts/resolveers/WebServiceResolver.

### Node

A cascading mechanism to implement document-like storage into MySQL database (probably others later).

You store and retrieve PHP data with this framework.

What it exactly does, as a cascading logic:

1. Table level virtualization
    1. If a table exists with the name specified in NODE_FIELD_COLLECTION (defaults to '@collection'), use it.
    2. Otherwise fallback to NODE_COLLECTION, which defaults to 'Nodes'.
2. Column level virtualization
    1. If a column of the same name as that PHP array key exists, use it.
    2. If there is other fields left, json encode them and put the result string into the column with the name as NODE_FIELD_VIRTUAL, which defaults to '@contents'.
    3. If column NODE_FIELD_VIRTUAL does not exists, those virtual fields are dropped silently.

For all constants, see scripts/framework/constants.php.

Assume we have the following data structure,

	+----------------------+-------------+---------------------------------------+---------------------+
	| ID                   | @collection | @contents                             | timestamp           |
	+----------------------+-------------+---------------------------------------+---------------------+
	| 00000000000000000001 | User        | {"username":"root","password":"..."}  | 2012-03-01 11:59:55 |
	| 00000000000000000002 | User        | {"username":"Peter","password":"..."} | 2012-03-01 12:00:10 |
	+----------------------+-------------+---------------------------------------+---------------------+

We can search the database with these code,

	// Remarks: `ID` and `identifier` is required to be rational column at the moment,
	//          as these are derived from the primary ideas when building this thing.
	//          They are meant to be optional in later time.

	$filter = Array(
		NODE_FIELD_COLLECTION => 'User',
		'username' => 'root'
	);

	$result = Node::get($filter);

	print_r($result);

And the result will be look like this,

	Array(
		[0] => Array(
		  '@collection' => 'User',
		  'ID' => '00000000000000000001',
			'username' => 'root',
			'password' => '...',
			'timestamp' => '2012-03-01 11:59:55'
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