<?php
/* Gateway.php | Starting point of all URI access. */

/***********************************************************************\
**                                                                     **
**             DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE             **
**                      Version 2, December 2004                       **
**                                                                     **
** Copyright (C) 2008 Vicary Archangel <vicary@victopia.org>           **
**                                                                     **
** Everyone is permitted to copy and distribute verbatim or modified   **
** copies of this license document, and changing it is allowed as long **
** as the name is changed.                                             **
**                                                                     **
**             DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE             **
**   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION   **
**                                                                     **
**  0. You just DO WHAT THE FUCK YOU WANT TO.                          **
**                                                                     **
\************************************************************************/

//--------------------------------------------------
//
//  Initialization
//
//--------------------------------------------------
require_once('.private/scripts/Initialize.php');

//------------------------------
//  Environment variables
//------------------------------
foreach ($_SERVER as $key => $value) {
	if ($key == 'REDIRECT_URL') continue;

	$res = preg_replace('/^REDIRECT_/', '', $key, -1, $count);

	if ($count > 0) {
		unset($_SERVER[$key]);

		$_SERVER[$res] = $value;
	}
}

if (isset($_SERVER['HTTP_STATUS'])) {
	redirect(intval($_SERVER['HTTP_STATUS']));
}

//------------------------------
//  JSON typed POST
//------------------------------

if ( @$_SERVER['REQUEST_METHOD'] == 'POST' && core\Request::headers('Content-Type') == 'application/json' ) {
  $_POST = json_decode(file_get_contents('php://input'), TRUE);
}

//------------------------------
//  Session & Authentication
//------------------------------
$sid = NULL;

// Follow variables_order, i.e. POST > GET > COOKIES.
if (isset($_REQUEST['sid'])) {
	$sid = $_REQUEST['sid'];
}
else
if (isset($_COOKIE['sid'])) {
	$sid = $_COOKIE['sid'];
}
// Discouraged usage but still, check for PHP sessions.
// elseif (isset($_SESSION['sid'])) { $sid = $_SESSION['sid']; }

// Session ID provided, validate it.
if ($sid !== NULL) {
	$res = session::ensure($sid);

	if ($res === FALSE) {
		// Session doesn't exists, delete exsting cookie.
		setcookie('sid', '', time() - 3600);
	}
	else if (is_integer($res)) {
		switch ($res) {
			// Reserved error code for client use
			case session::ERR_EXPIRED:
				throw new framework\exceptions\ServiceException('Current user is restricted.');
				break;
			// Treat as public user.
			case session::ERR_INVALID:
				break;
		}
	}
	else {
		// Success, proceed.
	}
}

unset($sid, $_POST['sid'], $_GET['sid'], $_REQUEST['sid'], $_COOKIE['sid']);

//--------------------------------------------------
//
//  Request handling
//
//--------------------------------------------------

//------------------------------
//  Access log
//------------------------------
if (FRAMEWORK_ENVIRONMENT == 'debug') {
  $accessTime = microtime(1);
}

//------------------------------
//  Resolve request URI
//------------------------------

$resolver = 'framework\Resolver';

// Web Services
$resolver::registerResolver(new resolvers\WebServiceResolver('/:service/'), 60);

// Cache resolver
$resolver::registerResolver(new resolvers\CacheResolver('/:cache/'), 50);

// Template resolver
$resolver::registerResolver(new resolvers\TemplateResolver(), 40);

// Database resources
$resolver::registerResolver(new resolvers\ResourceResolver('/:resource/'), 30);

// External URL
$resolver::registerResolver(new resolvers\ExternalResolver(), 20);

// Physical file resolver ( Directory Indexing )
$fileResolver = new resolvers\FileResolver();

$fileResolver->directoryIndex('Home index');

$fileResolver->cacheExclusions('htm html php');

$resolver::registerResolver($fileResolver, 10);

// Perform resolving
$response = $resolver::resolve($_SERVER['REQUEST_URI']);

unset($resolver);

//------------------------------
//  Access log
//------------------------------
if (FRAMEWORK_ENVIRONMENT == 'debug') {
  log::write("$_SERVER[REQUEST_METHOD] $_SERVER[REQUEST_URI]", 'Debug', array_filter(array(
      'origin' => @$_SERVER['HTTP_REFERER']
    , 'userAgent' => \utils::cascade(@$_SERVER['HTTP_USER_AGENT'], 'Unknown')
    , 'timeElapsed' => round(microtime(1) - $accessTime, 4) . ' secs'
    )));

  unset($accessTime);
}

// HTTP status code returned
if (is_integer($response)) {
	redirect($response);
}
