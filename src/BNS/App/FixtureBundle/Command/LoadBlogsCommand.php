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

use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Propel;

use BNS\App\CoreBundle\Model\BlogCategory;
use BNS\App\CoreBundle\Model\BlogArticlePeer;
use BNS\App\CoreBundle\Model\BlogArticle;
use BNS\App\CoreBundle\Model\BlogArticleCategory;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\BlogQuery;
use BNS\App\CoreBundle\Model\BlogCategoryQuery;

/**
 * Create and load fixtures for BNS
 *
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class LoadBlogsCommand extends ContainerAwareCommand
{
	/**
	 * @var PropelPDO MySQL connexion
	 */
	protected $con;
	
	protected function configure()
    {
        $this
            ->setName('bns:load-blogs')
            ->setDescription('Load BNS blog fixtures')
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
		
		$blogsData = Yaml::parse(__DIR__ . '/../Resources/data/Blog/blog.yml');
		
		try
		{
			$this->con->beginTransaction();
			
			// Récupération des blogs
			$blogs = BlogQuery::create()->find();
			
			// Récupération des utilisateurs
			$users = UserQuery::create()->find();
			
			// Inserts
			foreach ($blogs as $blog) {
				// Création des BlogCategory
				$rootCategory = BlogCategoryQuery::create()->findRoot($blog->getId());
				
				$categories = array();
				foreach ($blogsData['category'] as $category => $subCategories) {
					$blogCategory = new BlogCategory();
					$blogCategory->setBlogId($blog->getId());
					$blogCategory->setTitle($category);
					$blogCategory->insertAsLastChildOf($rootCategory);
					$blogCategory->save();
					$categories[] = $blogCategory;

					// La catégorie possède une ou plusieurs catégories filles
					if (is_array($subCategories)) {
						foreach ($subCategories as $subCategory) {
							$blogSubCategory = new BlogCategory();
							$blogSubCategory->setBlogId($blog->getId());
							$blogSubCategory->setTitle($subCategory);
							$blogSubCategory->insertAsLastChildOf($blogCategory);
							$blogSubCategory->save();
							$categories[] = $blogSubCategory;
							$blogSubCategory = null;
						}
					}

					$blogCategory = null;
				}

				// Création des blog_article
				$countArticle = rand(0, 10);
				$statusValues = BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS);

				for ($i=0; $i<$countArticle; $i++) {
					$article = new BlogArticle();
					$article->setCreatedAt(time() - rand(0, 86400));
					$article->setBlogId($blog->getId());
					$article->setAuthorId($users[rand(0, count($users) - 1)]->getId());
					$article->setTitle($blogsData['article'][rand(0, count($blogsData['article']) - 1)]);
					$article->setContent('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc viverra nulla nec ante fermentum id tincidunt dui rutrum. In hac habitasse platea dictumst. Sed luctus eleifend nibh in pulvinar. Proin feugiat semper sapien, consectetur tincidunt mi ullamcorper id. Curabitur eros lorem, auctor vel bibendum eu, pretium sit amet leo. Cras pretium, magna eu vulputate fermentum, ligula quam auctor quam, non sollicitudin turpis mi vitae nibh. Donec at augue orci. Aliquam facilisis blandit tortor, sed aliquam nisl vulputate non. Proin eu felis quis nunc hendrerit pretium ut et nunc. Integer iaculis tellus id sem placerat scelerisque.');
					$article->setStatus($statusValues[rand(0, count($statusValues) - 1)]);
					$article->setIsStar(rand(0, 1));
					$article->setIsCommentAllowed(rand(0, 1));

					switch ($article->getStatus())
					{
						case BlogArticlePeer::STATUS_DRAFT:
							$article->setUpdatedAt($article->getCreatedAt()->getTimestamp() + rand(0, 43200));
						break;

						case BlogArticlePeer::STATUS_PUBLISHED:
							if (rand(0, 1) == 1) {
								$article->setPublishedAt($article->getCreatedAt()->getTimestamp() + rand(0, 43200));
							}
							else {
								// Publication programmée
								$article->setPublishedAt(time() + rand(0, 86400));
							}

							$article->setUpdatedAt($article->getPublishedAt()->getTimestamp() + rand(0, 43200));
						break;

						case BlogArticlePeer::STATUS_WAITING_FOR_CORRECTION:
						case BlogArticlePeer::STATUS_FINISHED:
							$article->setUpdatedAt($article->getCreatedAt()->getTimestamp() + rand(0, 43200));
						break;

						default:
							throw new RuntimeException('The option number ' . $article->getStatus() . ' must be implemented when creating blog article !');
					}

					$article->save();

					// Création de la blog_article_category
					$usedCategories = array();
					$countCategory = rand(0, count($categories) - 1);
					for ($i=0; $i<$countCategory; $i++) {
						$articleCategory = new BlogArticleCategory();
						$articleCategory->setArticleId($article->getId());

						$category = null;
						while (null == $category || isset($usedCategories[$category->getId()])) {
							$category = $categories[rand(0, count($categories) - 1)];
						}
						$usedCategories[$category->getId()] = true;

						$articleCategory->setCategoryId($category->getId());
						$articleCategory->save();
					}

					$article = null;
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