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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Propel;

use BNS\App\CoreBundle\Model\MiniSitePageText;
use BNS\App\CoreBundle\Model\MiniSitePageTextPeer;
use BNS\App\CoreBundle\Model\MiniSitePageNews;
use BNS\App\CoreBundle\Model\MiniSitePageNewsPeer;
use BNS\App\CoreBundle\Model\MiniSitePage;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\MiniSiteWidgetTemplateI18n;
use BNS\App\CoreBundle\Model\MiniSiteWidgetTemplate;
use BNS\App\CoreBundle\Model\MiniSiteQuery;

/**
 * Create and load fixtures for BNS
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadMiniSiteCommand extends ContainerAwareCommand
{
	/**
	 * @var PropelPDO MySQL connexion
	 */
	protected $con;
	
	protected function configure()
    {
        $this
            ->setName('bns:load-minisite')
            ->setDescription('Load MiniSite fixtures')
			->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'Connexion a utiliser')
        ;
    }
	
	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * 
	 * @return type
	 * 
	 * @throws InvalidArgumentException 
	 */
	protected function getConnection(InputInterface $input, OutputInterface $output)
    {
        $propelConfiguration = $this->getContainer()->get('propel.configuration');
        $name = $input->getOption('connection') ?: $this->getContainer()->getParameter('propel.dbal.default_connection');

        if (isset($propelConfiguration['datasources'][$name])) {
            $defaultConfig = $propelConfiguration['datasources'][$name];
        } else {
            throw new InvalidArgumentException(sprintf('Connection named %s doesn\'t exist', $name));
        }

        $output->writeln(sprintf('Use connection named <comment>%s</comment> in <comment>%s</comment> environment.',
            $name, $this->getApplication()->getKernel()->getEnvironment()));

        return array($name, $defaultConfig);
    }
	
	/**
	 * @param OutputInterface $output
	 * @param type $text
	 * @param type $style 
	 */
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
		
		$miniSiteData = Yaml::parse(__DIR__ . '/../Resources/data/MiniSite/minisite.yml');
		
		try
		{
			$this->con->beginTransaction();
			
			$this->writeSection($output, 'Creation des minisites.');
			
			// Récupération des minisites
			$miniSites = MiniSiteQuery::create()->find();
			
			foreach ($miniSites as $miniSite) {
				$miniSite->setDescription($miniSiteData['MINISTE']['description'][rand(0, count($miniSiteData['MINISTE']['description']) - 1)]);
				$miniSite->save();
			}
			
			// Récupération des utilisateurs
			$users = UserQuery::create()->find();
			
			// Inserts
			foreach ($miniSites as $miniSite) {
				// Création des MiniSitePage
				$miniSitePages = array();
				foreach ($miniSiteData['PAGES'] as $pageTitle => $page) {
					$miniSitePage = new MiniSitePage();
					$miniSitePage->setMiniSiteId($miniSite->getId());
					$miniSitePage->setTitle($pageTitle);
					$miniSitePage->setType($page['type']);
					$miniSitePage->setIsHome(isset($page['is_home']) ? $page['is_home'] : null);
					$miniSitePage->setIsActivated($page['is_activated']);
					$miniSitePage->save();
					
					$miniSitePages[] = $miniSitePage;
					$pageData = $page['child_page'];
					
					switch ($page['type']) {
						case 'TEXT':
							$pageText = new MiniSitePageText();
							$pageText->setPageId($miniSitePage->getId());
							$pageText->setAuthorId($users[rand(0, count($users) - 1)]->getId());
							$pageText->setStatus($pageData['status']);
							$pageText->setDraftContent($pageData['draft_content']);
							$pageText->setDraftTitle($pageData['draft_title']);
							
							if ($pageText->getStatus() == MiniSitePageTextPeer::STATUS_PUBLISHED) {
								$pageText->setPublishedAt($pageData['published_at']);
								$pageText->setPublishedContent($pageData['published_content']);
								$pageText->setPublishedTitle($pageData['published_title']);
							}
							
							$pageText->save();
						break;
					
						case 'NEWS':
							foreach ($pageData as $pageNewsData) {
								$pageNews = new MiniSitePageNews();
								$pageNews->setPageId($miniSitePage->getId());
								$pageNews->setTitle($pageNewsData['title']);
								$pageNews->setContent($pageNewsData['content']);
								$pageNews->setStatus($pageNewsData['status']);
								$pageNews->setAuthorId($users[rand(0, count($users) - 1)]->getId());
								
								if ($pageNews->getStatus() == MiniSitePageNewsPeer::STATUS_PUBLISHED) {
									$pageNews->setPublishedAt($pageNewsData['published_at']);
								}
								
								$pageNews->save();
							}
						break;
					
						default:
							throw new \InvalidArgumentException('Unknown page type for : ' . $page['type'] . ' !');
						break;
					}
				}
			}
			
			// Widgets process
			foreach ($miniSiteData['WIDGET_TEMPLATES'] as $type => $template) {
				$widgetTemplate = new MiniSiteWidgetTemplate();
				$widgetTemplate->setType($type);
				$widgetTemplate->save();
				
				foreach ($template['i18n'] as $lang => $columns) {
					$widgetI18n = new MiniSiteWidgetTemplateI18n();
					$widgetI18n->setId($widgetTemplate->getId());
					$widgetI18n->setLang($lang);
					$widgetI18n->setLabel($columns['label']);
					$widgetI18n->setDescription($columns['description']);
					$widgetI18n->save();
					
					$widgetTemplate->addMiniSiteWidgetTemplateI18n($widgetI18n);
				}
			}

			$this->con->commit();
		}
		catch (Exception $e)
		{
			$this->con->rollBack();
            throw $e;
		}
    }
}