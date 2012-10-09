<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseRankPeer;


/**
 * Skeleton subclass for performing query and update operations on the 'rank' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class RankPeer extends BaseRankPeer {
	
	public static function createRank($params)
	{
		$rank = new Rank();
		
		$rank->setUniqueName($params['unique_name']);
		$rank->setModuleId($params['module_id']);
		$rank->save();
		
		
		$rank->save();
		
		foreach($params['i18n'] as $language => $values)
		{
			$rank_i18n = new RankI18n();
			$rank_i18n->setUniqueName($params['unique_name']);
			$rank_i18n->setLabel($values['label']);
			if(isset($values['description'])){
				$rank_i18n->setDescription($values['description']);
			}
			$rank_i18n->setLang($language);
			$rank->addRankI18n($rank_i18n);
			
		}
		$rank->save();
		return $rank;
	}

} // RankPeer
