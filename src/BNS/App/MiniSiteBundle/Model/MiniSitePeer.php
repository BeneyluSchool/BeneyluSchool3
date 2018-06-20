<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\CoreBundle\Group\BNSGroupManager;
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
	 * @param BNSGroupManager $groupManager
	 * @return MiniSite
	 */
	public static function create(array $params, TranslatorInterface  $trans, BNSGroupManager $groupManager)
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

		// check if we need to create a city page
		$needsCityPage = false;
		$group = $groupManager->setGroupById($params['group_id'])->getGroup();
		if ('CITY' === $group->getType()) {
			// group is a city, add city info page
			$needsCityPage = true;
		} else if ('SCHOOL' === $group->getType()) {
			// group is a school, add city page only if has a parent city
			$parents = $groupManager->getParents();
			foreach ($parents as $parent) {
				if ('CITY' === $parent->getType()) {
					$needsCityPage = true;
					break;
				}
			}
		}
		if ($needsCityPage) {
			$miniSite->ensureCityPage();
		}

		return $miniSite;
	}

}
