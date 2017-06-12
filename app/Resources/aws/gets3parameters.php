<?php
error_reporting(E_ALL);
ini_set('display_errors','On');

/**
 * @Author jaugustin
 * Script utilisé pour récupérer le fichier parameters_prod.yml depuis un bucket S3
 * utilise le SDK AmazonS3 et la lib Gaufrette ainsi que l'autoload du projet
 *
 * le script prend 4 parameter en entrée  :
 * $ gets3parameters.php my_aws_key my_aws_scerete_key my_bucket my_sf2_root_dir
 */
require_once __DIR__ .'/../../../vendor/autoload.php';


$s3_key    = $argv[1];
$s3_secret = $argv[2];
$s3_bucket = $argv[3];
$s2RootDir = $argv[4];

$adapter = new Gaufrette\Adapter\Local($s2RootDir . '/app/config');
$filesystem = new Gaufrette\Filesystem($adapter);

$amazon = new AmazonS3(array('key' => $s3_key, 'secret' => $s3_secret, 'certificate_authority' => true));
$s3adapter = new Gaufrette\Adapter\AmazonS3($amazon, $s3_bucket);
$s3filesystem = new Gaufrette\Filesystem($s3adapter);

$filesystem->write('parameters_prod.yml', $s3filesystem->read('parameters_prod.yml'), true);


if ($filesystem->has('parameters_prod.yml')) {
	exit(0);
}

exit(1);
