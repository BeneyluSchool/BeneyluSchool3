<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BNS\App\FixtureBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use BNS\App\TemplateBundle\Model\TemplateI18n;
use BNS\App\TemplateBundle\Model\TemplateEntityCollection;
use BNS\App\TemplateBundle\Model\Template;
use BNS\App\TemplateBundle\Model\TemplateType;
use BNS\App\TemplateBundle\Model\TemplateEntityI18n;
use BNS\App\TemplateBundle\Model\TemplateEntity;
use BNS\App\CoreBundle\Model\GroupTypeData;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplate;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplateI18n;
use BNS\App\CoreBundle\Model\GroupTypeDataTemplatePeer;
use BNS\App\CoreBundle\Model\GroupTypeDataChoice;
use BNS\App\CoreBundle\Model\GroupTypeDataChoiceI18n;
use BNS\App\CoreBundle\Model\ModulePeer;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\EmailTemplateI18n;
use BNS\App\CoreBundle\Model\EmailTemplate;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Propel;

/**
 * Create and load fixtures for BNS
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadInitCommand extends ContainerAwareCommand
{
	const USER_COUNT	= 10;
	const GROUP_COUNT	= 30;
	
	/**
	 * @var PropelPDO MySQL connexion
	 */
	private $con;
	
	/**
	 * @var array<User>
	 */
	private $users			= array();
	
	/**
	 * @var array<Module>
	 */
	private $modules				= array();
	
	/**
	 * @var array<>
	 */
	private $groupTypes				= array();

	/**
	 * @var array<TemplateType>
	 */
	private $templateTypes		= array();
	
	/**
	 * @var array<TemplateEntity>
	 */
	private $templateEntities		= array();

	/**
	 * @var array<Template>
	 */
	private $templates			= array();
	
	/**
	 * @var array<Group>
	 */
	private $environments			= array();
	
    protected function configure()
    {
        $this
            ->setName('bns:load-init')
            ->setDescription('Load Init datas ')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
			->setHelp('Note: les données des fixtures générées se trouvent dans BNS/App/FixtureBundle/Resources/data')
        ;
    }
	
	protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new \InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);
    }
	
	protected function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(array(
            '',
            $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
            '',
        ));
    }

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		list($connectionName, $defaultConfig) = $this->getConnection($input, $output);
		$this->con = Propel::getConnection($connectionName);
		Propel::setForceMasterConnection(true);
		
		try
		{
			//$this->con->beginTransaction();

			/**
			 * Chemin d'insertion pour ne pas avoir d'erreur de contrainte SQL :
			 * - User
			 * - Module
			 * - GroupTypeDataTemplates > (i18n)
			 * - GroupType > (i18n)
			 *   - GroupTypeData
			 *   - GroupTypeDataChoice
			 *   - GroupTypeModule
			 *   - Group
			 *   - GroupData
			 *   - GroupDataChoice
			 *   - GroupModule
			 */
			
			// Données essentielles
			$this->generateModules($input, $output);
			$this->generatePermissions($input, $output);
			$this->generateRanks($input, $output);
			$this->generateGroupTypeDataTemplates($input, $output);
			$this->generateGroupTypes($input, $output);
			$this->generateEmailTemplates($input, $output);
			$this->generateTemplateTypes($input, $output);
			$this->generateTemplateEntities($input, $output);
			$this->generateTemplates($input, $output);
			
			// Données fixtures
			$this->generateEnvironment($input, $output);			
			$this->generateRules($input, $output);
			
			
			//Création d'un admin
			
			$environmentGroup = GroupQuery::create()->useGroupTypeQuery()->filterByType('ENVIRONMENT')->endUse()->findOneByLabel($this->getContainer()->getParameter('domain_name'));
			
			$admin = $this->getContainer()->get('bns.user_manager')->createUser(
				array(
					'first_name'    => 'Eymeric',
					'last_name'		=> 'Taelman',
					'email'			=> 'eymeric.taelman@beneyluschool.com',
					'username'		=> 'eymericAdmin',
					'lang'			=> 'fr',
					'birthday'		=> new \DateTime()
				),true
			);
			
			$return = $this->getContainer()->get('bns.role_manager')->setGroupTypeRole(GroupTypeQuery::create()->findOneByType('ADMIN'))->assignRole($admin, $environmentGroup->getId());
			
			
			//$this->con->commit();
		}
		catch (Exception $e)
		{
			$this->con->rollBack();
            throw $e;
		}
    }
	
	
	/**
	 * Module
	 * 
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateModules(InputInterface $input, OutputInterface $output)
	{
		$modules		= Yaml::parse(__DIR__ . '/../Resources/data/Module/module.yml');
		$countModules	= count($modules);
		$this->writeSection($output, '[Propel] Generating ' . $countModules . ' modules...');
		$module = null;
		foreach ($modules as $moduleInfo)
		{
			$array_i18n = array();
			foreach ($moduleInfo['i18n'] as $lang => $moduleI18nInfo)
			{
				$array_i18n[$lang]['label'] = $moduleI18nInfo['label'];
				$array_i18n[$lang]['description'] = $moduleI18nInfo['description'];
			}
			
			$module = $this->getContainer()->get('bns.module_manager')->createModule(array(
				"unique_name"			=> $moduleInfo['unique_name'],
				"i18n"					=> $array_i18n,
				"is_contextable"		=> $moduleInfo['is_contextable'],
				"bundle_name"			=> $moduleInfo['bundle_name'],
				"default_parent_rank"	=> $moduleInfo['default_parent_rank'],
				"default_pupil_rank"	=> $moduleInfo['default_pupil_rank'],
				'default_other_rank'	=> $moduleInfo['default_other_rank'],
			));
			
			
			// Saving into object to avoid db access
			$this->modules[] = $module;
			// Cleaning memory
			$module = null;
		}
	}
	
	/**
	 * Permissions
	 * 
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generatePermissions(InputInterface $input, OutputInterface $output)
	{
		$permissions		= Yaml::parse(__DIR__ . '/../Resources/data/Permission/permission.yml');
		$count_permissions	= count($permissions);
		
		$this->writeSection($output, '[Propel] Generating ' . $count_permissions . ' permissions...');
		$permission = null;
		foreach ($permissions as $permission_info)
		{
			$array_i18n = array();
			
			if(isset($permission_info['module']))
				$module = ModuleQuery::create()->findOneByUniqueName($permission_info['module']);

			if($module)
			{
				$permission_info['module_id'] = $module->getId();
			}else{
				$permission_info['module_id'] = null;
			}	
			foreach ($permission_info['i18n'] as $lang => $permission_i18n_info)
			{
					$array_i18n[$lang]['label'] = $permission_i18n_info['label'];
					$array_i18n[$lang]['description'] = $permission_i18n_info['description'];
			}
			$permission = $this->getContainer()->get('bns.module_manager')->createPermission(array(
					"unique_name" => $permission_info['unique_name'],
					"i18n" => $array_i18n,
					"module_id" => $permission_info['module_id']
			));

			// Saving into object to avoid db access
			$this->permissions[] = $permission;
			// Cleaning memory
			$permission = null;
			
		}
	}
	
	/**
	 * Module
	 * 
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateRanks(InputInterface $input, OutputInterface $output)
	{
		$ranks		= Yaml::parse(__DIR__ . '/../Resources/data/Rank/rank.yml');
		$countRanks	= count($ranks);
		
		$this->writeSection($output, '[Propel] Generating ' . $countRanks . ' ranks ...');
		$rank = null;
		foreach ($ranks as $rankInfo)
		{
			
			$module = ModuleQuery::create()->findOneByUniqueName($rankInfo['module']);
			
			if($module){

				$array_i18n = array();	
				foreach ($rankInfo['i18n'] as $lang => $rankI18nInfo)
				{
					$array_i18n[$lang]['label'] = $rankI18nInfo['label'];
					$array_i18n[$lang]['description'] = $rankI18nInfo['description'];
				}
				
				$rank = $this->getContainer()->get('bns.module_manager')->createRank(array(
					"unique_name" => $rankInfo['unique_name'],
					"i18n" => $array_i18n,
					"module_id" => $module->getId(),
					"permissions" => $rankInfo['permissions']
				));

				// Saving into object to avoid db access
				$this->ranks[] = $rank;

				// Cleaning memory
				$rank = null;
			}
		}
	}
	
	private function generateEnvironment(InputInterface $input, OutputInterface $output)
	{
		$environments		= Yaml::parse(__DIR__ . '/../Resources/data/Environment/environment.yml');
		$countEnvironment	= count($environments);
		
		$this->writeSection($output, '[Propel] Generating ' . $countEnvironment . ' environments ...');
		$environment = null;
		foreach ($environments as $environmentInfo)
		{	
			$environment = $this->getContainer()->get('bns.group_manager')->createEnvironment(array(
				'label' => $environmentInfo['label']
			));
			
			// Saving into object to avoid db access
			$this->environments[] = $environment;
			
			// Cleaning memory
			$environment = null;
		}
	}
	
	private function generateRules(InputInterface $input, OutputInterface $output)
	{
		$rules		= Yaml::parse(__DIR__ . '/../Resources/data/Rule/role_group_type.yml');
		$countRules	= count($rules);
		
		$this->writeSection($output, '[Propel] Generating ' . $countRules . ' rules ...');
		
//		$this->getContainer()->get('bns.api')->send(
//			'rule_create', array('values' => array(
//				'who_group'		=> array(
//					'id'	=> $this->environments[0]->getId()
//				),
//				'rule_where'	=> array(
//					'group_id'			=> $this->environments[0]->getId(),
//					'belongs'			=> false
//				),
//				'state'			=> true,
//				'rank_unique_name'	=> 'BLOG_MANAGE'
//			))
//		);

		foreach($rules as $ruleInfo)
		{

			$whoGroupTypeRole = GroupTypeQuery::create()->findOneByType($ruleInfo['who']['group_type']);
			$whereGroupType = GroupTypeQuery::create()->findOneByType($ruleInfo['where']['group_type']);
			if ($whoGroupTypeRole == null || $whereGroupType == null)
			{
				throw \Exception('c\'est nul !');
			}
			
			foreach ($this->environments as $environment)
			{
				$values = array(
					'who_group'		=> array(
						'domain_id'			=> $this->getContainer()->getParameter('domain_id'),
						'group_type_id'		=> $whoGroupTypeRole->getId(),
						'group_parent_id'	=> $environment->getId()
					),
					'rule_where'	=> array(
						'group_id'			=> $environment->getId(),
						'group_type_id'		=> $whereGroupType->getId(),
						'belongs'			=> $ruleInfo['where']['belong']
					),
					'state'			=> $ruleInfo['state']
				);
				
				foreach ($ruleInfo['rank_unique_name']['value'] as $rankUniqueName)
				{
					$this->writeSection($output, '[Propel] Rang donne : ' . $rankUniqueName . '; domaine concerne : '.$environment->getLabel().'.');
					$values['rank_unique_name'] = $rankUniqueName;
					$this->getContainer()->get('bns.api')->send(
						'rule_create', array('values' => $values)
					);
				}
			}
		}
	}
	
	
	/**
	 * GroupTypeDataTemplate
	 * 
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateGroupTypeDataTemplates(InputInterface $input, OutputInterface $output)
	{
		$groupTypeDataTemplates			= Yaml::parse(__DIR__ . '/../Resources/data/GroupTypeDataTemplate/group_type_data_template.yml');
		$countGroupTypeDataTemplates	= count($groupTypeDataTemplates);
		
		$this->writeSection($output, '[Propel] Generating ' . $countGroupTypeDataTemplates . ' type group data templates...');
		
		// Init
		$groupTypeDataTemplate = null;
		$groupTypeDataI18n = null;
		
		foreach ($groupTypeDataTemplates as $groupTypeDataTemplateInfo)
		{
			// Creating main class
			$groupTypeDataTemplate = new GroupTypeDataTemplate();
			$groupTypeDataTemplate->setUniqueName($groupTypeDataTemplateInfo['unique_name']);
			$groupTypeDataTemplate->setType($groupTypeDataTemplateInfo['type']);
			if(isset($groupTypeDataTemplateInfo['default_value']))
				if($groupTypeDataTemplateInfo['type'] == GroupTypeDataTemplatePeer::TYPE_SINGLE)
					$groupTypeDataTemplate->setDefaultValue($groupTypeDataTemplateInfo['default_value']);
			$groupTypeDataTemplate->save();
			
			// i18n process
			foreach ($groupTypeDataTemplateInfo['i18n'] as $lang => $groupTypeDataTemplateI18nInfo)
			{
				$groupTypeDataI18n = new GroupTypeDataTemplateI18n();
				$groupTypeDataI18n->setLang($lang);
				$groupTypeDataI18n->setUniqueName($groupTypeDataTemplate->getUniqueName());
				$groupTypeDataI18n->setLabel($groupTypeDataTemplateI18nInfo['label']);
				$groupTypeDataI18n->save();
				
				$groupTypeDataTemplate->addGroupTypeDataTemplateI18n($groupTypeDataI18n);
				
				// Cleaning memory
				$groupTypeDataI18n = null;
			}
			
			// Choices process
			if ($groupTypeDataTemplateInfo['type'] != GroupTypeDataTemplatePeer::TYPE_SINGLE && $groupTypeDataTemplateInfo['type'] != GroupTypeDataTemplatePeer::TYPE_TEXT)
			{
				foreach ($groupTypeDataTemplateInfo['choices'] as $groupTypeDataTemplateChoiceInfo)
				{
					// Creating main class
					$groupTypeDataTemplateChoice = new GroupTypeDataChoice();
					$groupTypeDataTemplateChoice->setGroupTypeDataTemplateUniqueName($groupTypeDataTemplate->getUniqueName());
					$groupTypeDataTemplateChoice->setValue($groupTypeDataTemplateChoiceInfo['value']);
					$groupTypeDataTemplateChoice->save();
					
					if(isset($groupTypeDataTemplateInfo['default_value']))
						if($groupTypeDataTemplateChoiceInfo['value'] == $groupTypeDataTemplateInfo['default_value']){
							$groupTypeDataTemplate->setDefaultValue($groupTypeDataTemplateChoice->getId());
							$groupTypeDataTemplate->save();
						}

					// i18n choices process
					foreach ($groupTypeDataTemplateChoiceInfo['i18n'] as $lang => $groupTypeDataTemplateChoiceI18nInfo)
					{
						$groupTypeDataTemplateChoiceI18n = new GroupTypeDataChoiceI18n();
						$groupTypeDataTemplateChoiceI18n->setId($groupTypeDataTemplateChoice->getId());
						$groupTypeDataTemplateChoiceI18n->setLang($lang);
						$groupTypeDataTemplateChoiceI18n->setLabel($groupTypeDataTemplateChoiceI18nInfo['value']);
						$groupTypeDataTemplateChoiceI18n->save();

						// Adding into main class
						$groupTypeDataTemplateChoice->addGroupTypeDataChoiceI18n($groupTypeDataTemplateChoiceI18n);
						
						// Cleaning memory
						$groupTypeDataTemplateChoiceI18n = null;
					}

					// Cleaning memory
					$groupTypeDataTemplateChoice = null;
				}
			}
			// Cleaning memory
			$groupTypeDataTemplate = null;
		}
	}
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateGroupTypes(InputInterface $input, OutputInterface $output)
	{
		$groupTypes			= Yaml::parse(__DIR__ . '/../Resources/data/GroupType/grouptype.yml');
		$countGroupTypes	= count($groupTypes);
		
		$this->writeSection($output, '[Propel] Generating ' . $countGroupTypes . ' group types ...');
		
		// Init
		$groupType = null;
		$groupTypeI18n = null;
		$groupTypeData = null;
		$groupTypeModule = null;
		
		foreach ($groupTypes as $groupTypeInfo)
		{
			// Creating main class
			$groupTypeParams = array(
				'type'			=> $groupTypeInfo['type'],
				'centralize'	=> $groupTypeInfo['centralize'],
				'label'			=> $groupTypeInfo['i18n']['fr']['label'],
				'simulate_role'	=> $groupTypeInfo['simulate_role']
			);
			if (isset($groupTypeInfo['is_recursive'])) {
				$groupTypeParams['is_recursive'] = $groupTypeInfo['is_recursive'];
			}
			
			$groupType = $this->getContainer()->get('bns.group_manager')->createGroupType($groupTypeParams);
					
			foreach ($groupTypeInfo['data'] as $groupTypeDataTemplateUniqueName)
			{
				$groupTypeData = new GroupTypeData();
				$groupTypeData->setGroupTypeId($groupType->getId());
				$groupTypeData->setGroupTypeDataTemplateUniqueName($groupTypeDataTemplateUniqueName);
				$groupTypeData->save();
				
				// Adding GroupTypeData into main class
				$groupType->addGroupTypeData($groupTypeData);
				
				// Cleaning memory
				$groupTypeData = null;
			}
			
			// Avoid access to DB
			$this->groupTypes[] = $groupType;
			
			// Cleaning memory
			$groupType = null;
		}
	}
	
	
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateNotifications(InputInterface $input, OutputInterface $output)
	{
		$this->writeSection($output, '[Propel] Generating notification types...');
		
		$module = ModuleQuery::create()
			->add(ModulePeer::UNIQUE_NAME, 'BLOG')
		->findOne();
		$notificationType = new NotificationType();
		$notificationType->setModuleId($module->getId());
		$notificationType->setUniqueName('DEMO_NEW_COMMENT');
		$notificationType->save();
		
		$notificationType = new NotificationType();
		$notificationType->setModuleId($module->getId());
		$notificationType->setUniqueName('DEMO_NEW_ARTICLE');
		$notificationType->save();
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateTemplateTypes(InputInterface $input, OutputInterface $output)
	{
		$templateTypes		= Yaml::parse(__DIR__ . '/../Resources/data/TemplateType/template_type.yml');
		$countTemplateTypes	= count($templateTypes);
		
		$this->writeSection($output, '[Propel] Generating ' . $countTemplateTypes . ' profile template types...');
		
		// Init
		$templateType = null;
		foreach ($templateTypes as $templateTypeInfo)
		{
			// Creating main class
			$templateType = new TemplateType();
			$templateType->setBundleName($templateTypeInfo['bundle_name']);
			$templateType->save();
			
			// Saving into object to avoid db access
			$this->templateTypes[] = $templateType;
			
			// Cleaning memory
			$templateType = null;
		}
	}	
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output 
	 */
	private function generateTemplateEntities(InputInterface $input, OutputInterface $output)
	{
		$templateEntities		= Yaml::parse(__DIR__ . '/../Resources/data/TemplateEntity/template_entity.yml');
		$countTemplateEntities	= count($templateEntities);
		
		$this->writeSection($output, '[Propel] Generating ' . $countTemplateEntities . ' profile template entities...');
		
		// Init
		$templateEntity = null;
		foreach ($templateEntities as $templateEntityInfo)
		{
			// Creating main class
			$templateEntity = new TemplateEntity();
			$templateEntity->setCssClass($templateEntityInfo['css_class']);
			$templateEntity->setType($templateEntityInfo['type']);
			if ('FONT' == $templateEntityInfo['type'])
			{
				$templateEntity->setData(array('font_url' => $templateEntityInfo['data']));
			}
			
			$templateEntity->save();
			
			// i18n process
			foreach ($templateEntityInfo['i18n'] as $lang => $templateEntityI18nInfo)
			{
				$templateEntityI18n = new TemplateEntityI18n();
				$templateEntityI18n->setId($templateEntity->getId());
				$templateEntityI18n->setLang($lang);
				$templateEntityI18n->setLabel($templateEntityI18nInfo['label']);
				$templateEntityI18n->save();
				
				// Adding i18n into main class
				$templateEntity->addTemplateEntityI18n($templateEntityI18n);
				
				// Cleaning memory
				$templateEntityI18n = null;
			}
			
			// Saving into object to avoid db access
			$this->templateEntities[] = $templateEntity;
			
			// Cleaning memory
			$templateEntity = null;
		}
	}
	
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	private function generateTemplates(InputInterface $input, OutputInterface $output)
	{
		$templates	    = Yaml::parse(__DIR__ . '/../Resources/data/Template/template.yml');
		$countTemplates	= count($templates);
	
		$this->writeSection($output, '[Propel] Generating ' . $countTemplates . ' profile templates ...');
	
		// Init
		$template = null;
		$templateI18n = null;
	
		foreach ($templates as $templateInfo)
		{
			// Creating main class
			$template = new Template();
			$template->setCssClass($templateInfo['css_class']);
			foreach ($this->templateTypes as $templateType)
			{
				if ($templateInfo['template_type'] == $templateType->getBundleName())
				{
					$template->setTemplateTypeId($templateType->getId());
				}
			}
			
			$template->save();
	
			// TemplateEntityCollection process
			foreach ($templateInfo['entities'] as $templateEntityCollectionName)
			{
				$templateEntity = null;
				foreach ($this->templateEntities as $currentTemplateEntity)
				{
					if (strstr($currentTemplateEntity->getTranslation('en')->getLabel(), $templateEntityCollectionName))
					{
						$templateEntity = $currentTemplateEntity;
						break;
					}
				}
	
				if ($templateEntity == null)
				{
					$this->writeSection($output, '[ERROR] No profile template entity found for the profile template entity collection name : ' . $templateEntityCollectionName);
					continue;
				}
	
				$templateEntityCollection = new TemplateEntityCollection();
				$templateEntityCollection->setTemplateId($template->getId());
				$templateEntityCollection->setTemplateEntityId($templateEntity->getId());
				$templateEntityCollection->save();
	
				// Adding GroupTypeModule into main class
				$template->addTemplateEntityCollection($templateEntityCollection);
	
				// Cleaning memory
				$templateEntity = null;
			}
	
			// i18n process
			foreach ($templateInfo['i18n'] as $lang => $templateI18nInfo)
			{
				$templateI18n = new TemplateI18n();
				$templateI18n->setId($template->getId());
				$templateI18n->setLang($lang);
				$templateI18n->setLabel($templateI18nInfo['label']);
				$templateI18n->save();
	
				// Adding into main class
				$template->addTemplateI18n($templateI18n);
	
				// Cleaning memory
				$templateI18n = null;
			}
			
			// Saving into object to avoid db access
			$this->templates[] = $template;
			
			// Cleaning memory
			$template = null;
		}
	}
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	private function generateEmailTemplates(InputInterface $input, OutputInterface $output)
	{
		$emailTemplates			= Yaml::parse(__DIR__ . '/../Resources/data/EmailTemplate/email_template.yml');
		$countEmailTemplates	= count($emailTemplates);
		
		$this->writeSection($output, '[Propel] Generating ' . $countEmailTemplates . ' email templates...');
		
		// Init
		$emailTemplate = null;
		
		foreach ($emailTemplates as $uniqueName => $emailTemplateInfo)
		{
			// Creating main class
			$emailTemplate = new EmailTemplate();
			$emailTemplate->setUniqueName($uniqueName);
			$emailTemplate->setVars($emailTemplateInfo['vars']);
			$emailTemplate->save();
			
			// i18n process
			foreach ($emailTemplateInfo['i18n'] as $lang => $emailTemplateI18nInfo)
			{
				$emailTemplateI18n = new EmailTemplateI18n();
				$emailTemplateI18n->setUniqueName($emailTemplate->getUniqueName());
				$emailTemplateI18n->setLang($lang);
				$emailTemplateI18n->setSubject($emailTemplateI18nInfo['subject']);
				$emailTemplateI18n->setLabel($emailTemplateI18nInfo['label']);
				$emailTemplateI18n->setHtmlBody(file_get_contents(__DIR__ . '/../Resources/data/EmailTemplate/htmls/' . $emailTemplate->getUniqueName() . '_' . $lang . '.html'));
				$emailTemplateI18n->setPlainBody(file_get_contents(__DIR__ . '/../Resources/data/EmailTemplate/plains/' . $emailTemplate->getUniqueName() . '_' . $lang . '.txt'));
				
				// Finally
				$emailTemplateI18n->save();
				
				// Adding i18n into main class
				$emailTemplate->addEmailTemplateI18n($emailTemplateI18n);
				
				// Cleaning memory
				$emailTemplateI18n = null;
			}
			
			$emailTemplate->save();
			
			// Cleaning memory
			$emailTemplate = null;
		}
	}
}