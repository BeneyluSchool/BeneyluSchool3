<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseGroupPeer;

/**
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupPeer extends BaseGroupPeer
{
	/**
	 * @param array $params
	 *	- group_id
	 *  - label
	 *  - group_type_id
	 *  - (optionnal) attributes (array: key, value)
	 * 
	 * @return \BNS\App\CoreBundle\Model\Group
	 */
	public static function createGroup($params)
	{
		$group = new Group();
		$group->setId($params['group_id']);
		$group->setLabel($params['label']);
		$group->setGroupTypeId($params['group_type_id']);
		$group->setRegistrationDate(time());
		
		if (isset($params['validated']) && $params['validated']) {
			$group->setValidationStatus(self::VALIDATION_STATUS_VALIDATED);
		}
		
		$group->save();
		
		// GroupData Process
		if (isset($params['attributes'])) {
			foreach ($params['attributes'] as $attribute_name => $attribute_value) {
				$group->setAttribute($attribute_name, $attribute_value);
			}
		}
		
		return $group;
	}
}