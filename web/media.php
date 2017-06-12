<?php

require_once('../vendor/symfony/symfony/src/Symfony/Component/Yaml/Yaml.php');
require_once('../vendor/symfony/symfony/src/Symfony/Component/Yaml/Parser.php');
require_once('../vendor/symfony/symfony/src/Symfony/Component/Yaml/Inline.php');
require_once('../vendor/symfony/symfony/src/Symfony/Component/Yaml/Unescaper.php');


function denied()
{
    die('erreur');
}

function createThumb($size, $originalPath, $sizePath)
{
    require '../vendor/autoload.php';
    $params = array(
        'thumbnail'				=> array(60,60),
        'small'					=> array(180,180),
        'favorite'				=> array(300,140),
        'medium'				=> array(600,400),
        'large'					=> array(1200,800),
        'banner_minisite_front'	=> array(1150, 200),
        'banner_minisite_back'	=> array(1150, 200)
    );

    $imagine = new \Imagine\Gd\Imagine();
    $infos_image = @getimagesize($originalPath);
    $imagePath = $originalPath;


    $palette = new Imagine\Image\Palette\RGB();
    $white = $palette->color(array(255, 255, 255));

    $largeur = $infos_image[0]; // largeur de l'image
    $hauteur = $infos_image[1]; // hauteur de l'image
    $ratio = $largeur / $hauteur;
    //Si l'image est strictement moins large ET moins haute que le thumbnail demandé alors
    //On crée un thumbnail blanc avec l'image au milieu non redimmensionnée
    if($params[$size][0] > $largeur && $params[$size][1] > $hauteur)
    {
        if($size == 'medium')
        {
            //Medium utilisé pour les insertions on ne redimensionne pas les petits images
            copy($imagePath,$sizePath);
            return true;
        }


        //Remplir de blanc le thumbnail
        $squared_size = new \Imagine\Image\Box($params[$size][0],$params[$size][1]);
        $color = $white;

        //Création du rendu final
        $final = $imagine->create($squared_size, $color);

        //On ouvre l'image

        $image = $imagine->open($imagePath);


        //On la colle au milieu du rendu final
        $x = ($params[$size][0] / 2) - ($largeur / 2);
        $y = ($params[$size][1] / 2) - ($hauteur / 2);

        try {
            $final->paste($image, new \Imagine\Image\Point($x, $y));

            $final->save($sizePath);

        } catch(Exception $exception) {

        }
    }
    //Si l'image est strictement moins large OU moins haute que le thumbnail demandé alors
    //On doit redimmensionner en pourcentage pour que l'image rentre correctement dans le bandeau
    else if($params[$size][0] > $largeur || $params[$size][1] > $hauteur)
    {
        //Pourcentage de resize
        $percent_resize = 100;
        if($params[$size][0] < $largeur)
        {
            $percent_resize = $params[$size][0] / $largeur;
        }
        else
        {
            $percent_resize = $params[$size][1] / $hauteur;
        }

        //Remplir de blanc
        $squared_size = new \Imagine\Image\Box($params[$size][0],$params[$size][1]);
        $color = $white;

        //Création du rendu final
        $final = $imagine->create($squared_size, $color);

        //On ouvre l'image

        $image = $imagine->open($imagePath);

        $mode = \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        //Taille de l'image redimmensionnée proportionnellement
        $resized_image_size = new \Imagine\Image\Box($largeur*$percent_resize, $hauteur*$percent_resize);

        //On colle l'image au milieu
        $x = ($params[$size][0] / 2) - ($largeur*$percent_resize / 2);
        $y = ($params[$size][1] / 2) - ($hauteur*$percent_resize / 2);

        // Corrige un bug image outofbound problème de précision
        if ($x < 0) {
            $x = 0;
        }
        if ($y < 0) {
            $y = 0;
        }

        // Coller l'image redimmensionnée
        $final->paste($image->thumbnail($resized_image_size, $mode), new \Imagine\Image\Point($x, $y));
        // Création des thumbs en local (repertoire temporaire)
        $final->save($sizePath);

    }
    //Si l'image est strictement plus large ET plus haute que le thumbnail demandé alors
    //On fait un resize et le rendu peut dépasser
    else
    {
        $boxWidth = $params[$size][0];
        $boxHeight = $params[$size][1];
        $ratio = $largeur / $hauteur;
        if($size == "medium" && $ratio < 1)
        {
            $tmp = $boxHeight;
            $boxHeight = $boxWidth;
            $boxWidth = $tmp;
        }

        $squared_size = new \Imagine\Image\Box($boxWidth,$boxHeight);
        $mode = \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;
        $path = $originalPath;
        //Création des thumbs en local (repertoire temporaire)

        $dir = substr($sizePath,0,strrpos($sizePath,'/'));
        if(!is_dir($dir))
        {
            mkdir($dir . '/');
        }
        $imagine->open($path)->thumbnail($squared_size, $mode)->resize($squared_size)->save($sizePath);
    }
}



if(isset($_GET['env']))
{
    $env = isset($_GET['env']) ? $_GET['env'] : 'prod';
}

$conf = \Symfony\Component\Yaml\Yaml::parse(__DIR__ . '/../app/config/parameters_' . $env . '.yml');

$secretKey = $conf['parameters']['symfony_secret'];
$path = isset($conf['parameters']['resource_files_dir']) ? $conf['parameters']['resource_files_dir'] : __DIR__ . '/../app/data/resources/';

//Recupération des informations
$time = $_GET['time'];
$uid = $_GET['uid'];
$cat = urldecode($_GET['cat']);
$id = $_GET['id'];
$key = $_GET['key'];
$mimeType = $_GET['mime_type'];

$filename = urldecode(htmlspecialchars($_GET["filename"]));

if(time() - $time > 3600 || md5($uid . $cat . $id . $time . $secretKey) != $key)
{
    denied();
}

$size = isset($_GET['size']) ? $_GET['size'] : 'original';

$filePath = "$path$cat";
$originalPath = "$filePath$filename";

if($size != 'original')
{
    $sizePath = "$filePath$size$filename";
}else{
    $sizePath = $originalPath;
}

if(!is_file($originalPath))
{
    denied();
}

if(!is_file($sizePath) && $size != 'original')
{
    createThumb($size, $originalPath, $sizePath);
}

$shownMimeType = $mimeType;
if(!is_file($sizePath))
{
    $sizePath = utf8_encode($sizePath);
    if(!is_file($sizePath))
    {
        $sizePath =  $originalPath;
    }
}

header("X-Sendfile: $sizePath");
header("Content-Type: $shownMimeType");
header("Content-Disposition: attachment; filename=\"$filename\"");
exit;
?>