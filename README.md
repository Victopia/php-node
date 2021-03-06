<pre>
             DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
                      Version 2, December 2004

 Copyright (C) 2008 Vicary Archangel <vicary@victopia.org>

 Everyone is permitted to copy and distribute verbatim or modified
 copies of this license document, and changing it is allowed as long
 as the name is changed.

             DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION

  0. You just DO WHAT THE FUCK YOU WANT TO.
</pre>

# [PHP MySQL] Node Data Framework

This framework is developed and named since 2008, and has nothing to do with Node.js

This is in on-going development and actively used by me on casual websites, but you're free to use it.

Just create issues in github as usual, I am open for discussions.

**Please note that the following components will be broken down into separate repositories for standalone Composer packages.**

## Basics

This framework aims to provide simple select and upsert upon a database, seamlessly manipulating data across virtual (JSON encoded) columns and rational columns.

Some of the ideas might seems irrational, feel free to discuss every tiny bit in the project.

## Usage

This is a [Front Controller pattern](http://en.wikipedia.org/wiki/Front_Controller_pattern) implementation of PHP using apache's .htaccess file.

All scripts are initialized with gateway and their corresponding resolvers.

### Node

A cascading mechanism to implement document-like storage into MySQL database (probably others later).

You store and retrieve PHP data with this framework.

What it exactly does, as a cascading logic:

1. Table level virtualization
    1. If a table exists with the name specified in Node::FIELD_COLLECTION (defaults to '@collection'), use it.
    2. Otherwise fallback to Node::BASE_COLLECTION, which defaults to 'Nodes'.
2. Column level virtualization
    1. If a column of the same name as that PHP array key exists, use it.
    2. If there is other fields left, json encode them and put the result string into the column with the name as Node::FIELD_VIRTUAL, which defaults to '@contents'.
    3. If column Node::FIELD_VIRTUAL does not exists, those virtual fields are dropped silently.

For all constants, see scripts/framework/constants.php.

Assume we have the following data structure,

```PHP
+----------------------+-------------+---------------------------------------+---------------------+
| ID                   | @collection | @contents                             | timestamp           |
+----------------------+-------------+---------------------------------------+---------------------+
| 00000000000000000001 | users       | {"username":"root","password":"..."}  | 2012-03-01 11:59:55 |
| 00000000000000000002 | users       | {"username":"Peter","password":"..."} | 2012-03-01 12:00:10 |
+----------------------+-------------+---------------------------------------+---------------------+
```

We can search the database with these code,

```PHP
// Remarks: `ID` and `identifier` is required to be rational column at the moment,
//          as these are derived from the primary ideas when building this thing.
//          They are meant to be optional in later time.

$filter = Array(
  Node::FIELD_COLLECTION => 'users',
  'username' => 'root'
);

$result = Node::get($filter);

print_r($result);
```

And the result will be look like this,

```PHP
Array(
  [0] => Array(
    '@collection' => 'users',
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

This is a wrapper of Psr\Log interface.

Instantiate the logger and call `Log::setLogger()` at initialize.

```PHP
// Any Psr\Log compatible loggers
Log::setLogger($logger);

// __callStatic will be piped to logger
static function __callStatic($name, $args) {
  return call_user_func_array(array($this->getLogger(), $name), $args);
}

// Calling log methods directly from the Log class.
Log::log($type, $message, $context);
Log::emergency($message, $context);
Log::alert($message, $context);
Log::critical($message, $context);
Log::error($message, $context);
Log::warning($message, $context);
Log::notice($message, $context);
Log::info($message, $context);
Log::debug($message, $context);
```

## Low level classes

The `Database` class is build way earlier then Node frameworks, and has a lower level of simplicity upon rational uses.

### Database

Read the code for more info.

### Net

This class is inspired by the old jQuery ajax style, without the deferred/promise object returned, while they are likely to be implemented in the near future.

Sample usage:

```PHP
Net::httpRequest(array(
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
