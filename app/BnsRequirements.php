<?php

/**
 * @author Jérémie Augustin jeremie.augustin@pixel-cookers.com
 */
class BnsRequirements extends RequirementCollection
{
    const REQUIRED_PHP_VERSION = '5.3.18';

    protected $parameters;

    /**
     * @param string $mode should be 'all', 'front', 'reference'
     */
    public function __construct($mode = 'all')
    {
        // php >=5.3.18
        $installedPhpVersion = phpversion();
        $this->addRequirement(
            version_compare($installedPhpVersion, self::REQUIRED_PHP_VERSION, '>='),
            sprintf('PHP version must be at least %s (%s installed)', self::REQUIRED_PHP_VERSION, $installedPhpVersion),
            sprintf('You are running PHP version "<strong>%s</strong>", but Beneylu School needs at least PHP "<strong>%s</strong>" to run.
                Before using Beneylu School, upgrade your PHP installation',
                $installedPhpVersion, self::REQUIRED_PHP_VERSION)
        );

        // php < 5.4
        $this->addRecommendation(
            version_compare($installedPhpVersion, '5.4', '<'),
            sprintf('Votre version de PHP %s doit etre inferieure a PHP 5.4', $installedPhpVersion),
            sprintf('Votre version de PHP %s doit etre inferieure a PHP 5.4', $installedPhpVersion)
        );

        $parameters = array(
            'memory_limit' => 256,
            'post_max_size' => 50,
            'upload_max_filesize' => 20,
        );
        foreach ($parameters as $parameter => $size) {
            $this->addRequirement(
                $this->returnBytes(ini_get($parameter)) >= $size * 1024 * 1024,
                sprintf('le parameter php.ini %s doit etre au moins %sM (actuelement %s)', $parameter, $size, ini_get($parameter)),
                sprintf('vous devez modifier php.ini et changer la valeur %s qui doit etre au moins %sM (actuelement %s)', $parameter, $size, ini_get($parameter))
            );
        }

        $this->addRequirement(
            ini_get('date.timezone') == 'Europe/Paris',
            sprintf("le parameter php.ini date.timezone doit etre 'Europe/Paris' (actuelement %s)", ini_get('date.timezone')),
            sprintf("vous devez modifier php.ini et changer la valeur date.timezone qui doit etre 'Europe/Paris' (actuelement %s)", ini_get('date.timezone'))
        );

        $modules = array(
            'apc' => '3.1.0',
            'curl' => 0,
            'bcmath' => 0,
            'dom' => 0,
            'fileinfo' => 0,
            'gd' => 0,
            'intl' => 0,
            'imap' => 0,
            'mbstring' => 0,
            'mcrypt' => 0,
            'mysql' => 0,
            'openssl' => 0,
            'pdo' => 0,
            'pdo_mysql' => 0,
            'pdo_sqlite' => 0,
            'Phar' => 0,
            'soap' => 0,
            'xml' => 0,
            'zip' => 0,
        );

        foreach ($modules as $module => $requiredVersion) {
            if ($requiredVersion) {
                $installedVersion = phpversion($module)?: 0;
                $this->addRequirement(
                    version_compare($installedVersion, $requiredVersion, '>='),
                    sprintf('Extension PHP %s en version %s est requise (%s installed)', $module, $requiredVersion, $installedVersion),
                    sprintf('Vous devez installer l\'extension PHP %s avec au moins la version %s', $module, $requiredVersion)
                );
            } else {
                $this->addRequirement(
                    extension_loaded($module),
                    sprintf('Extension PHP %s est requise', $module),
                    sprintf('Vous devez installer l\'extension PHP : %s ', $module)
                );
            }
        }

        $this->addRecommendation(
            extension_loaded('posix'),
            'Extension PHP posix est optionnelle',
            'Vous devriez installer l\'extension PHP posix pour avoir la coloration de la console'
        );


        if (!isset($_SERVER['HTTP_HOST'])) {
            // Runnings apps
            $commands = array(
                'git --version' => '1.7.0',
                'svn --version' => '1.6.0',
                'curl --version' => '1.0.0',
                'java -version' => '1.6.0',
            );

            $commandReference = array(
                'redis-server --version' => '2.6.4',
                'rabbitmqctl status | grep -i \\"rabbitmq\\"' => '2.8.7'
            );

            switch (strtolower($mode)) {
                default:
                case 'all':
                case 'reference':
                    $commands = array_merge($commands, $commandReference);
                    break;
                case 'front':

                    break;
            }

            foreach ($commands as $command => $requiredVersion) {
                $installedVersion = $this->getVersion($command);
                $cmd = substr($command, 0, strpos($command, ' '));

                $this->addRequirement(
                    version_compare($installedVersion, $requiredVersion, '>='),
                    sprintf('%s avec au moins la version %s est requis (%s installe)', $cmd, $requiredVersion, $installedVersion),
                    sprintf('Vous devez installer %s avec au moins la version %s', $cmd, $requiredVersion)
                );
            }

            if ('all' == $mode) {
                try {
                    $dsn = sprintf('mysql:host=%s',
                        $this->getParameter('database_host')
                    );
                    $conn = new PDO($dsn, $this->getParameter('app_database_user'), $this->getParameter('app_database_password'));
                    $mysqlVersion = $this->extractVersion($conn->getAttribute(PDO::ATTR_SERVER_VERSION));
                    $erreurMessage = '';
                } catch(\Exception $e) {
                    $mysqlVersion = 0;
                    $erreurMessage = ' : verifier les parametres de connexion dans app/config/parameters_prod.yml, ' . $e->getMessage();
                }
                $this->addRequirement(
                    version_compare($mysqlVersion, '5.1.0', '>='),
                    sprintf('MySQL avec au moins la version %s est requis (%s installe)%s','5.1.0', $mysqlVersion, $erreurMessage),
                    sprintf('Vous devez installer au moins la version %s de MySQL (%s installe)%s','5.1.0', $mysqlVersion, $erreurMessage)
                );
                $this->addRecommendation(
                    version_compare($mysqlVersion, '5.5.0', '>='),
                    sprintf('MySQL avec au moins la version %s est recommande (%s installe)','5.5.0', $mysqlVersion),
                    sprintf('Pour des raisons de performance vous devriez installer au moins la version %s de MySQL (%s installe)%s','5.5.0', $mysqlVersion, $erreurMessage)
                );
            }

            // redis
            if (class_exists('Predis\Client')) {
                $redisDsn = new \Snc\RedisBundle\DependencyInjection\Configuration\RedisDsn($this->getParameter('redis_hosts'));
                try {
                    $redis = new Predis\Client(array(
                        'host' => $redisDsn->getHost(),
                        'port' =>  $redisDsn->getPort(),
                        'password' => $redisDsn->getPassword(),
                        'database' => $redisDsn->getDatabase(),
                    ));
                    $pong = $redis->ping();
                } catch(\Exception $e) {
                    $pong = false;
                }
                $erreurMessage = '';
                if (!$pong) {
                    $erreurMessage = ' : verifier les parametres de connexion dans app/config/parameters_prod.yml';
                }
                $this->addRequirement(
                    $pong,
                    sprintf('Redis Client doit pouvoir se connecter au serveur %s', $redisDsn->getHost()),
                    sprintf('Redis Client doit pouvoir se connecter au serveur %s%s', $redisDsn->getHost(), $erreurMessage)
                );
            }

        } else {
            $apacheVersion = $this->extractVersion(apache_get_version());
            $this->addRequirement(
                version_compare($apacheVersion, '2.2.0', '>='),
                sprintf('Apache doit etre installe avec au moins la version %s (%s installe)','2.2.0', $apacheVersion),
                sprintf('Vous devez installer au moins la version %s d\'Apache (%s installe)','2.2.0', $apacheVersion)
            );


            $apacheModules = apache_get_modules();
            $moduleRequired = array(
                'mod_rewrite',
                'mod_xsendfile'
            );
            foreach ($moduleRequired as $module) {
                $this->addRequirement(
                    in_array($module, $apacheModules),
                    sprintf('Le module apache %s est requis', $module),
                    sprintf('Vous devez installer le module apache %s', $module)
                );
            }
        }
    }

