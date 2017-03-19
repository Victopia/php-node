<?php /*! gateway.php | Starting point of all URI access. */

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

  // Insert resolvers from options
  foreach ( (array) conf::get('web::resolvers') as $index => $_resolver ) {
    $resolverClass = @"resolvers\\$_resolver[class]";
    if ( class_exists($resolverClass) ) {
      $resolverClass = new $resolverClass((array) @$_resolver['options']);

      $resolver->registerResolver($resolverClass);
    }

    unset($resolverClass, $_resolver);
  }

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

// Request context is built from constructor, let resolver instantiate it.

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
