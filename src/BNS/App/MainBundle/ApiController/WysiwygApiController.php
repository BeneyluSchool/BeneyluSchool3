<?php

namespace BNS\App\MainBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MessagingBundle\Form\Type\ConversationType;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Form\Type\AnswerType;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Stfalcon\Bundle\TinymceBundle\Helper\LocaleHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Controller\BaseApiController;

/**
 * Class WysiwygApiController
 *
 * @package BNS\App\MainBundle\ApiController
 */
class WysiwygApiController extends BaseApiController
{

    /**
     * @ApiDoc(
     *  section="Wysiwyg - Configuration",
     *  resource=true,
     *  description="Wysiwyg - Récupération de la configuration",
     *  statusCodes = {
     *      200 = "Ok"
     *  }
     * )
     * @Rest\Get("/configuration")
     * @Rest\View()
     *
     * @param Request $request
     * @return Response
     */
    public function getConfigurationAction(Request $request)
    {
        $assets = $this->get('templating.helper.assets');
        $metaConfiguration = $this->getParameter('stfalcon_tinymce.config');

        // get base config from theme
        $baseConfiguration = isset($metaConfiguration['theme']['simple'])
            ? $metaConfiguration['theme']['simple']
            : []
        ;

        // copy base settings
        $baseConfiguration['base_url'] = $metaConfiguration['base_url'];

        // load external plugins
        if (isset($metaConfiguration['external_plugins'])) {
            foreach ($metaConfiguration['external_plugins'] as $plugin => $conf) {
                $baseConfiguration['external_plugins'][$plugin] = $conf['url'];
            }
        }

        // add media_library plugin only if access
        if ($this->get('bns.right_manager')->hasRightSomeWhere('MEDIA_LIBRARY_ACCESS')) {
            $baseConfiguration['external_plugins']['media_library'] = $assets->getUrl('medias/js/tinymce/media_library.js');
        }

        // add plugin buttons from session settings
        $session = $request->getSession();
        if ($session->has('tiny_mce_plugins')) {
            $plugins = $session->get('tiny_mce_plugins');
            foreach ($plugins as $plugin) {
                $baseConfiguration['toolbar1'] .= ' ' . $plugin;
            }
        }

        // force changemode plugin conf if child
        if (!$request->cookies->has('tinymce_mode', array('path' => '/'))) {
            $isChild = $this->get('bns.right_manager')->getUserManager()->isChild();
            $value = $isChild ? 'simple' : 'advanced';
            setCookie('tinymce_mode', $value, null, '/');
        }

        $language = LocaleHelper::getLanguage($request->getLocale());
        $langDirectory = __DIR__ .'/../../../../../vendor/stfalcon/tinymce-bundle/Stfalcon/Bundle/TinymceBundle/Resources/public/vendor/tinymce/langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory . $language . '.js')) {
            // specific locale not found, try with base language: 'es_AR' -> 'es'
            $language = explode('_', $language)[0];
            if (!file_exists($langDirectory . $language . '.js')) {
                $language = false;
            }
        }


        $configuration = [
            'editor_script' => $assets->getUrl('bower_components/tinymce-dist/tinymce.js'),
        ];
        if (false !== $language) {
            $configuration['language_url'] = $assets->getUrl('bundles/stfalcontinymce/vendor/tinymce/langs/'.$language.'.js');
        }

        return array_merge_recursive($baseConfiguration, $configuration);
    }

}
