<?php

namespace BNS\App\CoreBundle\Form\Type;

use BNS\App\CoreBundle\Model\GroupTypeDataChoiceI18nPeer;

use BNS\App\CoreBundle\Model\GroupTypeDataChoicePeer;

use BNS\App\CoreBundle\Model\GroupTypeDataChoiceQuery;

use BNS\App\CoreBundle\Access\BNSAccess;

use BNS\App\CoreBundle\Model\GroupTypeDataTemplateI18nPeer;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupTypePeer;
use BNS\App\CoreBundle\Model\GroupDataChoice;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use BNS\App\CoreBundle\Model\GroupTypeDataQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Form\Type\IEmbeddedFormType;

/**
 * @author Eric
 * 26/01/2012 - Eric Chau
 *
 * TODO : trouver une meilleure solution pour les formulaires
 * Actuellement, nous ne sommes pas capable de créer des formulaires complexes en utilisant les formulaires imbriqués;
 * J'ai donc créé un formulaire qui est générique (qui s'adapte aux différents types de groupe et donc aux attributs qui différent
 * d'un groupe à l'autre) mais nécessite de passer par une méthode de conversion d'un groupe en un tableau compréhensible par le formulaire
 * CustomGroupType et ainsi pouvoir pré-remplir le formulaire du groupe;
 *
 * Problématique rencontré lié au formulaire :
 *
 * Après de longues heures de réflexion et de recherche avec Eymeric et Sylvain, nous ne sommes pas parvenus
 * à faire un formulaire d'édition/création d'un groupe où chaque GroupData, GroupDataChoice (et ainsi de suite...)
 * du groupe soit un formulaire imbriqué du formulaire principal GroupType. Plusieurs raisons à cela :
 * - On ne peut pas avoir accès aux données $options['data'] ailleurs que dans le formulaire père (dans notre cas, GroupType);
 * 	 dans les formulaires enfants, la variable $options['data'] est tout simplement égal à null
 * - Le fait que l'on souhaite utiliser les formulaires qui sont liés à des objets nous empêche d'ajouter des champs qui ne sont
 *   pas des attributs de l'objet sur lequel porte le formulaire (ex.: Group, GroupData, GroupDataChoice, etc.)
 * - Avoir des formulaires imbriqués est difficilement gérable concernant les GroupData de type 'ONE_CHOICE' ou 'MULTIPLE_CHOICE';
 *   En effet, chaque valeurs différentes pour l'attribut 'LEVEL' par exemple sera contenu séparément dans des formulaires fils
 *   différents (car chaque objet différent fait l'objet d'un nouveau formulaire fils; dans notre cas, si nous avons les 'LEVEL'
 *   'CM1' et 'CM2', nous aurons deux formulaires différents (GroupTypeDataChoiceType) qui contiendront chacun une seule checkbox
 *   avec les valeurs respectives 'CM1' et 'CM2'
 * - Un dernier problème a également été relevé, c'est qu'en 'passant' par les GroupDatas d'un groupe, on n'obtient seulement que les
 * 	 valeur d'attribut du groupe en question et non tous les choix possibles offert à l'utilisateur (qui sont accessible depuis
 * 	 GroupTypeDataTemplate); surtout, comment peut-on distingué les valeurs propres à l'attribut du groupe et les valeurs possibles
 * 	 pour l'attribut ?
 */
class CustomGroupType extends AbstractType
{
	/**
	 * Correspond au type de groupe du CustomGroupType ($groupType doit contenir un GroupTypePeer::TYPE_xxx_INTEGER;
	 * nous n'utilisons pour le moment pas les GroupTypePeer::Type_xxx car Propel n'est pas capable de convertir la
	 * chaîne de caractère en un INT)
	 *
	 * @var GroupTypePeer::TYPE_xxx_INTEGER $groupType
	 */
	private $groupType;
	
	/**
	 * Tous les attribtus contenus dans ce tableau seront les seuls attributs du groupe éditables pour le formulaire que vous
	 * souhaitez créé; si ce tableau est vide alors tous les attributs du groupe seront éditables
	 *
	 * @var array $usedAttributes
	 */
	private $usedAttributes;
	