    public function getVersion($exec)
    {
        @include_once __DIR__.'/../vendor/autoload.php';
        if (class_exists('\Symfony\Component\Process\Process')) {
            $process = new \Symfony\Component\Process\Process($exec);
            $process->run();

            $output = $process->getOutput()? : $process->getErrorOutput();
            if ($output) {
                $matches = array();
                if (preg_match('/[0-9]+\.+[0-9\.][0-9]/', $output, $matches)) {

                    return $matches[0];
                }
            }
        } else {
            $output = array();
            $return = null;
            exec($exec, $output, $return);

            if (isset($output[0])) {
                $output = $output[0];
            } else {
                $output = '';
            }
        }

        if ($output) {
            return $this->extractVersion($output);
        }

        return false;
    }

    protected function getParameter($key)
    {
        if (null === $this->parameters) {
            $this->parameters = array();
            if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                $this->parameters = \Symfony\Component\Yaml\Yaml::parse(__DIR__ . '/config/parameters_prod.yml');
            }
        }

        if (isset($this->parameters['parameters'])) {
            if (isset($this->parameters['parameters'][$key])) {
                return $this->parameters['parameters'][$key];
            }
        }

        return false;
    }

    protected function extractVersion($string)
    {
        $matches = array();
        if (preg_match('/[0-9]+[0-9\.]*[0-9]/', $string, $matches)) {

            return $matches[0];
        }

        return false;
    }

    protected function returnBytes($size_str)
    {
        switch (substr ($size_str, -1))
        {
            case 'M': case 'm': return (int)$size_str * 1048576;
            case 'K': case 'k': return (int)$size_str * 1024;
            case 'G': case 'g': return (int)$size_str * 1073741824;
            default: return $size_str;
        }
    }
}
