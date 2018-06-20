<?php

use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__.'/../app/bootstrap.php.cache';
// Use APC for autoloading to improve performance.
// Change 'sf2' to a unique prefix in order to prevent cache key conflicts
// with other applications also using APC.
/*
$loader = new ApcClassLoader('sf2', $loader);
$loader->register(true);
*/
require_once __DIR__.'/../app/AppKernel.php';
//require_once __DIR__.'/../app/AppCache.php';

$env = isset($_SERVER['SYMFONY__ENV']) ? $_SERVER['SYMFONY__ENV'] : 'app_prod';

$kernel = new AppKernel($env, false, $_SERVER['HTTP_HOST']);
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);
$request = Request::createFromGlobals();

$kernel->boot();
/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer();

// ATTENTION : 'application_base_url' doit être définie pour la signature de l'url
$response = $container->get('bns_app_media_library.download.download')->downloadAction($request);
$response->prepare($request);
$response->send();
