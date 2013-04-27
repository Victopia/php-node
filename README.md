# [PHP MySQL] Node Data Framework

This framework is developed and named since 2008, and has nothing to do with Node.js

This is in on-going development and actively used by me on casual websites, but you're free to use it.

Just create issues in github as usual, I am open for discussions.

## Basics

This framework aims to provide simple select and upsert upon a database, seamlessly manipulating data across virtual (JSON encoded) columns and rational columns.

Some of the ideas might seems irrational, feel free to discuss every tiny bit in the project.

## Usage

This is a [Front Controller pattern](http://en.wikipedia.org/wiki/Front_Controller_pattern) implementation of PHP using apache's .htaccess file.

All scripts are initialized with gateway and their corresponding resolvers.

### Special directories

These directories behave differently as normal folders, they are configurable via the .htaccess file. The documented names are only defaults.

#### .private
This directory is prevented from access. For any reason you don't want some files to be public, place them here.

#### .private/scripts
This directory contains framework scripts, and is not supposed to be accessed by public.

Feel free to add your own scripts here.

#### .privae/services
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

```PHP
  +----------------------+-------------+---------------------------------------+---------------------+
  | ID                   | @collection | @contents                             | timestamp           |
  +----------------------+-------------+---------------------------------------+---------------------+
  | 00000000000000000001 | User        | {"username":"root","password":"..."}  | 2012-03-01 11:59:55 |
  | 00000000000000000002 | User        | {"username":"Peter","password":"..."} | 2012-03-01 12:00:10 |
  +----------------------+-------------+---------------------------------------+---------------------+
```

We can search the database with these code,

```PHP
  // Remarks: `ID` and `identifier` is required to be rational column at the moment,
  //          as these are derived from the primary ideas when building this thing.
  //          They are meant to be optional in later time.

  $filter = Array(
    NODE_FIELD_COLLECTION => 'User',
    'username' => 'root'
  );

  $result = Node::get($filter);

  print_r($result);
```

And the result will be look like this,

```PHP
  Array(
    [0] => Array(
      '@collection' => 'User',
      'ID' => '00000000000000000001',
      'username' => 'root',
      'password' => '...',
      'timestamp' => '2012-03-01 11:59:55'
    )
  )
```

### ImageConverter

Utilize and depends on the GD library, for easy resizing, cropping and converting images into different formats.

Sample usage:

```PHP
$converter = new core\ImageConverter($_FILES['image']['tmp_name']);

// resize to 720p regardless of aspect ratio.
$conveter->resizeTo(1280, 720);

// resize image to match the 720p bounding box, respecting aspect ratio.
$conveter->resizeTo(1280, 720, array(
    'ratioPicker' => 'min'
  ));

// resize image to at least 720p.
$conveter->resizeTo(1280, 720, TRUE, array(
    'ratioPicker' => 'max'
  ));

// resize image to at least 720p, and cropping exceeded pixels.
$conveter->resizeTo(1280, 720, TRUE, array(
    'ratioPicker' => 'max'

  // $dst_x and $dst_y in imagecopyresampled(), default to (0, 0) which is the top-left corner.
  , 'cropsTarget' => array(
      // offset pixels, 'auto' or 'center' (they have the same meaning)
      'x' => 'center'
    , 'y' => 50
    )
  ));

$image = $converter->getImage(/* specify a mime-type, or the original one is returned. */);

$converter->close(); // release memory

```

### XMLConverter

Originally this is built as an XML formatter for `core\Net`, while exposed as a public class to be used alone.

```PHP
$phpArray = core\XMLConverter::fromXML('<a><b1 c="c">d</b1><b2>c</b2></a>');

var_dump($phpArray);

/* OUTPUT:

array(1) {
  ["a"]=>
  array(2) {
    ["b1"]=>
    array(2) {
      ["@attributes"]=>
      array(1) {
        ["c"]=>
        string(1) "c"
      }
      ["@value"]=>
      string(1) "d"
    }
    ["b2"]=>
    string(1) "c"
  }
}

*/

```

### Session

Make use of table type MEMORY, take advantage of memory storage and clears sessions upon reboot.

```PHP
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
```

### Log

Basic logging into database.

```PHP
  // Overloaded function write()

  // This writes the content without logging a specific user.
  Log::write($contents);

  // This validates a session with 'sid' and acquires user info when logging.
  Log::write($sid, $identifier, $action, $remarks = NULL);
```

## Low level classes

The `Database` class is build way earlier then Node frameworks, and has a lower level of simplicity upon rational uses.

### Database

Read the code for more info.

### Net

This class is inspired by the old jQuery ajax style, without the deferred/promise object return. Promises are likely to be implemented in the near future.

Sample usage:

```PHP

net::httpRequest(array(
  'url' => 'http://www.google.com'
, 'type' => 'POST'
, 'data' => array( /* data object to be encoded */ )
, 'dataType' => 'xml'

  /* Any callable formats are accepted here. */
  'success' => function() {}
, 'failure' => function() {}
, 'complete' => function() {}
));

```

### Event
### EventEmitter

Name of the event classes are inspired by node.js, while PHP is a blocking language this make little advantage but favoring the JavaScript-like coding style.

### Deferred
### Promise

Porting the functionalities from jQuery deferred/promise objects, see their docs [here](http://api.jquery.com/category/deferred-object/).