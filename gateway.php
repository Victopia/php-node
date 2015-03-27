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

use core\Log;
use core\Utility as util;

use framework\Configuration;
use framework\Session;

//--------------------------------------------------
//
//  Initialization
//
//--------------------------------------------------
require_once('.private/scripts/Initialize.php');

//------------------------------
//  Environment variables
//------------------------------
foreach ( $_SERVER as $key => $value ) {
  if ( $key == 'REDIRECT_URL' ) continue;

  $res = preg_replace('/^REDIRECT_/', '', $key, -1, $count);

  if ( $count > 0 ) {
    unset($_SERVER[$key]);

    $_SERVER[$res] = $value;
  }
}

if ( isset($_SERVER['HTTP_STATUS']) ) {
  redirect(intval($_SERVER['HTTP_STATUS']));
}

//------------------------------
//  JSON typed POST
//------------------------------

if ( @$_SERVER['REQUEST_METHOD'] == 'POST' && preg_match('/^application\/json/', core\Request::headers('Content-Type')) ) {
  $_POST = json_decode(file_get_contents('php://input'), true);
}

//------------------------------
//  Session & Authentication
//------------------------------
// Session ID provided, validate it.
$sid = util::cascade(@$_REQUEST['__sid'], @$_COOKIE['__sid']);
if ( $sid !== null ) {
  $res = Session::ensure($sid, @$_REQUEST['__token']);

  if ( $res === false || $res === Session::ERR_EXPIRED ) {
    // Session doesn't exists, delete exsting cookie.
    setcookie('__sid', '', time() - 3600);
  }
  else if ( is_integer($res) ) {
    switch ( $res ) {
      // Treat as public user.
      case Session::ERR_INVALID:
        break;
    }
  }
  else {
    // Success, proceed.
  }
}

$res = array(&$_POST, &$_GET, &$_REQUEST, &$_COOKIE);

foreach ( $res as &$ref ) {
  remove(['__sid', '__token'], $ref);
}

unset($sid, $res, $ref);

//------------------------------
//  Locale
//------------------------------
if ( @$_REQUEST['locale'] ) {
  setcookie('locale', $_REQUEST['locale'], FRAMEWORK_COOKIE_EXPIRE_TIME, '/');
}

$locale = util::cascade(@$_REQUEST['locale'], @$_COOKIE['locale'], 'en_US');

$resource = util::getResourceContext($locale);

unset($locale);

//--------------------------------------------------
//
//  Request handling
//
//--------------------------------------------------

//------------------------------
//  Access log
//------------------------------
if ( FRAMEWORK_ENVIRONMENT == 'debug' ) {
  $accessTime = microtime(1);
}

//------------------------------
//  Resolve request URI
//------------------------------

$resolver = 'framework\Resolver';

// Maintenance resolver
$resolver::registerResolver(new resolvers\MaintenanceResolver(FRAMEWORK_PATH_MAINTENANCE_TEMPLATE), 70);

// Web Services
$resolver::registerResolver(new resolvers\WebServiceResolver('/service/'), 60);

// Cache resolver
// $resolver::registerResolver(new resolvers\CacheResolver('/:cache/'), 50);

// Template resolver
$templateResolver = new resolvers\TemplateResolver(array(
    'render' => function($path) {
        static $mustache;

        if ( !$mustache ) {
          $mustache = new Mustache_Engine();
        }

        $resource = util::getResourceContext();

        return $mustache->render(file_get_contents($path), $resource);
      }
  , 'extensions' => 'mustache html'
  ));

$templateResolver->directoryIndex('Home index');

$resolver::registerResolver($templateResolver, 40);

unset($templateResolver);

// Database resources
// $resolver::registerResolver(new resolvers\ResourceResolver('/:resource/'), 30);

// External URL
$resolver::registerResolver(new resolvers\ExternalResolver(), 20);

// Physical file resolver ( Directory Indexing )
$fileResolver = new resolvers\FileResolver();
$fileResolver->directoryIndex('Home index');
$fileResolver->cacheExclusions('htm html php');

$resolver::registerResolver($fileResolver, 10);

unset($fileResolver);

// Perform resolving
$response = $resolver::resolve($_SERVER['REQUEST_URI']);

//------------------------------
//  Access log
//------------------------------
if ( FRAMEWORK_ENVIRONMENT == 'debug' ) {
  Log::write("$_SERVER[REQUEST_METHOD] $_SERVER[REQUEST_URI]", 'Debug', array_filter(array(
      'origin' => @$_SERVER['HTTP_REFERER']
    , 'userAgent' => util::cascade(@$_SERVER['HTTP_USER_AGENT'], 'Unknown')
    , 'timeElapsed' => round(microtime(1) - $accessTime, 4) . ' secs'
    )));

  unset($accessTime);
}

// HTTP status code returned
if ( is_integer($response) ) {
  redirect($response);
}
