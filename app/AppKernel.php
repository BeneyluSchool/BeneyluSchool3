<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Request;

class AppKernel extends Kernel
{
    /**
     * @var string
     */
    private $host;

    public function __construct($environment, $debug, $host = null)
    {
        // disabled Forwarded header (our LB use X-Forwarded-For)
        // prevent bug with LB and proxy cache
        Request::setTrustedHeaderName(Request::HEADER_FORWARDED, null);

        // Settage de la locale pour éviter souci de slug sur serveur
        setlocale(LC_ALL, 'fr_FR.UTF-8');
        // Modification de la valeur de la séparation décimal - fr_FR: ','; en_US: '.'
        setlocale(LC_NUMERIC, 'en_US.UTF-8');

        parent::__construct($environment, $debug);

        if ($host != null) {
            $this->host = $host;
        } else {
            $this->host = getenv('BNS_HOST') ?: null;
        }
    }

    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Sensio\Bundle\BuzzBundle\SensioBuzzBundle(),
            //new JMS\AopBundle\JMSAopBundle(),
            //new JMS\SecurityExtraBundle\JMSSecurityExtraBundle(),
            new Propel\PropelBundle\PropelBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new FOS\RestBundle\FOSRestBundle(),
            new Knp\Bundle\MenuBundle\KnpMenuBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle(),
            new Snc\RedisBundle\SncRedisBundle(),
            new WhiteOctober\PagerfantaBundle\WhiteOctoberPagerfantaBundle(),
            new JMS\TranslationBundle\JMSTranslationBundle(),
            new Misd\PhoneNumberBundle\MisdPhoneNumberBundle(),
            new Nelmio\CorsBundle\NelmioCorsBundle(),
        );

        // Bns Common Bundles
        $bundles[] = new BNS\CommonBundle\BNSCommonBundle();

        // App Bundles
        if (strstr($this->getEnvironment(), '_', true) == 'app') {
            $bundles[] = new Exercise\HTMLPurifierBundle\ExerciseHTMLPurifierBundle();
            $bundles[] = new HWI\Bundle\OAuthBundle\HWIOAuthBundle();
            $bundles[] = new Knp\Bundle\GaufretteBundle\KnpGaufretteBundle();
            $bundles[] = new Knp\Bundle\SnappyBundle\KnpSnappyBundle();
            $bundles[] = new Stfalcon\Bundle\TinymceBundle\StfalconTinymceBundle();
            $bundles[] = new Vich\GeographicalBundle\VichGeographicalBundle();
            $bundles[] = new OpenSky\Bundle\RuntimeConfigBundle\OpenSkyRuntimeConfigBundle();
            $bundles[] = new OldSound\RabbitMqBundle\OldSoundRabbitMqBundle();
            $bundles[] = new JMS\Payment\CoreBundle\JMSPaymentCoreBundle();
            $bundles[] = new Ruudk\Payment\StripeBundle\RuudkPaymentStripeBundle();
            $bundles[] = new Bazinga\Bundle\HateoasBundle\BazingaHateoasBundle();
            $bundles[] = new WhiteOctober\TCPDFBundle\WhiteOctoberTCPDFBundle();
            $bundles[] = new Fkr\SimplePieBundle\FkrSimplePieBundle();
            $bundles[] = new Dubture\FFmpegBundle\DubtureFFmpegBundle();
            $bundles[] = new SunCat\MobileDetectBundle\MobileDetectBundle();
            $bundles[] = new Qandidate\Bundle\ToggleBundle\QandidateToggleBundle();
            $bundles[] = new Lopi\Bundle\PusherBundle\LopiPusherBundle();
            // BNS
            $bundles[] = new BNS\App\AdminBundle\BNSAppAdminBundle();
            $bundles[] = new BNS\App\CommandBundle\BNSAppCommandBundle();
            $bundles[] = new BNS\App\CoreBundle\BNSAppCoreBundle();
            $bundles[] = new BNS\App\CorrectionBundle\BNSAppCorrectionBundle();
            $bundles[] = new BNS\App\FixtureBundle\BNSAppFixtureBundle();
            $bundles[] = new BNS\App\ForumBundle\BNSAppForumBundle();
            $bundles[] = new BNS\App\InstallBundle\BNSAppInstallBundle();
            $bundles[] = new BNS\App\MainBundle\BNSAppMainBundle();
            $bundles[] = new BNS\App\MigrationBundle\BNSAppMigrationBundle();
            $bundles[] = new BNS\App\NotificationBundle\BNSAppNotificationBundle();
            $bundles[] = new BNS\App\TeamBundle\BNSAppTeamBundle();
            $bundles[] = new BNS\App\ClassroomBundle\BNSAppClassroomBundle();
            $bundles[] = new BNS\App\ProfileBundle\BNSAppProfileBundle();
            $bundles[] = new BNS\App\SchoolBundle\BNSAppSchoolBundle();
            $bundles[] = new BNS\App\MailerBundle\BNSAppMailerBundle();
            $bundles[] = new BNS\App\HomeworkBundle\BNSAppHomeworkBundle();
            $bundles[] = new BNS\App\GroupBundle\BNSAppGroupBundle();
            $bundles[] = new BNS\App\HelloWorldBundle\BNSAppHelloWorldBundle();
            //$bundles[] = new BNS\App\ResourceBundle\BNSAppResourceBundle();
            $bundles[] = new BNS\App\GPSBundle\BNSAppGPSBundle();
            $bundles[] = new BNS\App\CalendarBundle\BNSAppCalendarBundle();
            $bundles[] = new BNS\App\MessagingBundle\BNSAppMessagingBundle();
            $bundles[] = new BNS\App\BlogBundle\BNSAppBlogBundle();
            $bundles[] = new BNS\App\LiaisonBookBundle\BNSAppLiaisonBookBundle();
            $bundles[] = new BNS\App\AutosaveBundle\BNSAppAutosaveBundle();
            $bundles[] = new BNS\App\PropelBundle\BNSAppPropelBundle();
            $bundles[] = new BNS\App\CommentBundle\BNSAppCommentBundle();
            $bundles[] = new BNS\App\ModalBundle\BNSAppModalBundle();
            $bundles[] = new BNS\App\MiniSiteBundle\BNSAppMiniSiteBundle();
            $bundles[] = new BNS\App\TemplateBundle\BNSAppTemplateBundle();
            $bundles[] = new BNS\App\RegistrationBundle\BNSAppRegistrationBundle();
            $bundles[] = new BNS\App\DirectoryBundle\BNSAppDirectoryBundle();
            $bundles[] = new BNS\App\UserBundle\BNSAppUserBundle();
            $bundles[] = new BNS\App\GuideTourBundle\BNSAppGuideTourBundle();
            $bundles[] = new BNS\App\GoogleBundle\BNSAppGoogleBundle();
            $bundles[] = new BNS\App\BoardBundle\BNSAppBoardBundle();
            $bundles[] = new BNS\App\ScolomBundle\BNSAppScolomBundle();
            $bundles[] = new BNS\App\StatisticsBundle\BNSAppStatisticsBundle();
            $bundles[] = new BNS\App\NoteBookBundle\BNSAppNoteBookBundle();
            $bundles[] = new BNS\App\StoreBundle\BNSAppStoreBundle();
            $bundles[] = new BNS\App\PupilMonitoringBundle\BNSAppPupilMonitoringBundle();
            $bundles[] = new BNS\App\ReservationBundle\BNSAppReservationBundle();
            $bundles[] = new BNS\App\WorkshopBundle\BNSAppWorkshopBundle();
            $bundles[] = new BNS\App\YerbookBundle\BNSAppYerbookBundle();
            $bundles[] = new BNS\App\InfoBundle\BNSAppInfoBundle();
            $bundles[] = new BNS\App\PaasBundle\BNSAppPaasBundle();
            $bundles[] = new BNS\App\LunchBundle\BNSAppLunchBundle();
            $bundles[] = new BNS\App\PortalBundle\BNSAppPortalBundle();
            $bundles[] = new BNS\App\SearchBundle\BNSAppSearchBundle();
            $bundles[] = new BNS\App\MediaLibraryBundle\BNSAppMediaLibraryBundle();
            $bundles[] = new BNS\App\UserDirectoryBundle\BNSAppUserDirectoryBundle();
            $bundles[] = new BNS\App\RealtimeBundle\BNSAppRealtimeBundle();
            $bundles[] = new BNS\App\SpotBundle\BNSAppSpotBundle();
            $bundles[] = new BNS\App\EventBundle\BNSAppEventBundle();
            $bundles[] = new BNS\App\TranslationBundle\TranslationBundle();
            $bundles[] = new BNS\App\CampaignBundle\BNSAppCampaignBundle();
            $bundles[] = new BNS\App\StarterKitBundle\BNSAppStarterKitBundle();
            $bundles[] = new BNS\App\AchievementBundle\BNSAppAchievementBundle();
            $bundles[] = new BNS\App\LsuBundle\BNSAppLsuBundle();
            $bundles[] = new BNS\App\CompetitionBundle\BNSAppCompetitionBundle();
            $bundles[] = new BNS\App\AccountBundle\BNSAppAccountBundle();
            $bundles[] = new BNS\App\ChatBundle\BNSAppChatBundle();
        }

        // Central Bundles
        if (strstr($this->getEnvironment(), '_', true) == 'auth') {
            $bundles[] = new FOS\OAuthServerBundle\FOSOAuthServerBundle();
            $bundles[] = new FOS\UserBundle\FOSUserBundle();
            // BNS
            $bundles[] = new BNS\Central\CoreBundle\BNSCentralCoreBundle();
            $bundles[] = new BNS\Central\SecurityBundle\BNSCentralSecurityBundle();
            $bundles[] = new BNS\Central\InitBundle\BNSCentralInitBundle();
            $bundles[] = new BNS\Central\AuthenticationBundle\BNSCentralAuthenticationBundle();
        }

        if (in_array(strstr($this->getEnvironment(), '_'), array('_dev', '_test'))) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Elao\WebProfilerExtraBundle\WebProfilerExtraBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
            $bundles[] = new Nelmio\ApiDocBundle\NelmioApiDocBundle();
        }

        if ($this->isLexikActivated()){
//            $bundles[] = new Lexik\Bundle\TranslationBundle\LexikTranslationBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config_' . $this->getEnvironment() . '.yml');

        if ($this->host != null) {
            // Security process
            if (!preg_match('/[a-zA-Z0-9.-]+/', $this->host, $host)) {
                die;
            }
            $configFile = __DIR__ . '/config/domains/' . $this->getEnvironment() . '/' . $host[0] . '.yml';
            if (is_file($configFile)) {
                $loader->load($configFile);
            }
        }
        if ($this->isLexikActivated()) {
            $lexikFile = __DIR__ . '/config/lexik.yml';
            if (is_file($lexikFile)) {
                $loader->load($lexikFile);
            }
        }
    }

    /**
     * Surcharge pour établir la différence entre les différents domaines
     * @return string
     */
    public function getCacheDir()
    {
        if ($this->host != null) {
            $cacheDir = $this->rootDir . '/cache/' . $this->host . '/' . $this->environment;
        } else {
            $cacheDir = $this->rootDir . '/cache/' . $this->environment;
        }

        return $cacheDir;
    }

    public function isLexikActivated()
    {
        return 'true' === getenv('SYMFONY__INCLUDE__LEXIK');
    }
}
