<?php

namespace BNS\App\CoreBundle\Twig\Extension;

use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig_Extension;
use Twig_Function_Method;

class AnalyticsExtension extends Twig_Extension
{

    protected $marioKey;
    /** @var  BNSRightManager $rightManager */
    protected $rightManager;

    /** @var  Container $container */
    protected $container;

    protected $actions = array(
        'LOGGED_IN_USER'           => 'Logged in User',
        'LOGGED_OUT_USER'          => 'Logged out User',
        'REGISTERED_USER'          => 'Registered User',
        'DEACTIVATED_USER'         => 'Deactivated User',
        'REACTIVATED_USER'         => 'Reactivated User',
        'ARCHIVED_USER'            => 'Archived User',
        'REGISTERED_CLASSROOM'     => 'Registered Classroom',
        'DESACTIVATED_CLASSROOM'   => 'Desactivated Classroom',
        'REACTIVATED_CLASSROOM'    => 'Reactivated Classroom',
        'ARCHIVED_CLASSROOM'       => 'Archived Classroom',
        'CONFIRMED_CLASSROOM'      => 'Confirmed Classroom',
        'REGISTERED_ACCOUNT'       => 'Registered Account',
        'DESACTIVATED_ACCOUNT'     => 'Desactivated Account',
        'REACTIVATED_ACCOUNT'      => 'Reactivated Account',
        'ARCHIVED_ACCOUNT'         => 'Archived Account',
        'ACTIVATED_MODULE'         => 'Activated Module',
        'DESACTIVATED_MODULE'      => 'Desactivated Module',
        'LOGGED_IN_PROJECT'        => 'Logged in Project'
    );

    public function __construct($marioKey, $container)
    {
        $this->isInit = false;
        $this->marioKey = $marioKey;
        $this->container = $container;
    }

    /**
     * Retourne le nom de l'extension
     * @return string le nom de l'extension
     */
    public function getName()
    {
        return 'analytics';
    }

    public function getFunctions()
    {
        return array(
            'has_help'            => new Twig_Function_Method($this, 'hasHelp', array('is_safe' => array('html'))),
            'analyticsIdentify'  => new Twig_Function_Method($this, 'identify', array('is_safe' => array('html'))),
            'analyticsPage'      => new Twig_Function_Method($this, 'page', array('is_safe' => array('html'))),
            'analyticsAlias'     => new Twig_Function_Method($this, 'alias', array('is_safe' => array('html'))),
        );
    }

    public function hasHelp()
    {
        /** @var BNSRightManager $rightManager */
        $rightManager = $this->container->get('bns.right_manager');
        $userManager = $rightManager->getUserManager();

        return $rightManager->isAuthenticated() && $rightManager->getUserSession()
            && $userManager->getUser()
            && !in_array($userManager->getUser()->getHighRoleId(), array(8, 9))
            && $rightManager->getCurrentGroupManager()->getProjectInfo('has_inline_support');
    }

    public function identify(User $user, SessionInterface $session = null)
    {
        $rightManager = $this->container->get('bns.right_manager');
        if (!$rightManager->isAuthenticated()) {
            return false;
        }
        //Construction du message selon le type d'utilisateur
        //Appelé sur toutes les pages, doit être très léger
        if ($this->container->get('bns.group_manager')->hasGroup()) {
            $oldGroup = $this->container->get('bns.group_manager')->getGroup();
        }
        if (!$rightManager->getCurrentGroup()) {
            // no group context we can't use it
            return false;
        }
        $gm = $rightManager->getCurrentGroupManager();
        if (
            !in_array($user->getHighRoleId(),array(8,9)) &&
            $gm->getProjectInfo('has_inline_support')
        ) {
            $message = array();
            $message['email'] = $user->getEmail();
            $message['username'] = $user->getLogin();
            $message['firstName'] = $user->getFirstName();
            $message['lastName'] = $user->getLastName();
            $message['gender'] = $user->getGender() == 'M' ? 'Male' : 'Female';
            $message['language_override'] = $user->getLang();
            $message['registered'] = $user->hasRegistered();
            $message['registration_step'] = $user->getRegistrationStep();
            $message['registration_origin'] = $user->getRegisterOrigin();

            // if session is given, get pre-calculated info from it
            if ($session) {
                if ($schoolId = $session->get('current_school')) {
                    $message['company'] = array('company_id' => $schoolId);
                }
            }

            if(isset($oldGroup)) {
                $this->container->get('bns.group_manager')->setGroup($oldGroup);
            }
            if (isset($message)) {
                return $this->call('identify', $user->getId(), $message, $user);
            } else {
                return false;
            }
        }

        if (isset($oldGroup)) {
            $this->container->get('bns.group_manager')->setGroup($oldGroup);
        }
    }

    public function alias($user)
    {
        return $this->call('alias', $user->getId());
    }

    public function page($page)
    {
        return $this->call('page', $page);
    }

    public function call($method, $action, $message = null, $object = null)
    {
        $script = '<script type="text/javascript">';
        if(!$this->isInit)
        {
            $script .= <<<EOF
                    window.analytics=window.analytics||[],window.analytics.methods=["identify","group","track","page","pageview","alias","ready","on","once","off","trackLink","trackForm","trackClick","trackSubmit"],window.analytics.factory=function(t){return function(){var a=Array.prototype.slice.call(arguments);return a.unshift(t),window.analytics.push(a),window.analytics}};for(var i=0;i<window.analytics.methods.length;i++){var key=window.analytics.methods[i];window.analytics[key]=window.analytics.factory(key)}window.analytics.load=function(t){if(!document.getElementById("analytics-js")){var a=document.createElement("script");a.type="text/javascript",a.id="analytics-js",a.async=!0,a.src=("https:"===document.location.protocol?"https://":"https://")+"cdn.segment.io/analytics.js/v1/"+t+"/analytics.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(a,n)}},window.analytics.SNIPPET_VERSION="2.0.9",
                    window.analytics.load("$this->marioKey");
EOF;
            $this->isInit = true;
        }
        switch($method)
        {
            case 'identify':
                $script .= 'analytics.identify("' . $action . '", ';

                $script .= json_encode($message);

                $script .= ", {";

                $user = $object;

                $script .= "
                    integrations: {
                        Intercom : {
                            user_hash : '" . hash_hmac('sha256', $user->getId(), $this->container->getParameter('intercom_api_key')). "'
                        }
                    }
                });";

                break;
            case 'track':
                $script .= 'analytics.track("' . $action . '", {';

                foreach($message as $key => $value)
                {
                    $script .= $key . ':' . '"' . $value  . '",';
                }

                $script .= '});';
                break;
            case 'page':
                if($action != '')
                {
                    $script .= "window.analytics.page('$action');";
                }else{
                    $script .= "window.analytics.page();";
                }

                break;
            case 'alias':
                $script .= "analytics.alias('$action');";
                break;
            case 'group':
                $script .= 'analytics.group("' . $action . '", {';

                foreach($message as $key => $value)
                {
                    $script .= $key . ':' . '"' . $value  . '",';
                }

                $script .= '});';
                break;
        }

        $script .= '</script>';
        return $script;
    }

}
