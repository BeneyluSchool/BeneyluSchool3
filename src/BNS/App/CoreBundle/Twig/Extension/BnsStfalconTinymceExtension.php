<?php

namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Access\BNSAccess;
use Stfalcon\Bundle\TinymceBundle\Twig\Extension\StfalconTinymceExtension;
use Stfalcon\Bundle\TinymceBundle\Helper\LocaleHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class BnsStfalconTinymceExtension extends StfalconTinymceExtension{


    public function getFunctions()
    {
        return array_merge(parent::getFunctions(),array(
            'tinymce_init_ajax' => new \Twig_Function_Method($this, 'tinymceInit', array('is_safe' => array('html')))
        ));
    }

    /**
     *
     * @param array $options
     * @return string
     */
    public function tinymceInit($options = array()){

        @trigger_error(sprintf('Calling tinymce_init() in template is no longer required'), E_USER_DEPRECATED);

        return '';

        $config = $this->getParameter('stfalcon_tinymce.config');
        $config = array_merge_recursive($config, $options);

        $this->baseUrl = (!isset($config['base_url']) ? null : $config['base_url']);
        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('templating.helper.assets');

        // Get path to tinymce script for the jQuery version of the editor
        if ($config['tinymce_jquery']) {
            $config['jquery_script_url'] = $assets->getUrl(
                $this->baseUrl . 'bundles/stfalcontinymce/vendor/tinymce/tinymce.jquery.min.js'
            );
        }

        // Get local button's image
        foreach ($config['tinymce_buttons'] as &$customButton) {
            if ($customButton['image']) {
                $customButton['image'] = $this->getAssetsUrl($customButton['image']);
            } else {
                unset($customButton['image']);
            }

            if ($customButton['icon']) {
                $customButton['icon'] = $this->getAssetsUrl($customButton['icon']);
            } else {
                unset($customButton['icon']);
            }
        }

        /**
         * Ici surcharge des composants si offre
         */

        /* @var Session $session */
        $session = BNSAccess::getSession();
        if($session->has('tiny_mce_plugins'))
        {
            $plugins = $session->get('tiny_mce_plugins');
            foreach($plugins as $plugin)
            {
                $config['theme']['simple']['toolbar1'] .= ' ' . $plugin;
            }
        }


        // Update URL to external plugins
        foreach ($config['external_plugins'] as &$extPlugin) {
            $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
        }

        // If the language is not set in the config...
        if (!isset($config['language']) || empty($config['language'])) {
            // get it from the request
            $config['language'] = $this->getService('request')->getLocale();
        }

        $config['language'] = LocaleHelper::getLanguage($config['language']);

        $langDirectory = __DIR__ .'/../../../../../../vendor/stfalcon/tinymce-bundle/Stfalcon/Bundle/TinymceBundle/Resources/public/vendor/tinymce/langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory . $config['language'] . '.js')) {
            // specific locale not found, try with base language: 'es_AR' -> 'es'
            $config['language'] = explode('_', $config['language'])[0];
            if (!file_exists($langDirectory . $config['language'] . '.js')) {
                unset($config['language']);
            }
        }

        if (isset($config['language']) && $config['language']) {
            // TinyMCE does not allow to set different languages to each instance
            foreach ($config['theme'] as $themeName => $themeOptions) {
                $config['theme'][$themeName]['language'] = $config['language'];
            }
        }

        if (isset($config['theme']) && $config['theme'])
        {
            // Parse the content_css of each theme so we can use 'asset[path/to/asset]' in there
            foreach ($config['theme'] as $themeName => $themeOptions) {
                if(isset($themeOptions['content_css']))
                {
                    // As there may be multiple CSS Files specified we need to parse each of them individually
                    $cssFiles = explode(',', $themeOptions['content_css']);

                    foreach($cssFiles as $idx => $file)
                    {
                        $cssFiles[$idx] = $this->getAssetsUrl(trim($file)); // we trim to be sure we get the file without spaces.
                    }

                    // After parsing we add them together again.
                    $config['theme'][$themeName]['content_css'] = implode(',', $cssFiles);
                }
            }
        }

        if(!$this->container->get('request')->cookies->has('tinymce_mode',array('path' => '/'))){
            $isChild = $this->container->get('bns.right_manager')->getUserManager()->isChild();
            $value = $isChild ? 'simple' : 'advanced';
            setCookie('tinymce_mode', $value, null, '/');
        }

        $hasMedialibraryAcess = $this->container->get('bns.right_manager')->hasRight('MEDIA_LIBRARY_ACCESS');

        return $this->getService('templating')->render('BNSAppCoreBundle:Script:init.html.twig', array(
            'tinymce_config' => preg_replace(
                '/"file_browser_callback":"([^"]+)"\s*/', 'file_browser_callback:$1',
                json_encode($config)
            ),
            'include_jquery' => $config['include_jquery'],
            'tinymce_jquery' => $config['tinymce_jquery'],
            'base_url'       => $this->baseUrl,
            'hasNoMediaLibraryAccess' => !$hasMedialibraryAcess
        ));
    }


}