	/**
	 * Contient la liste des GroupTypeData (= attributs) que l'on souhaite éditer à l'aide de notre formulaire (CustomGroupType);
	 * cette liste ne contiendra pas les GroupTypeData associés aux attributs que nous souhaitons ignorer lors de l'édition;
	 * le tri est fait une seule fois lorsque l'on appelle pour la première fois la méthode getCustomGroupGroupTypeDatas();
	 *
	 * @var array $groupTypeDatas
	 */
	private $groupTypeDatas;
	
	/**
	 * @var array Tableau contenant les formulaires imbriquées au formulaire principal (CustomGroupType); ces sous formulaires doivent
	 * implémenter l'interface IEmbeddedFormType
	 */
	private $additionalEmbeddedForms;
	
	/**
	 * 
	 * @param GroupTypePeer::TYPE_xxx_INTEGER $groupType
	 * @param array $usedAttributes tableau contenant les attributs  dont on ne souhaite pas qu'ils soient éditables
	 * 				(il faut le UniqueName de chaque attribut) (si un tableau vide est donné alors tous les attributs du groupe seront éditables)
	 * @param array $additionalEmbeddedForms tableau contenant les formulaires que l'on souhaite imbriquer dans le formulaire
	 * 				CustomGroupType que vous êtes en train de créé; /!\ attention, il faut indexer les formulaires selon l'input name
	 * 				de votre choix; c'est à l'aide de ce dernier que vous pourrez identifier ces derniers lors que vous ferez un
	 * 				$form->getData(); Il faut également que tous les sous formulaires implémente l'interface IEmbeddedFormType
	 */
	public function __construct($groupType, array $usedAttributes = array(), array $additionalEmbeddedForms = array())
	{
		$this->groupType = $groupType;
		$this->usedAttributes = $usedAttributes;
		
		foreach ($additionalEmbeddedForms as $embedForm)
		{
			if (!$embedForm instanceof IEmbeddedFormType)
			{
				throw new RuntimeException('Embed form must implement the IEmbeddedFormType interface !');
			}
		}
		
		$this->additionalEmbeddedForms = $additionalEmbeddedForms;
	}


