<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSitePeer;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for performing query and update operations on the 'mini_site' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSitePeer extends BaseMiniSitePeer
{
	/**
	 * @var GroupType 
	 */
	private static $editorGroup = null;
	
	/**
	 * @param array $params
	 */
	public static function create(array $params)
	{
		$miniSite = new MiniSite();
		$miniSite->setGroupId($params['group_id']);
		$miniSite->setTitle('Site : ' . $params['label']);
		$miniSite->save();
		
		// Create the homepage, many queries are based on the homepage
		$homePage = new MiniSitePage();
		$homePage->setMiniSiteId($miniSite->getId());
		$homePage->setTitle('Accueil');
		$homePage->setIsActivated(true);
		$homePage->setType(MiniSitePagePeer::TYPE_TEXT);
		$homePage->setIsHome(true);
		$homePage->save();
		
		BNSAccess::getContainer()->get('bns.group_manager')->createSubgroupForGroup(array(
			'label'             => 'Editeur', // TODO i18n
			'type'				=> 'EDITOR_MINISITE',
			'group_type_id'		=> self::getEditorGroup(),
			'attributes'        => array()
		), $params['group_id'], false);
	}
	
	/**
	 * @return GroupType 
	 * 
	 * @throws \RuntimeException
	 */
	private static function getEditorGroup()
	{
		if (null == self::$editorGroup) {
			self::$editorGroup = GroupTypeQuery::create()
				->add(GroupTypePeer::TYPE, 'EDITOR_MINISITE')
			->findOne();
			
			if (null == self::$editorGroup) {
				throw new \RuntimeException('The group type EDITOR_MINISITE doest NOT exist, please create it !');
			}
		}
		
		return self::$editorGroup;
	}
}