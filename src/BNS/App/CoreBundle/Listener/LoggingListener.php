<?php
namespace BNS\App\CoreBundle\Listener;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use BNS\App\CoreBundle\Model\Logging;
class LoggingListener
{

	protected $container;
	public function __construct($container)
	{
		$this->container = $container;
	}
	/**
	 * A chaque page mise en BDD (pour retours statistiques) des données essentielles liées à l'action
	 * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent $event
	 */
	public function onKernelController(FilterControllerEvent $event)
	{
		$container = $this->container;
		$route = $event->getRequest()->attributes->get('_route');
		//On écarte tous les appels internes
		if($route && $route != "_internal" && $route != "_monitoring" && $container->get('bns.right_manager')->isAuthenticated()){
			$logging = new Logging();
			$logging->setUsername($container->get('bns.right_manager')->getUserSession()->getUsername());
			$logging->setUserId($container->get('bns.right_manager')->getUserSession()->getId());
			$logging->setGroupId($container->get('bns.right_manager')->getCurrentGroupId());
			$controller = explode('\\',$event->getRequest()->attributes->get('_controller'));
			$logging->setModule(isset($controller[2]) ? $controller[2] : "");
			$logging->setAction(isset($controller[4]) ? $controller[4] : "");
            $logging->setIp($container->get('request')->getClientIp());
			$logging->setRoute($route);
            if(!in_array($route,array('user_password','BNSAppProfileBundle_back_authenticate_target_user', 'BNSAppProfileBundle_back_merge_validate', 'user_api_change_password')))
            {
                $logging->setParameters(serialize($event->getRequest()->request->all()));
            }
			$logging->save();
		}
	}
}
