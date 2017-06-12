<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

/**
 * script executé à chaque déployement
 */
$webRootDir = __DIR__ . '/../../..';
$dir = realpath($webRootDir . '/bin/');

// Mise en 777 des binaires WKHTML
exec('chmod 755 -R ' . $dir);
// Création du cache pour le host www.beneyluschool.net
exec('BNS_HOST=www.beneyluschool.net /usr/bin/php ' . $webRootDir . '/app/console cache:clear --env="app_prod" 2>&1');

echo 'No extra task needed to run';

// success
exit(0);