	/**
	 * (non-PHPdoc)
	 * @see Symfony\Component\Form.AbstractType::buildForm()
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
	{
		foreach ($this->getCustomGroupGroupTypeDatas() as $groupTypeData)
		{
			$groupTypeDataTemplate = $groupTypeData->getGroupTypeDataTemplate();
			$typeOfData = $groupTypeDataTemplate->getType();

			// On test si l'attribut est du type SINGLE
			if ($typeOfData == GroupTypeDataTemplatePeer::TYPE_SINGLE)
			{
				$uniqueName = $groupTypeDataTemplate->getUniqueName();
				$builder->add($uniqueName, 'text', array(
		    		'label'		=> $groupTypeDataTemplate->getLabel(),
		    		'required'	=> true,
		    	));
			}
			// On test si l'attribut est du type TEXT
			else if ($typeOfData == GroupTypeDataTemplatePeer::TYPE_TEXT)
			{
				$uniqueName = $groupTypeDataTemplate->getUniqueName();
				$builder->add($uniqueName, 'textarea', array(
	    			'label'		=> $groupTypeDataTemplate->getLabel(),
	    			'required'	=> true,
	    		));
			}
			// Arrivé ici, on sait que l'attribut est ni du type SINGLE, ni du type TEXT, c'est soit ONE_CHOICE ou MULTIPLE_CHOICE
			else
			{
				// Dans les deux cas, nous devons construire le tableau qui contient toutes les valeurs possibles de l'attribut
				$choicesArray = array();
				$groupTypeDataChoices = GroupTypeDataChoiceQuery::create()
					->joinWith('GroupTypeDataChoiceI18n')
					->add(GroupTypeDataChoicePeer::GROUP_TYPE_DATA_TEMPLATE_UNIQUE_NAME, $groupTypeDataTemplate->getUniqueName())
					->add(GroupTypeDataChoiceI18nPeer::LANG, BNSAccess::getLocale())
				->find();
				
				foreach ($groupTypeDataChoices as $groupTypeDataChoice)
				{
					$choicesArray[$groupTypeDataChoice->getId()] = $groupTypeDataChoice->getValue();
				}
				
				// Si l'attribut est du type MULTIPLE_CHOICE, nous devons présenter les valeurs possibles sous forme d'une liste de checkbox
				if ($typeOfData == GroupTypeDataTemplatePeer::TYPE_MULTIPLE_CHOICE)
				{
					$builder->add($groupTypeDataTemplate->getUniqueName(), 'choice', array(
			    		'choices' 		=> $choicesArray,
			    		'required'		=> false,
			    		'empty_value' 	=> false,
			    		'multiple'		=> true, 
			    		'expanded'		=> true,
			    		'label'			=> $groupTypeDataTemplate->getLabel(),
			    	));
				}
				// Sinon l'attribut est du type ONE_CHOICE, nous devons présenter les valeurs possibles sous forme d'une liste à un choix possible
				else
				{
					$builder->add($groupTypeDataTemplate->getUniqueName(), 'choice', array(
	    				'choices' 		=> $choicesArray,
	    				'required'		=> false,
	    				'empty_value' 	=> false,
	    				'label'			=> $groupTypeDataTemplate->getLabel(),
	    			));
				}
			}
		} // fin du foreach
		 
		if (count($this->additionalEmbeddedForms) > 0)
		{
			foreach ($this->additionalEmbeddedForms as $key => $form)
			{
				$builder->add($key, $form);
			}
		}
	}

	/**
	 * Convertit l'objet $group passé en paramètre en un tableau de données exploitable par le formulaire
	 * CustomGroupType; est nécessaire pour pouvoir pré-populer le formulaire avec les informations du groupe $group
	 *
	 * @param Group $group est le groupe dont on souhaite utiliser les informations pour pré-remplir le formulaire
	 * 					   CustomGroupType
	 */
	public function convertGroupToCustomGroupTypeArray(Group $group)
	{
		/**
		 * On récupère dans $attributesValuesIdArray la liste des valeurs d'attributs ainsi que leur id
		 * Le tableau est indexé par la valeur des attributs
		 *
		 * Ce tableau sert à récupérer les id des valeurs d'attributs de type ONE_CHOICE ou MULTIPLE_CHOICE
		 */
		$attributesValuesIdArray = array();
		foreach ($group->getFullGroupDatas() as $groupData)
		{
			if ($groupData->getType() != GroupTypeDataTemplatePeer::TYPE_SINGLE && $groupData->getType() != GroupTypeDataTemplatePeer::TYPE_TEXT)
			{
				foreach ($groupData->getGroupDataChoices() as $groupDataChoice)
				{
					$choiceI18ns = $groupDataChoice->getGroupTypeDataChoice()->getGroupTypeDataChoiceI18ns();
					$attributesValuesIdArray[$choiceI18ns[0]->getValue()] = $choiceI18ns[0]->getId();
				}
			}
		}
		 
		/**
		 * On créé maintenant le tableau correspondant au pattern CustomGroupType; on rempli ensuite ce tableau
		 * à partir des données propres au groupe passé en paramètre ($group)
		 */
		$groupTypeArray = array();
		foreach ($this->getCustomGroupGroupTypeDatas() as $groupTypeData)
		{
			/**
			 * On vérifie que le type de l'attribut dont on souhaite récupérer la valeur n'est pas du type SINGLE ou TEXT
			 * Si c'est du type SINGLE, nous n'avons pas besoin de chercher son id dans le tableau $attributesValuesIdArray
			 */
			if ($groupTypeData->getGroupTypeDataTemplate()->getType() != GroupTypeDataTemplatePeer::TYPE_SINGLE && $groupTypeData->getGroupTypeDataTemplate()->getType() != GroupTypeDataTemplatePeer::TYPE_TEXT)
			{
				/**
				 * Si l'attribut dont on souhaite récupérer la (ou les) valeur(s) est de type :
				 * 		MULTIPLE_CHOICE, alors $values sera un tableau qui contient toutes les valeurs
				 * 		de l'attribut pour le groupe $group
				 *
				 * 		ONE_CHOICE, alors $values n'est pas un tableau mais une string qui correspond à la
				 * 		valeur de l'attribut du groupe $group
				 */
				$values = $group->getAttribute($groupTypeData->getGroupTypeDataTemplateUniqueName());
				if (is_array($values)) // MULTIPLE_CHOICE
				{
					$valuesArray = array();
					foreach ($values as $value)
					{
						$valuesArray[$value] = $attributesValuesIdArray[$value];
					}
					$groupTypeArray[$groupTypeData->getGroupTypeDataTemplateUniqueName()] = $valuesArray;
				}
				else if (null == $values) // $values peut être égale à null, cela signifie qu'il n'y a pas de valeur pour l'attribut
				{
					continue;
				}
				else // ONE_CHOICE
				{
					$groupTypeArray[$groupTypeData->getGroupTypeDataTemplateUniqueName()] =  $attributesValuesIdArray[$values];
				}
			}
			else
			{
				$groupTypeArray[$groupTypeData->getGroupTypeDataTemplateUniqueName()] = $group->getAttributeValue($groupTypeData->getGroupTypeDataTemplateUniqueName());
			}
		}
		 
		return $groupTypeArray;
	}


