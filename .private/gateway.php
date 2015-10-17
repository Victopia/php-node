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

use framework\Configuration as conf;
use framework\System;

//--------------------------------------------------
//
//  Initialization
//
//--------------------------------------------------

require_once('scripts/Initialize.php');

//--------------------------------------------------
//
//  Resolve the request
//
//--------------------------------------------------

// Resolver chain
  $resolver = new framework\Resolver();

  // Maintenance resolver
    // Simply don't put it into chain when disabled.
    if ( conf::get('system::maintenance.enable') ) {
      $resolver->registerResolver(new resolvers\MaintenanceResolver(array(
          'templatePath' => conf::get('system::maintenance.templatePath'),
          'whitelist' => (array) conf::get('system::maintenance.whitelist')
        )), 999);
    }

  // Session authentication
    $resolver->registerResolver(new resolvers\UserContextResolver(array(
        // This enables the "startup" super user when no user in database.
        'setup' => System::environment() != System::ENV_PRODUCTION
      )), 100);

  // Access rules and policy
    $resolver->registerResolver(new resolvers\AuthenticationResolver(array(
        'paths' => conf::get('web::auth.paths'),
        'statusCode' => conf::get('web::auth.statusCode')
      )), 80);

  // Locale
    $resolver->registerResolver(new resolvers\LocaleResolver(array(
        'default' => conf::get('system::i18n.localeDefault', 'en_US')
      )), 70);

  // JSONP Headers
    $resolver->registerResolver(new resolvers\JsonpResolver(array(
        'defaultCallback' => conf::get('web::resolvers.jsonp.defaultCallback', 'callback')
      )), 65);

  // Web Services
    $resolver->registerResolver(new resolvers\WebServiceResolver(array(
        'prefix' => conf::get('web::resolvers.service.prefix', '/service')
      )), 60);

  // Post Processers
    $resolver->registerResolver(new resolvers\InvokerPostProcessor(array(
        'invokes' => 'invokes',
        'unwraps' => 'core\Utility::unwrapAssoc'
      )), 50);

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
    $resolver->registerResolver(new resolvers\ExternalResolver(array(
        'source' => conf::get('system::paths.external.src')
      )), 40);

  // Markdown handling
    $resolver->registerResolver(new resolvers\MarkdownResolver(), 30);

  // SCSS Compiler
    $resolver->registerResolver(new resolvers\ScssResolver(array(
        'source' => conf::get('system::paths.scss.src'),
        'output' => conf::get('system::paths.scss.dst', 'assets/css')
      )), 30);

  // LESS Compiler
    $resolver->registerResolver(new resolvers\LessResolver(array(
        'source' => conf::get('system::paths.less.src'),
        'output' => conf::get('system::paths.less.dst', 'assets/css')
      )), 30);

  // Css Minifier
    $resolver->registerResolver(new resolvers\CssMinResolver(array(
        'source' => conf::get('system::paths.cssmin.src'),
        'output' => conf::get('system::paths.cssmin.dst', 'assets/css')
      )), 20);

  // Js Minifier
    $resolver->registerResolver(new resolvers\JsMinResolver(array(
        'source' => conf::get('system::paths.jsmin.src'),
        'output' => conf::get('system::paths.jsmin.dst', 'assets/js')
      )), 20);

  // Physical file handling
    $fileResolver = array(
        'directoryIndex' => conf::get('web::resolvers.file.indexes', 'Home index')
      );

    if ( conf::get('web::http.output.buffer.enable') ) {
      $fileResolver['outputBuffer']['size'] = conf::get('web::http.output.buffer.size', 1024);
    }

    $fileResolver = new resolvers\FileResolver($fileResolver);

    $resolver->registerResolver($fileResolver, 10);

    unset($fileResolver);

  // HTTP error pages in HTML or JSON
    $resolver->registerResolver(new resolvers\StatusDocumentResolver(array(
        'prefix' => conf::get('web::resolvers.errordocs.directory')
      )), 5);

  // Logging
    $resolver->registerResolver(new resolvers\LogResolver(), 0);

// Request context
  // We have no request context options currently, use defaults.

// Response context
  if ( conf::get('web::http.output.buffer.enable') ) {
    $response = new framework\Response(array(
        'outputBuffer' => array(
          'size' => conf::get('web::http.output.buffer.size', 1024)
        )
      ));
  }

// Start the application
  $resolver->run(@$request, @$response);
