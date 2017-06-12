<?php

require_once dirname(__FILE__).'/SymfonyRequirements.php';
require_once dirname(__FILE__).'/BnsRequirements.php';

$symfonyRequirements = new SymfonyRequirements();

$iniPath = $symfonyRequirements->getPhpIniConfigPath();

echo "********************************\n";
echo "*                              *\n";
echo "*  Symfony requirements check  *\n";
echo "*                              *\n";
echo "********************************\n\n";

echo $iniPath ? sprintf("* Configuration file used by PHP: %s\n\n", $iniPath) : "* WARNING: No configuration file (php.ini) used by PHP!\n\n";

echo "** ATTENTION **\n";
echo "*  The PHP CLI can use a different php.ini file\n";
echo "*  than the one used with your web server.\n";
if ('\\' == DIRECTORY_SEPARATOR) {
    echo "*  (especially on the Windows platform)\n";
}

echo_title('Mandatory requirements');

$checkPassed = true;
foreach ($symfonyRequirements->getRequirements() as $req) {
    /** @var $req Requirement */
    echo_requirement($req);
    if (!$req->isFulfilled()) {
        $checkPassed = false;
    }
}

echo_title('Optional recommendations');

foreach ($symfonyRequirements->getRecommendations() as $req) {
    echo_requirement($req);
}

echo "\n\n";
echo "********************************\n";
echo "*                              *\n";
echo "*  Beneylu School pre-requis   *\n";
echo "*                              *\n";
echo "********************************\n\n";

$mode = 'all';
if (isset($argv) && isset($argv[1])) {
    $mode = $argv[1];
}

$bnsRequirements = new BnsRequirements($mode);

echo_title('Pre-requis indispensables');

foreach ($bnsRequirements->getRequirements() as $req) {
    /** @var $req Requirement */
    echo_requirement($req);
    if (!$req->isFulfilled()) {
        $checkPassed = false;
    }
}

echo_title('Pre-requis optionnel');

foreach ($bnsRequirements->getRecommendations() as $req) {
    echo_requirement($req);
}

if ($checkPassed) {
    echo_title('Bravo !!! tous les pre-requis sont passe');
    if (!isset($_SERVER['HTTP_HOST'])) {
        echo "* vous pouvez maintenant tester depuis votre navigateur qui la configuration apache est correct http://localhost/check.php";
    }
} else {
    echo_title('!!! Vous avez des erreurs de configuration, verifiez les points requis marques ERREUR');
}

exit($checkPassed ? 0 : 1);

/**
 * Prints a Requirement instance
 */
function echo_requirement(Requirement $requirement)
{
    $result = $requirement->isFulfilled() ? 'OK' : ($requirement->isOptional() ? 'ATTENTION' : 'ERREUR');
    echo ' ' . str_pad($result, 12);
    echo $requirement->getTestMessage() . "\n";

    if (!$requirement->isFulfilled()) {
        echo sprintf("                %s\n\n", $requirement->getHelpText());
    }
}

function echo_title($title)
{
    echo "\n** $title **\n\n";
}

