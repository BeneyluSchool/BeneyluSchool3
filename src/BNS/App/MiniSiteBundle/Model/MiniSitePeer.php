<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePeer;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePeer extends BaseMiniSitePeer
{

	/**
	 * List of params :
	 *  - group_id
	 *  - label
	 *
	 * @param array $params
	 * @param Translator $trans
	 * @return MiniSite
	 */
	public static function create(array $params, TranslatorInterface  $trans)
	{
		$miniSite = new MiniSite();
		$miniSite->setGroupId($params['group_id']);
		$miniSite->setTitle($trans->trans('TITLE_SITE_NAME', array('%name%' => $params['label']), 'MINISITE'));
		$miniSite->save();

		// Create the homepage, many queries are based on the homepage
		$homePage = new MiniSitePage();
		$homePage->setMiniSiteId($miniSite->getId());
		$homePage->setTitle($trans->trans('TITLE_WELCOME', array(), 'MINISITE'));
		$homePage->setIsActivated(true);
		$homePage->setType(MiniSitePagePeer::TYPE_TEXT);
		$homePage->setIsHome(true);
		$homePage->save();

		return $miniSite;
	}

}
