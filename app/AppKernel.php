<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
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
            new JMS\AopBundle\JMSAopBundle(),
            new JMS\SecurityExtraBundle\JMSSecurityExtraBundle(),
            new Propel\PropelBundle\PropelBundle(),
            new FOS\JsRoutingBundle\FOSJsRoutingBundle(),
            new Knp\Bundle\MenuBundle\KnpMenuBundle(),
            new JMS\SerializerBundle\JMSSerializerBundle($this),
            new OldSound\RabbitMqBundle\OldSoundRabbitMqBundle(),
            new Snc\RedisBundle\SncRedisBundle(),
        );

        // App Bundles
        if (in_array($this->getEnvironment(), array('app_dev', 'app_test', 'app_prod'))) {
            $bundles[] = new HWI\Bundle\OAuthBundle\HWIOAuthBundle();
            $bundles[] = new Stfalcon\Bundle\TinymceBundle\StfalconTinymceBundle();
            $bundles[] = new Vich\GeographicalBundle\VichGeographicalBundle();
            $bundles[] = new Knp\Bundle\GaufretteBundle\KnpGaufretteBundle();
			$bundles[] = new Knp\Bundle\SnappyBundle\KnpSnappyBundle();
            $bundles[] = new BNS\App\CoreBundle\BNSAppCoreBundle();
            $bundles[] = new BNS\App\AdminBundle\BNSAppAdminBundle();
            $bundles[] = new BNS\App\MainBundle\BNSAppMainBundle();
            $bundles[] = new BNS\App\FixtureBundle\BNSAppFixtureBundle();
            $bundles[] = new BNS\App\NotificationBundle\BNSAppNotificationBundle();
            $bundles[] = new BNS\App\TeamBundle\BNSAppTeamBundle();
            $bundles[] = new BNS\App\ClassroomBundle\BNSAppClassroomBundle();
            $bundles[] = new BNS\App\ProfileBundle\BNSAppProfileBundle();
            $bundles[] = new BNS\App\SchoolBundle\BNSAppSchoolBundle();
            $bundles[] = new BNS\App\MailerBundle\BNSAppMailerBundle();
            $bundles[] = new BNS\App\HomeworkBundle\BNSAppHomeworkBundle();
            $bundles[] = new BNS\App\GroupBundle\BNSAppGroupBundle();
            $bundles[] = new BNS\App\HelloWorldBundle\BNSAppHelloWorldBundle();
            $bundles[] = new BNS\App\ResourceBundle\BNSAppResourceBundle();
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
        }

        if (in_array($this->getEnvironment(), array('app_dev', 'app_test'))) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
            $bundles[] = new WhiteOctober\PagerfantaBundle\WhiteOctoberPagerfantaBundle();
            $bundles[] = new Behat\BehatBundle\BehatBundle();
        }

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
	{
        $loader->load(__DIR__ . '/config/config_' . $this->getEnvironment() . '.yml');
    }
}