	/**
	 * Sauvegarde le nouvel état de l'objet Group $group si ce dernier est différent de null, créé un nouveau
	 * groupe sinon (le type du groupe sera lui que l'on a spécifier au
	 * Le nouvel état de l'objet est stocké dans le tableau passé en paramètre (ce dernier étant généré
	 * par les informations contenus dans le formulaire CustomGroupType)
	 *
	 * @param array $groupTypeArray correspond au getData() du formulaire CustomGroupType
	 * 
	 * @return array un tableau contenant toutes les données des formulaires imbriqués qui n'ont pas de traitement
	 * 				 associé dans ce formulaire;
	 * 				 ils sont indexés par leur input name
	 */
	public function save($groupTypeArray, Group $group = null)
	{
		$embeddedFormsDatas = array();
		if (null == $group)
		{
			$group = Group::createGroup($this->groupType, $groupTypeArray['NAME']);
		}

		foreach ($groupTypeArray as $key => $value)
		{
			$groupData = $group->getGroupDataByGroupTypeDataTemplateUniqueName($key);
			if (null == $groupData)
			{
				$embeddedFormsDatas[$key] = $value;
				continue;
			}
			if (GroupTypeDataTemplatePeer::TYPE_SINGLE == $groupData->getType() || GroupTypeDataTemplatePeer::TYPE_TEXT == $groupData->getType())
			{
				$groupData->setValue($value);
				$groupData->save();
			}
			else if (GroupTypeDataTemplatePeer::TYPE_MULTIPLE_CHOICE == $groupData->getType())
			{
				$groupTypeDataChoiceIdFromForm = $value;
				$groupTypeDataChoiceIdFromBDArray = array();
				$groupDataChoices = $groupData->getGroupDataChoices();

				foreach ($groupDataChoices as $groupDataChoice)
				{
					$groupTypeDataChoiceIdFromBDArray[] = $groupDataChoice->getGroupTypeDataChoiceId();
				}

				$groupTypeDataChoiceIdToInsert = array_diff($groupTypeDataChoiceIdFromForm, $groupTypeDataChoiceIdFromBDArray);
				$groupTypeDataChoiceIdToDelete = array_diff($groupTypeDataChoiceIdFromBDArray, $groupTypeDataChoiceIdFromForm);

				if (count($groupTypeDataChoiceIdToInsert) > 0)
				{
					foreach ($groupTypeDataChoiceIdToInsert as $id)
					{
						$groupDataChoice = new GroupDataChoice();
						$groupDataChoice->setGroupDataId($groupData->getId());
						$groupDataChoice->setGroupTypeDataChoiceId($id);
						$groupDataChoice->save();
						$groupData->addGroupDataChoice($groupDataChoice);
					}
				}

				if (count($groupTypeDataChoiceIdToDelete) > 0)
				{
					foreach ($groupDataChoices as $groupDataChoice)
					{
						$keyToUnset;
						foreach ($groupTypeDataChoiceIdToDelete as $key => $id)
						{
							if ($id == $groupDataChoice->getGroupTypeDataChoiceId())
							{

								$groupDataChoice->delete();
								$keyToUnset = $key;
								break;
							}
						}
						
						if (isset($keyToUnset))
						{
							unset($groupTypeDataChoiceIdToDelete[$keyToUnset]);
						}
					}
				}
			}
			else // ONE_CHOICE
			{
				$groupDataChoices = $groupData->getGroupDataChoices();
				if (count($groupDataChoices) <= 0)
				{
					$groupDataChoice = new GroupDataChoice();
					$groupDataChoice->setGroupDataId($groupData->getId());
					$groupDataChoice->setGroupTypeDataChoiceId($value);
					$groupDataChoice->save();
					$groupData->addGroupDataChoice($groupDataChoice);
				}
				else if ($value != $groupDataChoices[0]->getGroupTypeDataChoiceId())
				{
					$groupDataChoices[0]->delete();
					$groupDataChoice = new GroupDataChoice();
					$groupDataChoice->setGroupDataId($groupData->getId());
					$groupDataChoice->setGroupTypeDataChoiceId($value);
					$groupDataChoice->save();
					$groupData->addGroupDataChoice($groupDataChoice);					
				}
			}
		}
		
		$group->save();
		
		// Ici on fait appel au save de tous les formulaires imbriqués; c'est pourquoi ces derniers doivent impérativement implémenter IEmbeddedFormType
		if (count($embeddedFormsDatas) > 0) 
		{
			foreach ($embeddedFormsDatas as $key => $embeddedFormDatas)
			{
				$this->additionalEmbeddedForms[$key]->save($embeddedFormDatas);
			}
		}
	}


