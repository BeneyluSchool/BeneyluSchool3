<?php

namespace BNS\App\HelloWorldBundle\Controller;

use BNS\App\CoreBundle\Model\BlogArticleQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Yaml\Parser;

/**
 * Class BackBlogController
 *
 * @package BNS\App\HelloWorldBundle\Controller
 *
 * @Route("/gestion/blog")
 */
class BackBlogController extends Controller
{

    private $statuses = array(
        'pub' => 'Articles publiés',
        'sched' => 'Articles programmés',
        'corr' => 'Articles à corriger',
        'fin' => 'Articles terminés',
        'draft' => 'Brouillons',
    );

    private $categories = array(
        1 => 'Catégorie A',
        2 => 'Catégorie B',
        3 => 'Catégorie C',
        4 => 'Catégorie D',
        5 => 'Catégorie E',
    );

    /**
     * @Route("", name="hello_world_manager_blog")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function indexAction(Request $request)
    {
        $articles = $this->getArticles();
        $form = $this->getSelectForm($articles);
        $filterForm = $this->getFilterForm();

        $form->handleRequest($request);
        $filterForm->handleRequest($request);

        return array(
            'articles' => $articles,
            'form' => $form->createView(),
            'filter_form' => $filterForm->createView(),
        );
    }

    /**
     * @Route("/create", name="hello_world_manager_blog_create")
     * @Template()
     *
     * @param Request $request
     * @return array
     */
    public function createAction(Request $request)
    {
        $form = $this->getForm();
        $form->handleRequest($request);

        return array(
            'form' => $form->createView(),
        );
    }

    /**
     * @Route("/edit/{id}", name="hello_world_manager_blog_edit")
     * @Template()
     *
     * @param $id
     * @param Request $request
     * @return array
     */
    public function editAction($id, Request $request)
    {
        $article = $this->getArticle($id);
        $form = $this->getForm($article);
        $form->handleRequest($request);
        $actualArticle = BlogArticleQuery::create()->findPk(5);

        return array(
            'article' => $article,
            'form' => $form->createView(),
            'actual_article' => $actualArticle,
        );
    }

    /**
     * @Route("/components", name="hello_world_manager_blog_components")
     * @Template()
     *
     * @return array
     */
    public function componentsAction()
    {
        $this->get('session')->getFlashBag()->add('success', 'Flash message on page load');

        return array();
    }

    private function getArticles()
    {
        $yamlParser = new Parser();
        $articles = $yamlParser->parse(file_get_contents(__DIR__ . '/../Fixtures/articles.yml'));

        foreach ($articles as &$article) {
            $article['date'] = new \DateTime($article['date']);
        }

        return $articles;
    }

    private function getArticle($id)
    {
        foreach ($this->getArticles() as $article) {
            if ($article['id'] == $id) {
                return $article;
            }
        }

        return null;
    }

    private function getSelectForm($articles)
    {
        $choices = array();
        foreach ($articles as $article) {
            $choices[$article['id']] = $article['title'];
        }

        return $this->createFormBuilder()
            ->add('selection', 'choice', array(
                'choices' => $choices,
                'expanded' => true,
                'multiple' => true,
            ))
            ->getForm()
        ;
    }

    private function getFilterForm()
    {
        return $this->get('form.factory')->createNamedBuilder('form_filter', 'form')
            ->add('search', 'search', array(
                'required' => false,
                'attr' => array(
                    'placeholder' => 'Search articles',
                ),
                'label' => false,
            ))
            ->add('status', 'choice', array(
                'choices' => $this->statuses,
                'expanded' => true,
                'multiple' => true,
                'proxy' => true,
            ))
            ->add('categories', 'choice', array(
                'choices' => $this->categories,
                'expanded' => true,
                'multiple' => true,
                'proxy' => true,
            ))
            ->getForm()
        ;
    }

    private function getForm($data = null)
    {
        // Create the form type directly here. Don't do this at home...
        return $this->createFormBuilder($data)
            ->setErrorBubbling(true)
            ->add('title', 'text', array(

                'constraints' => array(
                    new Length(array(
                        'min' => 10,
                        'minMessage' => 'Le titre est trop petit. 10 caractères minimum.',
                        'max' => 100,
                        'maxMessage' => 'Le titre est trop grand. 100 caractères maximum',
                    )),
                    new NotBlank(),
                ),
                'attr' => array(
                    'minlength' => 10,
                    'maxlength' => 100,
                ),
                'fullwidth' => true,
                'error_bubbling' => true,
            ))
            ->add('description', 'textarea', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'fullwidth' => true,
                'error_bubbling' => true,
            ))
            ->add('status', 'choice', array(
                'choices' => $this->statuses,
                'empty_data' => 'draft',
                'expanded' => true,
                'constraints' => array(
                    new NotBlank(),
                ),
                'proxy' => true,
                'error_bubbling' => true,
            ))
            ->add('categories', 'choice', array(
                'choices' => $this->categories,
                'expanded' => true,
                'multiple' => true,
                'error_bubbling' => true,
                'proxy' => true,
            ))
            -> add('date', 'date', array(
                'widget' => 'single_text',
                'error_bubbling' => true,
                'proxy' => true,
            ))
            ->add('submit', 'submit')
            ->getForm()
        ;

        // TODO: switch for comments
        // TODO: inline category creation
    }

}
