<?php
/*! gateway.php | Starting point of all URI access. */

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

//--------------------------------------------------
//
//  Resolve the request
//
//--------------------------------------------------

$resolver = new framework\Resolver();

// Maintenance resolver
$resolver->registerResolver(new resolvers\MaintenanceResolver(FRAMEWORK_PATH_MAINTENANCE_TEMPLATE), 999);

// Session authentication
$resolver->registerResolver(new resolvers\UserContextResolver(array(
    'setup' => true // This enables the "startup" super user when no user in database.
  )), 100);

// AuthententicationResolver
$resolver->registerResolver(new resolvers\AuthenticationResolver(), 80);

// Locale
$resolver->registerResolver(new resolvers\LocaleResolver(array(
  'default' => framework\Configuration::get('core.i18n::localeChain')
  )), 70);

// Web Services
$resolver->registerResolver(new resolvers\WebServiceResolver('/service/'), 60);

// Markdown handling
$resolver->registerResolver(new resolvers\MarkdownResolver(), 50);

// Template resolver
// $templateResolver = new resolvers\TemplateResolver(array(
//     'render' => function($path) {
//         static $mustache;
//         if ( !$mustache ) {
//           $mustache = new Mustache_Engine();
//         }

//         $resource = util::getResourceContext();

//         return $mustache->render(file_get_contents($path), $resource);
//       }
//   , 'extensions' => 'mustache html'
//   ));

// $templateResolver->directoryIndex('Home index');

// $resolver->registerResolver($templateResolver, 50);

// unset($templateResolver);

// External URL
// $resolver->registerResolver(new resolvers\ExternalResolver(), 30);

// Physical file handling
$fileResolver = new resolvers\FileResolver();
$fileResolver->directoryIndex('Home index');

$resolver->registerResolver($fileResolver, 10);

unset($fileResolver);

/*! Note @ 24 Apr, 2015
 *  Cache resolver is now disabled because it handles way more than expected,
 *  such as conditional request and etag.
 *
 *  $resolver->registerResolver(new resolvers\CacheResolver('/cache/'), 0);
 */

// Error status in HTML or JSON
$resolver->registerResolver(new resolvers\StatusDocumentResolver('assets/errordocs'), 5);

// Logging
$resolver->registerResolver(new resolvers\LogResolver(), 0);

// Start the application
$resolver->run();
