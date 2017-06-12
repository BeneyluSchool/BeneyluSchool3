<?php

namespace BNS\App\MiniSiteBundle\Controller;

use \BNS\App\CoreBundle\Rss\RssManager;

use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 *
 * @Route("/widget")
 */
class FrontWidgetController extends AbstractMiniSiteController
{
	public function renderRssAction($widget)
	{
		try {
			$rssManager = new RssManager($this->get('snc_redis.default'));
		}
		catch (\Exception $e) {
			// Nothing
		}

		return $this->render('BNSAppMiniSiteBundle:Widget:front_rss_content.html.twig', array(
			'items' => $rssManager->getRss($widget->getRssUrl(), $widget->getLimit())
		));
	}
}