<?php
/*! constants.php
 *
 *  Define framework wide constants.
 */

// Global system constants

// Epoach in seconds before 1970-01-01
define('EPOACH', -62167246596);

// Table name for Data class
define('NODE_COLLECTION', 'Nodes');
// A field for Node framework to identify as table (collection).
define('NODE_FIELD_COLLECTION', '@collection');
// Physical column for storing virtual field data
define('NODE_FIELD_VIRTUAL', '@contents');
// Untapped queries passed directly into SQL statements
define('NODE_RAWQUERY', md5('@@rawQuery'));
// Row limit for each data fetch, be careful on setting this.
// Required system resources will change exponentially.
define('NODE_FETCHSIZE', '100');

// Current environment, 'debug' or 'production'
define('FRAMEWORK_ENVIRONMENT', 'debug');

// Collection of system configurations
define('FRAMEWORK_COLLECTION_CONFIGURATION', 'Configuration');
// Collection of Node hirarchy relations
define('FRAMEWORK_COLLECTION_RELATION', 'Relation');
// Collection of system logs
define('FRAMEWORK_COLLECTION_LOG', 'Log');
// Collection of processes
define('FRAMEWORK_COLLECTION_PROCESS', 'Processes');
// Collection of users
define('FRAMEWORK_COLLECTION_USER', 'User');
// Collection of user data
define('FRAMEWORK_COLLECTION_USER_DATA', 'UserData');
// Collection of http user sessions
define('FRAMEWORK_COLLECTION_SESSION', 'Session');
// Collection of system messages
define('FRAMEWORK_COLLECTION_MESSAGE', 'Message');
// Collection of user files, basic file system implementation.
define('FRAMEWORK_COLLECTION_FILE', 'File');

// Times to retry to ensure a process is written to disc,
// before spawning background process
define('FRAMEWORK_PROCESS_INSERT_RETRY_COUNT', 10);
// Times to retry when deletion on process table fails.
define('FRAMEWORK_PROCESS_DELETE_RETRY_COUNT', 50);
// Interval to wait before checks that ensure data is written to disk.
define('FRAMEWORK_DATABASE_ENSURE_WRITE_INTERVAL', 0.4);
// Seconds to wait before sending next core\Net progress event.
define('FRAMEWORK_NET_PROGRESS_INTERVAL', 0.3);
// Respose Header - Cache-Control: max-age=
define('FRAMEWORK_RESPONSE_CACHE_AGE', 108000);
// Seconds to delay before checks for updates on external resources
define('FRAMEWORK_EXTERNAL_UPDATE_DELAY', 18000);
// Regex pattern to match custom request headers
define('FRAMEWORK_CUSTOM_HEADER_PATTERN', '/^X\-/');
// Date format for framework outputs
define('FRAMEWORK_DATE_FOTMAT', 'd M, H:i');
// Quick search items limit per type
define('FRAMEWORK_SEARCH_QUICK_LIMIT', 10);
// Date format for search
define('FRAMEWORK_SEARCH_DATE_FORMAT', 'M, Y');

//--------------------------------------------------
//
//  Short hand of frequently used classes
//
//--------------------------------------------------
class_alias('\core\Node',     'node');
class_alias('\core\Relation', 'relation');
class_alias('\core\Utility',  'utils');
class_alias('\core\Log',      'log');
class_alias('\core\Debugger', 'debug');

class_alias('\framework\Configuration', 'conf');
class_alias('\framework\Cache',   'cache');
class_alias('\framework\Session', 'session');
class_alias('\framework\Process', 'process');
class_alias('\framework\Service', 'service');
class_alias('\framework\Message', 'message');

// Session globals
define('FRAMEWORK_USR_PUBLIC', Session::USR_PUBLIC);
define('FRAMEWORK_USR_NORMAL', Session::USR_NORMAL);
define('FRAMEWORK_USR_ADMINS', Session::USR_ADMINS);
define('FRAMEWORK_USR_BANNED', Session::USR_BANNED);