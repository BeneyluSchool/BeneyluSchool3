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
		$group->setId(isset($params['group_id']) ? $params['group_id'] : $params['id']);
		$group->setLabel($params['label']);
		$group->setGroupTypeId($params['group_type_id']);
		$group->setRegistrationDate(time());
        $group->setSlug('groupe-' . $group->getId());


        $group->setValidationStatus(self::VALIDATION_STATUS_VALIDATED);


        if (isset($params['aaf_id']) && $params['aaf_id']) {
            $group->setAafId($params['aaf_id']);
        }

        if (isset($params['aaf_academy']) && $params['aaf_academy']) {
            $group->setAafAcademy($params['aaf_academy']);
        }

        if (isset($params['import_id']) && $params['import_id']) {
            $group->setImportId($params['import_id']);
        }

        if (isset($params['lang']) && $params['lang']) {
            $group->setLang($params['lang']);
        }

        if (isset($params['country']) && $params['country']) {
            $group->setCountry($params['country']);
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