	/**
	 * Renvoi tous les GroupTypeData qui sont associés GroupTypePeer::Type que l'on a reçu en paramètre du constructeur
	 * de notre CustomGroupType ($this->groupType);
	 * Cette méthode fait le tri sur les attributs que l'on souhaite éditer; cela signifie que si nous souhaitons ignorer
	 * certain attributs lors de l'édition de notre groupe, le tri s'effectue à ce moment précis et renverra seulement les
	 * attributs éditables
	 *
	 * Rappel : chaque GroupTypeData représente un attribut de notre type de groupe
	 */
	private function getCustomGroupGroupTypeDatas()
	{
		if (!isset($this->groupTypeDatas))
		{
			$groupTypeDatas = GroupTypeDataQuery::create()
				->joinWith('GroupTypeDataTemplate')
				->joinWith('GroupTypeDataTemplate.GroupTypeDataTemplateI18n')
				->joinWith('GroupType')
				->add(GroupTypeDataTemplateI18nPeer::LANG, BNSAccess::getLocale())
				->add(GroupTypePeer::TYPE, $this->groupType)
			->find();
			
			$groupTypeDatasNeeded = array();
			// On vérifie que le nombre de champ dans le formulaire souhaité par l'utilisateur est supérieur à 0
			if (count($this->usedAttributes) > 0)
			{
				// Arrivé ici, oui il y a des attributs à ajouter au formulaire, nous allons donc effectuer un tri
				foreach ($groupTypeDatas as $groupTypeData)
				{
					$groupTypeDataTemplateUniqueName = $groupTypeData->getGroupTypeDataTemplate()->getUniqueName();
					if (in_array($groupTypeDataTemplateUniqueName, $this->usedAttributes))
					{
						$groupTypeDatasNeeded[] = $groupTypeData;
						$i;
						foreach ($this->usedAttributes as $key => $attr)
						{
							if ($attr == $groupTypeDataTemplateUniqueName)
							{
								$i = $key;
								break;
							}
						}
						unset($this->usedAttributes[$i]);
					}
				}
				// On vérifie si des attributs inexistants ont été fourni; si oui, on lève une exception;
				if (count($this->usedAttributes) > 0)
				{
					throw new \Exception('Some given attribute(s) do(es) not exist!');
				}
			}
			else // ici, aucun attribut en particulier n'est spécifié, tous les attributs du groupe seront éditables à travers le formulaire
			{
				$groupTypeDatasNeeded = $groupTypeDatas;
			}

			$this->groupTypeDatas = $groupTypeDatasNeeded;
		}

		return $this->groupTypeDatas;
	}


	public function getName()
	{
		return 'custom_group_form';
	}
}