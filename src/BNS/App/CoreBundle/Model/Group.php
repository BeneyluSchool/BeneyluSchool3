<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\GroupPeer;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroup;
use BNS\App\MediaLibraryBundle\Model\MediaFolderGroupQuery;
use BNS\App\PaasBundle\Client\PaasClientInterface;
use PropelPDO;
use Symfony\Component\Security\Acl\Exception\Exception;
use \Criteria;

use BNS\App\CoreBundle\Model\om\BaseGroup;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupData;
use BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * Skeleton subclass for representing a row from the 'group' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class Group extends BaseGroup implements PaasClientInterface
{
    use TranslatorTrait;

    /** @var bool|null */
    public $isFavorite = null;

    public $userIds = null;

    private $users;
    private $subgroups;
    private $subgroupsRole;
    private $subGroupsArray;
    private $canSeeProfile = false;

	/**
	 * @var Module The app representing this group
	 */
	private $app;

	/**
	 * @var Group groupe parent du groupe courant
	 */
	private $parents = null;

    /**
	 * @var array<Module> type group's modules
	 */
    private $activatedModules;

	/*
	 *  FONCTIONS ESSENTIELLES
	 */


	/**
	 *
	 * Affiche le label pour affichage
	 * @return string Label à afficher
	 *
	 */
	public function __toString()
	{
		return $this->getLabel();
	}

	/**
	 * Renvoie le type d'objet
	 */
	public function getClassName()
	{
		return 'Group';
	}

    public function getType()
    {
        return $this->getGroupType()->getType();
    }

	/*
	 *  FONCTIONS SPECIFIQUES
	 */

	/**
	 * On récupère tous les GroupData du groupe $this;
	 * le label (getLabel()) et le(s) valeur(s) (getValue()) sont directements accessibles
	 * sur les objets GroupData
	 *
	 * @return    $data_value tableau qui contient tous les objets de type GroupData associés
	 * 			  au group $this;
	 *
	 */
	public function getFullGroupDatas()
	{
		if (!isset($this->collGroupDatas)) {
			$groupDatas = GroupDataQuery::create()
				->joinWith('GroupTypeData')
				->joinWith('GroupTypeData.GroupTypeDataTemplate')
			->findByGroupId($this->getId());

			$groupDataIds = array();
			foreach ($groupDatas as $groupData) {
				if ($groupData->getValue() == null) {
					$groupDataIds[] = $groupData->getId();
				}
			}

			$groupDataChoices = GroupDataChoiceQuery::create()
				->joinWith('GroupTypeDataChoice')
				->add(GroupDataChoicePeer::GROUP_DATA_ID, $groupDataIds, \Criteria::IN)
			->find();

			foreach ($groupDataChoices as $groupDataChoice) {
				foreach ($groupDatas as $groupData) {
					if ($groupData->getId() == $groupDataChoice->getGroupDataId()) {
						$groupData->addGroupDataChoice($groupDataChoice);
						break;
					}
				}
			}

			$this->collGroupDatas = $groupDatas;
		}

		return $this->collGroupDatas;
	}

    /**
     * @param string $uniqueName
     *
     * @return GroupTypeDataTemplate
     */
    public function getGroupTypeDataTemplateByUniqueName($uniqueName)
    {
        return GroupTypeDataTemplateQuery::create()
            ->filterByUniqueName($uniqueName)
            ->useGroupTypeDataQuery()
                ->filterByGroupTypeId($this->getGroupTypeId())
            ->endUse()
            ->findOne()
        ;
    }

    /**
     * @param string $uniqueName
     *
     * @return GroupData
     */
    public function getGroupDataByUniqueName($uniqueName)
    {
        return GroupDataQuery::create()
            ->filterByGroupId($this->getId())
            ->useGroupTypeDataQuery()
                ->filterByGroupTypeDataTemplateUniqueName($uniqueName)
            ->endUse()
            ->findOne()
        ;
    }

    /**
     * @param string $uniqueName
     * @param mixed $default
     * @return mixed
     */
    public function getGroupDataValue($uniqueName, $default = null)
    {
        $groupData = $this->getGroupDataByUniqueName($uniqueName);
        if ($groupData) {
            return $groupData->getValue();
        }

        return $default;
    }


	/////////    METHODES LIEES AUX ATTRIBUTS    \\\\\\\\\\\\

	/**
	 * @param string $uniqueName
	 * @param string|mixed $value
	 *
	 * @throws Exception
	 */
	public function createAttribute($uniqueName, $value)
	{
		if ($this->hasAttribute($uniqueName)) {
			$groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
			$group_data = new GroupData();
			$group_data->setGroupTypeDataId($this->getGroupType()->getGroupTypeDataByUniqueName($uniqueName)->getId());
			$group_data->setGroupId($this->getId());

			switch ($groupe_type_data_template->getType()) {
				case "SINGLE":
				case "TEXT":
				case "BOOLEAN":
					$group_data->setValue($value);
					$group_data->save();
				break;
				case "ONE_CHOICE":
					$group_data->save();
					$group_data->clearChoices();
					$group_data->addChoice($value);
				break;
				case "MULTIPLE_CHOICE":
					$group_data->save();
					$group_data->clearChoices();
					$group_data->addChoices($value);
				break;
			}
		}
		else {
			// FIXME retirer ces exceptions et faire en sorte que si l'attribut existe dans les templates, on puisse l'ajouter quoi qu'il arrive
			if ($uniqueName == 'HOME_MESSAGE' || $uniqueName == 'YERBOOK_MESSAGE'){
				$this->addGroupTypeData($uniqueName);
				$this->getGroupType()->reloadAttributes();
				$this->createAttribute($uniqueName, $value);
			}
			else {
				throw new Exception("You cant create this attribute [" . $uniqueName . "] to this group. Add the attribute to the group before setting it !");
			}
		}
	}

	/**
	 * @param string $uniqueName
	 * @param mixed  $value
	 */
	public function setAttribute($uniqueName, $value)
	{
		if ($this->hasAttributeAndValue($uniqueName)) {
			$this->updateAttribute($uniqueName, $value);
		}
		else {
			$this->createAttribute($uniqueName, $value);
		}

        // Wtf ?
		if ($uniqueName == 'NAME') {
			$this->setLabel($value);
			$this->save();
		}
	}

    public function setLabel($label)
    {
        parent::setLabel($label);
        if ($this->isColumnModified(GroupPeer::LABEL) &&  ($mediaRootFolder = $this->getMediaFolderRoot())) {
            $mediaRootFolder->setLabel($label);
            $mediaRootFolder->save();
        }
    }

	/**
	 * @param type $uniqueName
	 * @param mixed $value
	 *  - array if multiple value
	 */
	private function updateAttribute($uniqueName, $value)
	{
		$groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
		$group_data = $this->getGroupDataByUniqueName($uniqueName);
		switch ($groupe_type_data_template->getType()) {
			case "SINGLE":
			case "TEXT":
			case "BOOLEAN":
				$group_data->setValue($value);
				$group_data->save();
				$this->$uniqueName = $value; // update cached value
			break;
			case "ONE_CHOICE":
				$group_data->clearChoices();
				$group_data->addChoice($value);
			break;
			case "MULTIPLE_CHOICE":
				$group_data->clearChoices();
				$group_data->addChoices($value);
			break;
		}
	}

	/**
	 * @return GroupTypeData[]|\PropelObjectCollection
	 */
	public function getAttributes()
	{
		$query = GroupTypeDataQuery::create();
		$query
			->filterByGroupTypeId($this->getGroupTypeId())
			->leftJoin('GroupTypeDataTemplate')
			->with('GroupTypeDataTemplate')
		;

		return $query->find();
	}

	/**
	 * Méthode qui permet de vérifier si le group $this possède un attribut $uniqueName
	 *
	 * @param String $uniqueName est le nom unique de l'attribut dont on souhaite vérifier la présence
	 *
	 * @return 	un objet du type GroupTypeData associé à $uniqueName, si le groupe $this ne possède pas l'attribut alors
	 * 			retourne null
	 */
	public function hasAttribute($uniqueName)
	{
		return $this->getGroupType()->hasAttribute($uniqueName);
	}

	/**
	 * @param string $uniqueName
	 *
	 * @return boolean
	 */
	public function hasAttributeAndValue($uniqueName)
	{
		return $this->getGroupDataByUniqueName($uniqueName) != null;
	}

    /**
     * @param string $uniqueName
     *
     * @param null $default
     * @param bool $returnChoiceLabel
     * @return mixed|string
     * @throws \Exception
     * @throws \PropelException
     */
    public function getAttribute($uniqueName, $default = null, $returnChoiceLabel = false)
    {
        if (!isset($this->$uniqueName) || $returnChoiceLabel) {
            if ($this->hasAttribute($uniqueName)) {
                $groupe_type_data_template = $this->getGroupTypeDataTemplateByUniqueName($uniqueName);
                $defaultValue = $groupe_type_data_template->getDefaultValue();

                switch ($groupe_type_data_template->getType()) {
                    case "SINGLE":
                    case "TEXT":
                    case "BOOLEAN":
                        /** @var GroupData $groupData */
                        $groupData = GroupDataQuery::create()
                            ->filterByGroupId($this->getId())
                            ->useGroupTypeDataQuery()
                                ->filterByGroupTypeDataTemplateUniqueName($uniqueName)
                            ->endUse()
                            ->findOne();

                        if (!$groupData && 'NAME' === $uniqueName) {
                            return $this->getLabel();
                        }

                        $value = $groupData ? $groupData->getDirectValue() : null;

                        if ('BOOLEAN' === $groupe_type_data_template->getType()) {
                            // handle null/empty value with $defaultValue value
                            // this allow 3 states boolean true/false unset
                            if (null === $value || '' === $value) {
                                $this->$uniqueName = null === $defaultValue ? null : (bool) $defaultValue;
                            } else {
                                $this->$uniqueName = (bool) $value;
                            }
                            break;
                        }

                        if ($value != "" && $value != null) {
                            $this->$uniqueName = $value;
                        } elseif (0 === strpos($defaultValue, 'DEFAULT_')) {
                            $this->$uniqueName =  $this->getTranslator()->trans(/** @Ignore */ $defaultValue, [], 'GROUP_TYPE');
                        } else {
                            $this->$uniqueName =  $defaultValue;
                        }
                        break;

                    case "ONE_CHOICE":
                        /** @var GroupTypeDataChoice $groupTypeDataChoice */
                        $groupTypeDataChoice = GroupTypeDataChoiceQuery::create()
                            ->filterByGroupTypeDataTemplateUniqueName($uniqueName)
                            ->useGroupDataChoiceQuery()
                                ->useGroupDataQuery()
                                    ->filterByGroup($this)
                                ->endUse()
                            ->endUse()
                            ->findOne()
                        ;

                        if ($groupTypeDataChoice) {
                            if ($returnChoiceLabel) {
                                return $groupTypeDataChoice->getLabel();
                            }

                            $this->$uniqueName = $groupTypeDataChoice->getValue();
                        } else {
                            $groupTypeDataChoice = GroupTypeDataChoiceQuery::create()->findPk($defaultValue);
                            if ($groupTypeDataChoice && $returnChoiceLabel) {
                                return $groupTypeDataChoice->getLabel();
                            }
                            $this->$uniqueName = $groupTypeDataChoice ? $groupTypeDataChoice->getValue() : null;
                        }
                    break;

                    case "MULTIPLE_CHOICE":
                        $groupTypeDataChoices = GroupTypeDataChoiceQuery::create()
                            ->filterByGroupTypeDataTemplateUniqueName($uniqueName)
                            ->useGroupDataChoiceQuery()
                                ->useGroupDataQuery()
                                    ->filterByGroup($this)
                                ->endUse()
                            ->endUse()
                            ->setDistinct()
                            ->find()
                        ;

                        $values = array();
                        foreach ($groupTypeDataChoices as $choice) {
                            if ($returnChoiceLabel) {
                                $values[] = $choice->getLabel();
                            } else {
                                $values[] = $choice->getValue();
                            }
                        }

                        if ($returnChoiceLabel) {
                            return $values;
                        }

                        if (count($values) > 0) {
                            $this->$uniqueName = $values;
                        } else {
                            $def = GroupTypeDataChoiceQuery::create()->findOneById($defaultValue);
                            if ($def) {
                                $this->$uniqueName = $def->getValue();
                            } else {
                                $this->$uniqueName = null;
                            }
                        }

                        $this->$uniqueName = $values;
                        break;
                }
            } else {
                $this->$uniqueName = $default;
            }
        }

        return $this->$uniqueName;
    }

    public function populateCurrentGroupDatas()
    {
        if (!$this->collGroupDatas instanceof \PropelObjectCollection) {
            return;
        }

        /** @var GroupData $groupData */
        foreach ($this->collGroupDatas as $groupData) {
            $this->{$groupData->getGroupTypeData()->getGroupTypeDataTemplateUniqueName()} = $groupData->getValue();
        }
    }

	/**
	 * Ajouter un type d'attribute au type de groupe du groupe
	 * @param type $unique_name Unique name du groupTypeDataTemplate à ajouter
	 */
	public function addGroupTypeData($unique_name)
	{
		$this->getGroupType()->addGroupTypeDataByUniqueName($unique_name);
	}

    /**
     * @param string $uniqueName
     *
     * @return string
     */
    public function printAttribute($uniqueName)
    {
        if ($uniqueName == 'NAME') {
            return $this->getLabel();
        }
        $value = $this->getAttribute($uniqueName, null, true);

        if (is_array($value)) {

            return implode(', ', $value);
        }

        return $value;
    }


	///////////////////         METHODES LIEES AUX RESSOURCES       \\\\\\\\\\\\\\\\\\\\\\\

//    /**
//     * @param type $with_root
//     *
//     * @return type
//     */
//    public function getRessourceLabels($with_root = true)
//    {
//        if ($with_root) {
//            return ResourceLabelGroupQuery::create()->orderByBranch()->filterByGroupId($this->getId())->find();
//        }
//
//        return ResourceLabelGroupQuery::create()->filterByTreeLevel(array('min' => 1))->findTree($this->getId());
//    }

    /**
     * @param \PropelPDO $con
     * @return \BNS\App\MediaLibraryBundle\Model\MediaFolderGroup
     */
    public function getMediaFolderRoot(\PropelPDO $con = null)
    {
        return MediaFolderGroupQuery::create()->findRoot($this->getId(), $con);
    }

    public function getExternalFolder(\PropelPDO $con = null)
    {
        if (!in_array($this->getType(), ['CLASSROOM', 'SCHOOL'])) {
            return null;
        }

        $folder = MediaFolderGroupQuery::create()
            ->filterByGroupId($this->getId())
            ->filterByIsExternalFolder(true)
            ->findOne();

        if (!$folder) {
            $folder = new MediaFolderGroup();
            $folder->setSlug('external-' . $this->getId());
            $folder->setLabel('LABEL_SPOT_FOLDER');
            $folder->setGroupId($this->getId());
            $folder->insertAsFirstChildOf($this->getMediaFolderRoot($con));
            $folder->setIsExternalFolder(true);
            $folder->save();
            $folder->setupForSpecialFolder();
        }

        return $folder;
    }

    /**
     * @param int $value
     */
    public function addResourceSize($value)
    {
        unset($this->RESOURCE_USED_SIZE);
        $this->setAttribute('RESOURCE_USED_SIZE', $this->getAttribute('RESOURCE_USED_SIZE') + $value);
    }

    /**
     * @param int $value
     */
    public function deleteResourceSize($value)
    {
        unset($this->RESOURCE_USED_SIZE);
        $this->setAttribute('RESOURCE_USED_SIZE', max(0, $this->getAttribute('RESOURCE_USED_SIZE') - $value));
    }

	/**
	 * Set the new used size quota
	 *
	 * @param int $size
	 */
	public function setResourceUsedSize($size)
	{
		$this->setAttribute('RESOURCE_USED_SIZE', $size);
	}

    public function getResourceUsedSize()
    {
        return $this->getAttribute('RESOURCE_USED_SIZE', 0);
    }

    /**
     * @return array|User[]
     * @throws \Exception
     */
    public function getUsers()
    {
        if (!isset($this->users)) {
            throw new \Exception('You didn\'t set users attribute, you can not do get!');
        }

        return $this->users;
    }

	/**
	 * @param array|User[] $users
	 */
	public function setUsers($users)
	{
		$this->users = $users;
	}

	public function setSubgroupsRoleWithUsers(array $subgroupsRole)
	{
		$this->subgroupsRole = $subgroupsRole;
	}

	public function getSubgroupsRoleWithUsers()
	{
		if (!isset($this->subgroupsRole)) {
			throw new \Exception('You didn\'t set subgroups role attribute, you can not do get!');
		}

		return $this->subgroupsRole;
	}

	/**
	 * @param type $uniqueName
	 *
	 * @return mixed
	 */
	public function getGroupDataByGroupTypeDataTemplateUniqueName($uniqueName)
	{
		$groupTypeData = $this->hasAttribute($uniqueName);
		if ($groupTypeData == null) {
			return null;
		}

		if (!isset($this->collGroupDatas)) {
			$this->getFullGroupDatas();
		}

		foreach($this->collGroupDatas as $groupData) {
			if ($groupData->getGroupTypeDataId() == $groupTypeData->getId()) {
				return $groupData;
			}
		}
	}

	/**
	 * @param type $typeGroup
	 *
	 * @return type
	 */
	public function getGroupChilds($typeGroup = null)
	{
		$query = GroupQuery::create()->joinWith('GroupType');
		if (null != $typeGroup) {
			$query->add(GroupTypePeer::TYPE, $typeGroup);
		}

		$childGroups = $query->find();

		return $childGroups;
	}

	/**
	 * @param array $subgroups
	 */
	public function setSubgroups(array $subgroups)
	{
		$this->subgroups = $subgroups;
	}

	/**
	 * @param bool $throw Lance une exception si la collection n'a pas été set précédemment
	 * @return array|Group[]
	 * @throws \Exception
	 */
	public function getSubgroups($throw = true)
	{
		if (true === !isset($this->subgroups) && $throw) {
			throw new \Exception('You did not set subgroups!');
		}

		return $this->subgroups;
	}

	public function hasSubgroup()
	{
		return isset($this->subgroups);
	}

	public function getParents()
	{
		if (!isset($this->parents)) {
			throw new \Exception('You did not set a parent group!');
		}

		return $this->parents;
	}

	/**
	 * @param array <\BNS\App\CoreBundle\Model\om\BaseGroup> $parents
	 */
	public function setParents(array $parents)
	{
		$this->parents = $parents;
	}

	public function getFullParentLabel()
	{
		if (isset($this->parent) and $this->parent instanceof Group) {
			$grandParentLabel = $this->parent->getFullParentLabel();
			if (!$grandParentLabel && $this->parent->hasAttribute('CITY')) {
				$grandParentLabel = $this->parent->getAttribute('CITY');
			}
			if ($grandParentLabel) {
				return $this->parent->getLabel() . ' - ' . $grandParentLabel;
			} else {
				return $this->parent->getLabel();
			}
		}

		return null;
	}

	/**
	 * @param array<Integer> $subGroupIds
	 */
	public function setSubGroupsArray(array $subGroupIds)
	{
		$this->subGroupsArray = $subGroupIds;
	}

	/**
	 * @param int $groupTypeId
	 *
	 * @return array
	 */
	public function getSubGroupArrayByGroupTypeId($groupTypeId)
	{
		foreach ($this->subGroupsArray as $subGroup) {
			if ($groupTypeId == $subGroup['group_type_id']) {
				return $subGroup;
			}
		}

		return null;
	}

	/**
	 * @return boolean
	 */
	public function hasSubGroupArray($groupTypeId = null)
	{
		if (null == $groupTypeId) {
			return isset($this->subGroupsArray) && count($this->subGroupsArray) > 0;
		}

		if (isset($this->subGroupsArray)) {
			return null != $this->getSubGroupArrayByGroupTypeId($groupTypeId);
		}

		return false;
	}

	/**
	 * @return array<Module>
	 */
	public function getActivatedModules($role)
	{
		if (!isset($this->activatedModules)) {
			BNSAccess::getContainer()->get('bns.group_manager')->setGroup($this);
			$this->modules = BNSAccess::getContainer()->get('bns.group_manager')->getActivatedModules($role);
		}

		return $this->activatedModules;
	}

	/**
	 * Remove the pending validation date
	 */
	public function removePendingValidationDate()
	{
		if (null != $this->pending_validation_date) {
			$this->modifiedColumns[] = GroupPeer::PENDING_VALIDATION_DATE;
		}

		$this->pending_validation_date = null;
	}

	/**
	 * Ecris le statut du groupe
	 */
	public function printValidation()
	{
		switch($this->getValidationStatus()){
			case GroupPeer::VALIDATION_STATUS_VALIDATED:
				return "Validé";
			break;
			case GroupPeer::VALIDATION_STATUS_REFUSED:
				return "Refusé";
			break;
			case GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION:
				return "En cours de validation";
			break;
		}
	}

    //On ne vérifie que pour les env le nécessitant
    protected function checkGroupValidation()
    {
        if(BNSAccess::getContainer() && BNSAccess::getContainer()->hasParameter('check_group_validated') && BNSAccess::getContainer()->getParameter('check_group_validated') == true)
        {
            return true;
        }
        return false;
    }

	/**
	 * Le groupe est il validé
	 */

    public function validateStatus()
    {
        switch($this->getValidationStatus())
        {
            case GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION:
                $action = 'CONFIRMED_CLASSROOM';
                break;
            case GroupPeer::VALIDATION_STATUS_REFUSED:
                $action = 'REACTIVATED_CLASSROOM';
                break;
        }
        if(BNSAccess::isConnectedUser() && $this->getType() == "CLASSROOM" && isset($action))
        {
            BNSAccess::getContainer()->get('bns.right_manager')->trackAnalytics($action, $this);
        }

        $this->setValidationStatus(GroupPeer::VALIDATION_STATUS_VALIDATED);
        $this->removePendingValidationDate();
        $this->setConfirmationToken(null);
        $this->save();

        if($this->getType() == "CLASSROOM" && isset($action) && BNSAccess::isConnectedUser())
        {
            BNSAccess::getContainer()->get('bns.analytics.manager')->track($action, $this);
        }

    }

    public function refuse()
    {
        if(BNSAccess::isConnectedUser() && $this->getType() == "CLASSROOM")
        {
            BNSAccess::getContainer()->get('bns.right_manager')->trackAnalytics('DESACTIVATED_CLASSROOM', $this);
        }
        $this->setValidationStatus( GroupPeer::VALIDATION_STATUS_REFUSED);
        $this->removePendingValidationDate();
        $this->setConfirmationToken(null);
        $this->save();

        if($this->getType() == "CLASSROOM")
        {
            BNSAccess::getContainer()->get('bns.analytics.manager')->track('DESACTIVATED_CLASSROOM', $this);
        }
    }

    public function isValidated()
    {
        return true;
    }

	public function isRefused()
	{
        if(!$this->checkGroupValidation())
        {
            return false;
        }
		return $this->getValidationStatus() == GroupPeer::VALIDATION_STATUS_REFUSED;
	}

	public function isPendingConfirmation()
	{
        if(!$this->checkGroupValidation())
        {
            return false;
        }
		return $this->getValidationStatus() == GroupPeer::VALIDATION_STATUS_PENDING_VALIDATION;
	}

    //Fonction liées au champ enabled
    public function isEnabled()
    {
        return $this->getEnabled();
    }

    public function enable()
    {
        if (!$this->isEnabled()) {
        $this->setEnabled(true);
            $this->setEnabledAt('now');
            //  TODO : remove me
        $this->save();
    }

        return $this;
    }

    public function disable()
    {
        if ($this->isEnabled()) {
        $this->setEnabled(false);
            $this->setEnabledAt(null);
            //  TODO : remove me
        $this->save();
    }

        return $this;
    }

    public function toggleEnabled()
    {
        if ($this->isEnabled()) {
            return $this->disable();
    }

        return $this->enable();
    }

    public function printEnabled()
    {
        return $this->isEnabled() ? "Activé" : "Désactivé";
    }

    public function printStatus($withValidation = false)
    {
        if($this->isArchived())
        {
            return 'Archivée';
        }
        if(!$withValidation)
        {
            return "Confirmée";
        }

        if($this->isRefused())
        {
            return 'Désactivée';
        }
        if($this->isPendingConfirmation())
        {
            return 'Non confirmée';
        }
        if($this->isValidated())
        {
            return 'Confirmée';
        }
    }


    /**
     * Archive du groupe : Il le remontera pas dans les listes et dans les droits des utilisateurs
     * Un groupe archivé est supprimé au bout d'un an par défaut
     */
    public function archive($expire = 31536000)
    {
        $this->setArchived(true);
        $this->setArchiveDate(time());
        $this->setExpiresAt(time() + $expire);
        $this->save();
    }

    public function isArchived()
    {
        return $this->getArchived();
    }

    public function restore()
    {
        $this->setArchived(false);
        $this->setArchiveDate(null);
        $this->setExpiresAt(null);
        $this->save();
    }

	/**
	 * @return Blog
	 */
	public function getBlog()
	{
		$blogs = $this->getBlogs();

		return isset($blogs[0]) ? $blogs[0] : null;
	}

    /**
    * @return Agenda
    */
    public function getAgenda()
    {
        $agendas = $this->getAgendas();

		return isset($agendas[0]) ? $agendas[0] : null;
    }

	/**
	 * @return array<ResourceLabelGroup>
	 */
	public function getResourceLabelByLevel($level = 1)
	{
		return ResourceLabelGroupQuery::create()->filterByGroupId($this->getId())->filterByTreeLevel($level)->filterByIsUserFolder(false)->find();
	}

    /**
     * Renvoie la fin de libellé pour la dockbar
     */
    public function getLabelForDockBar()
    {
        switch($this->getType())
        {
            case "CLASSROOM":
                return "de ma classe";
                break;
            case "SCHOOL":
                return "de mon école";
            case "CITY":
                return "de ma ville";
            case "CIRCONSCRIPTION":
                return "de ma circonscription";
            case "TEAM":
                return "de mon équipe";
            default:
                return "";
        }
    }

    public function getLevelsString()
    {
        $str = "";
        $levels = $this->getAttribute('LEVEL');

        foreach($levels as $level)
        {
            $str .= $level . ' ';
        }

        return trim($str);
    }

    /*
     * Fonctions liées à l'avatar
     */
    public function hasAvatar()
	{
		$hasAvatar = ($this->getAttribute('AVATAR_ID') != 0)? true : false;

		return $hasAvatar;
	}

    public function getAvatarUrl()
    {
        return BNSAccess::getContainer()->get('twig.extension.resource')->getAvatar($this);
    }

    public function isPartnerShip()
    {
        return $this->getGroupType()->getType() == "PARTNERSHIP";
    }

    public function getApp()
    {
        return $this->app;
    }

    public function setApp(Module $app)
    {
        $this->app = $app;

        return $this;
    }

    public function getPaasIdentifier()
    {
        return $this->getId();
        /**
         * old way
        if($this->getGroupType()->getType() == 'SCHOOL')
        {
            $value = $this->getAttribute('UAI');
            if($value == null || $value =='')
            {
                $value = $this->getId();
            }
            return $value;
        }elseif($this->getGroupType()->getType() == 'CLASSROOM'){
            return $this->getId();
        }
         */
    }

    public function getPaasType(){
        return $this->getGroupType()->getType();
    }

    /**
     * @deprecated do not use anymore
     */
    public function tagPremium()
    {
        $this->setIsPremium(true);
        $this->save();
    }

    /**
     * @deprecated do not use anymore
     */
    public function untagPremium()
    {
        $this->setIsPremium(false);
        $this->save();
    }

    /**
     * @deprecated do not use anymore
     */
    public function isPremium()
    {
        return $this->getIsPremium() == true;
    }

    /**
     * @deprecated do not use anymore
     */
    public function togglePremium()
    {
        if($this->isPremium())
        {
            $this->untagPremium();
        }else{
            $this->tagPremium();
        }
    }

	/**
	 * Permet de savoir si on doit afficher le lien du profil dans l'annuaire
	 * @param $val
	 */
	public function setCanSeeProfile($val)
	{
		$this->canSeeProfile = $val;
	}

	public function getCanSeeprofile()
	{
		return $this->canSeeProfile;
	}

    /**
     * @inheritDoc
     */
    public function getSortedModules()
    {
        if (null === $this->sorted_modules) {
            $this->sorted_modules = '| ACCOUNT | PROFILE | MESSAGING | MEDIA_LIBRARY | USER_DIRECTORY | NOTIFICATION |';
        }

        return parent::getSortedModules();
    }

    /**
     * @inheritDoc
     */
    public function getFavoriteModules()
    {
        if (null === $this->favorite_modules) {
            $this->favorite_modules = '| ACCOUNT | PROFILE | MESSAGING | MEDIA_LIBRARY | USER_DIRECTORY | NOTIFICATION |';
        }

        return parent::getFavoriteModules();
    }


    /**
     * @inheritDoc
     */
    public function preSave(PropelPDO $con = null)
    {
        if ($this->isNew()) {
            if (null === $this->sorted_modules) {
                // Set default values
                $this->setSortedModules($this->getSortedModules());
            }
            if (null === $this->favorite_modules) {
                // Set default favorites values
                $this->setFavoriteModules($this->getSortedModules());
            }
        }


        return parent::preSave($con);
    }

    public function getUAI()
    {
        return $this->getAttribute('UAI');
    }

    public function getOndeId()
    {
        return $this->getAttribute('ONDE_ID');
    }

    public function getStatisticLabel()
    {
        if ($this->getType() !== 'SCHOOL') {
            return $this->getLabel();
        } else {
            return $this->getLabel() . ' - ' . $this->getAttribute('CITY');
        }
    }
}
