<?php
namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseGroupType;
use BNS\App\CoreBundle\Translation\TranslatorTrait;
use Criteria;
use PropelPDO;
use JMS\TranslationBundle\Annotation\Ignore;

/**
 * Skeleton subclass for representing a row from the 'group_type' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.Work/Beneyluschool3/src/BNS/App/CoreBundle/Model
 */
class GroupType extends BaseGroupType
{
    use TranslatorTrait;

	private $attributes;

    /**
	 * @var array<Module> type group's modules
	 */
    private $modules;

	/**
	 * @return string
	 */
    public function __toString()
	{
		return $this->getLabel();
	}

    /**
	 * Permet d'obtenir la liste des attributs du type de groupe $this
	 *
	 * @return    $label_type tableau qui contient les attributs triés par type (SINGLE, ONE_CHOICE, MULTIPLE_CHOICE)
	 */
    public function getGroupTypeDataArray()
    {
		$groupTypeDatas = $this->getGroupTypeDatas();

		$label_type = array();
		foreach ($groupTypeDatas as $groupTypeData)
		{
			$data = $groupTypeData->getGroupTypeDataTemplate();
			$label_type[$data->getType()][] = $data->getLabel();
		}

		return $label_type;
    }

	/**
	 * @param type $uniqueName
	 *
	 * @return type
	 */
	public function getGroupTypeDataByUniqueName($uniqueName)
	{
		$c = new Criteria();
		$c->add(GroupTypeDataPeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME,$uniqueName);

		return $this->getGroupTypeDatas($c)->getFirst();
	}

	/**
	 * @param \ModelCriteria $query
	 * @param PropelPDO $con
	 *
	 * @return array<GroupTypeData> Tous les GroupTypeData avec leur template.
	 */
    public function getFullGroupTypeDatas($query = null, PropelPDO $con = null)
    {
		if (!isset($this->collGroupTypeDatas)) {
			if (null == $query) {
				$query = GroupTypeDataQuery::create();
			}

			$query->joinWith('GroupTypeDataTemplate')
			;

			$this->collGroupTypeDatas = $this->getGroupTypeDatas($query, $con);
		}

		return $this->collGroupTypeDatas;
    }

    /**
     * Vérifie si le type de groupe $this possède l'attribut $uniqueName
     *
     * @param String $uniqueName
     *
     * @return 	soit null si l'attribut n'existe pas pour le type de groupe $this;
     * 			soit l'objet GroupTypeDataTemplate associé à l'attribut $uniqueName
     */
    public function hasAttribute($uniqueName)
    {
    	return in_array($uniqueName, $this->getAttributes());
    }

	/**
	 * @return array<String>
	 */
	public function getAttributes()
	{
		if (!isset($this->attributes)) {
			$attrs = GroupTypeDataQuery::create()->filterByGroupTypeId($this->getId())->find();
			$attrsArray = array();

			foreach ($attrs as $attr) {
				$attrsArray[] = $attr->getGroupTypeDataTemplateUniqueName();
			}

			$this->attributes = $attrsArray;
		}

		return $this->attributes;
	}

    /*
     * Renvoie les attributs non settés au type de groupe (qu'il n'a pas)
     */
    public function getNotSetAttributes()
    {
        return GroupTypeDataTemplateQuery::create()
            ->filterByUniqueName(GroupTypeDataTemplateQuery::create()
                ->useGroupTypeDataQuery()
                ->filterByGroupTypeId($this->getId())
                ->groupBy(GroupTypeDataPeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME)
                ->endUse()
                ->find()->getPrimaryKeys(),\Criteria::NOT_IN)
            ->find();
    }

	/**
	 * Reload des attributs
	 */
	public function reloadAttributes(){
		unset($this->attributes);
	}

	/**
	 * Ajoute un groupTypeData à partir de son UniqueName
	 * @param $uniqueName UniqueName du GroupTypeDataTemplate à ajouter
	 */
	public function addGroupTypeDataByUniqueName($uniqueName)
	{
		if( !GroupTypeDataTemplateQuery::create()->filterByUniqueName($uniqueName)->findOne() ) {
			throw new \Exception("This attribute does not exist");
		}
		$groupTypeData = GroupTypeDataQuery::create()
			->filterByGroupTypeId($this->getId())
			->filterByGroupTypeDataTemplateUniqueName($uniqueName)
			->findOneOrCreate();

		if( $groupTypeData->isNew() ) {
			$groupTypeData->save();
		}
		$this->reload(true);
	}

	/**
	 * Simple shortcut
	 *
	 * @return boolean
	 */
	public function isSimultateRole()
	{
		return $this->getSimulateRole();
	}

	/**
	 * @param array<Module> $modules
	 */
	public function setModules($modules)
	{
		$this->modules = $modules;
	}

	/**
	 * @return array<Module>
	 */
	public function getModules($isContextable = null)
	{
		if (!isset($this->modules)) {
			$this->modules = BNSAccess::getContainer()->get('bns.right_manager')->getContextModules($this->getId());
		}

		if (null !== $isContextable) {
			$modules = array();
			foreach ($this->modules as $module) {
				if ($module->isContextable() === $isContextable) {
					$modules[$module->getUniqueName()] = $module;
				}
			}

			return $modules;
		}

		return $this->modules;
	}

    /**
     * @deprecated
     * @return string
     */
    public function getSlug()
    {
        return $this->getType();
    }

    public function getLabel($locale = null)
    {
        $translator = $this->getTranslator();
        if (!$translator) {
            return $this->getLabelToken();
        }

        /** @Ignore */
        return $translator->trans($this->getLabelToken(), array(), 'GROUP_TYPE', $locale);
    }

    public function getLabelToken()
    {
        return 'LABEL_' . $this->getType();
    }

    public function setLabel($v)
    {
        return $this;
    }

    public function setSlug($v)
    {
        return $this;
    }

    public function getDescription()
    {
        return "";
    }
}
