<?php

use BNS\App\MediaLibraryBundle\Manager\MediaThumbCreator;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\ClassLoader\ApcClassLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

if(isset($_SERVER['SYMFONY__ENV']))
{
    $env = $_SERVER['SYMFONY__ENV'];
}else{
    $env = 'app_prod';
}

$kernel = new AppKernel($env, false, $_SERVER['HTTP_HOST']);
$kernel->loadClassCache();
//$kernel = new AppCache($kernel);
$request = Request::createFromGlobals();

$kernel->boot();
/** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
$container = $kernel->getContainer();

// ATTENTION : l'application_base_url doit être définie pour la signature de l'url

$mediaDownloadValidator = $container->get('bns.media.download_validator');
if (!$mediaDownloadValidator->validateUrl($request)) {
    $response = new Response('', Codes::HTTP_NOT_FOUND);
    $response->send();
    exit();
}

$path = $request->get('pattern');
$size = $request->get('size');
$filename = $request->get('filename');
$mimeType = $request->get('mime_type');
$mediaThumbCreator = $container->get('bns.media.thumb_creator');
$localAdapter = $container->get('bns.local.adapter');

$fullPath = $path;
$needThumb = false;
if (in_array($size, array_keys(MediaThumbCreator::$thumbnails))) {
    if ($localAdapter->isDirectory($fullPath . $size)) {
        $fullPath .= '_' .$size;
    } else {
        $fullPath .= $size;
    }
    $needThumb = true;
} else if (null !== $size && 'original' !== $size) {
    // invalid size parameter
    $response = new Response('', Codes::HTTP_NOT_FOUND);
    $response->send();
    exit();
}

if (!$needThumb) {
    $fullPath .= $filename;
}
$localAdapter = $container->get('bns.local.adapter');
if (!$localAdapter->exists($fullPath)) {
    if (!$needThumb && false !== $url = $container->get('bns.media.download_manager')->generateSwiftTemporaryUrl($fullPath)) {
        $response = new \Symfony\Component\HttpFoundation\RedirectResponse($url);
        $response->send();
        exit();
    }
    if (!$needThumb || !$mediaThumbCreator->createLocalThumbForKey($path . $filename, $fullPath, $size)) {
        $response = new Response('', Codes::HTTP_NOT_FOUND);
        $response->send();
        exit();
    }
}

$fullPath =  \Gaufrette\Util\Path::normalize($container->getParameter('resource_files_dir') .  '/' . $fullPath);

header("X-Sendfile: $fullPath");
header("Content-Type: $mimeType");
header("Content-Disposition: attachment; filename=\"$filename\"");
