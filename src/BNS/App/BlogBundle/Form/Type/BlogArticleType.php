<?php

namespace BNS\App\BlogBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use BNS\App\CoreBundle\Translation\TranslatorTrait;


use BNS\App\CoreBundle\Model\BlogArticlePeer;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class BlogArticleType extends AbstractType
{
	use TranslatorTrait;

	private $isAdmin;
	private $canPublish;
    private $isEdit;
    private $groupsWhereAdminPermission;
	private $categories;
	private $categoriesRoute;
	private $currentBlogId;
	private $canSchedule;

	/**
	 * @param boolean $isAdmin
	 */
	public function __construct($isAdmin, $canPublish = false, $isEdit = false, $groupsWhereAdminPermission = null, $categories = [], $categoriesRoute = '', $currentBlogId, $canSchedule = false)
	{
		$this->isAdmin = $isAdmin;
		$this->canPublish = $canPublish;
        $this->isEdit = $isEdit;
        $this->groupsWhereAdminPermission = $groupsWhereAdminPermission;
		$this->categories = $categories;
		$this->categoriesRoute = $categoriesRoute;
        $this->currentBlogId = $currentBlogId;
        $this->canSchedule = $canSchedule;
	}

	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('draftTitle', 'text', array('label'=>' '));
        $builder->add('draftContent', 'textarea', [
            'label' => false,
            'correction_edit' => true, // add correction Automatically
            'correction_object_class' => 'BNS\\App\\CoreBundle\\Model\\BlogArticle',
            'correction_group_id' => $options['correction_group_id'],
        ]);
		$translator = $this->getTranslator();


			$builder->add('categories', 'model', array(
				'class' => 'BNS\\App\\CoreBundle\\Model\\BlogCategory',
				'choices' => $this->categories,
				'choice_label' => 'title',
				'by_reference' => false,
				'choices_as_values' => true,
				'expanded' => true,
				'multiple' => true,
				'create' => $this->isAdmin ? $this->categoriesRoute : false,
				'label' => $translator->trans('CATEGORY', [], 'BLOG'),
			));
        if ($this->isAdmin || $this->canPublish) {
			$statuses = array_flip(BlogArticlePeer::getValueSet(BlogArticlePeer::STATUS));
			$statuses['PROGRAMMED'] = 'programmed';

			if ($this->canPublish && !$this->isAdmin) {
				$statuses = array_filter($statuses, function ($key) {
					return in_array($key, ['DRAFT', 'PUBLISHED', 'PROGRAMMED']);
				}, ARRAY_FILTER_USE_KEY);
			}

			$builder->add('status', 'choice', array(
				'choices'	=> $statuses,
				'expanded'	=> true,
//				'choices_as_values' => true,
				'attr' => [ 'bns-status' => '' ],
				'choice_attr' => function ($value) {
					if ($value === 'PROGRAMMED') {
						return ['bns-feature-flag' => 'blog_schedule'];
					}

					return [];
				},
				'choice_label' => function ($value) {
					$translator = $this->getTranslator();
					$choicesStatuses = array(
							'DRAFT' => 'DRAFT',
							'FINISHED' => 'ARTICLE_FINISHED',
							'WAITING_FOR_CORRECTION' => 'ARTICLE_TO_CORRECT',
							'PUBLISHED' => 'ARTICLE_PUBLISH',
							'PROGRAMMED' => 'ARTICLE_PROGRAM'
					);

                    /** @Ignore */
                    return $translator->trans($choicesStatuses[$value], array(), 'BLOG');
				},
				'label' => $translator->trans('ARTICLE_STATUS', array(), 'BLOG'),
				'constraints' => [
					new Callback([$this, 'validateStatus'])
				]
			));
			$builder->add('programmation_day', 'date', array(
				'input' => 'datetime',
				'widget'	=> 'single_text',
				'required'	=> false,
				'label' => $translator->trans('DATE_OF_PUBLISH_PROGRAM', array(), 'BLOG')
			));
			$builder->add('programmation_time', 'time', array(
				'input'		=> 'string',
				'widget'	=> 'single_text',
                'with_seconds' => false,
				'required'	=> false,
				'label' => $translator->trans('TIME_OF_PUBLISH_PROGRAM', array(), 'BLOG')
			));
			$publicationConstraints = [];
			if (!$this->canSchedule) {
				$publicationConstraints[] = new Range([
					'max' => new \DateTime(),
				]);
			}
			$builder->add('publication_day', 'date', array(
				'input' => 'datetime',
				'widget'	=> 'single_text',
				'required'	=> false,
				'constraints' => $publicationConstraints,
				'label' => $translator->trans('DATE_OF_PUBLISH', array(), 'BLOG')
			));
			$builder->add('publication_time', 'time', array(
				'input'		=> 'string',
				'widget'	=> 'single_text',
				'required'	=> false,
                'with_seconds' => false,
				'label' => $translator->trans('TIME_OF_PUBLISH', array(), 'BLOG')
			));

			$builder->add('is_comment_allowed', 'checkbox', array(
				'required'	=> false,
				'proxy' => true,
				'row_attr' => [
					'data-proxy-label' => $translator->trans('COMMENTS', [], 'BLOG'),
				],
                'label' => 'LABEL_ALLOW_COMMENT'
			));

		}

        if ($this->groupsWhereAdminPermission != null) {
            $choices = array();
            foreach ($this->groupsWhereAdminPermission as $group) {
                $choices[$group->getBlog()->getId()] = $group->getLabel();
            }
            if (count($choices) > 1) {
                $blogIdsOptions = [
                    'choices' => $choices,
                    'expanded' => true,
                    'required' => true,
                    'multiple' => true,
                    'proxy' => true,
                    'label' => 'BLOG_PUBLICATION',
                    'choice_attr' => function ($value) {
                            if ($value !== $this->currentBlogId){
                                return ['bns-feature-flag' => 'blog_publipost'];
                            }
                            return [];
                    }
                ];
                if (!$this->isEdit) {
                    $blogIdsOptions['data'] = [$this->currentBlogId];
                }

                $builder->add('blog_ids', 'choice', $blogIdsOptions);
            }
        }
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'correction_group_id' => null,
            'data_class' => 'BNS\App\BlogBundle\Form\Model\BlogArticleFormModel',
            'translation_domain' => 'BLOG'
        ));
    }

	/**
	 * @return string
	 */
    public function getName()
    {
        return 'blog_article_form';
    }

    public function validateStatus($value, ExecutionContextInterface $context)
    {
        if ('PROGRAMMED' === $value && !$this->canSchedule) {
            $context->addViolation($this->trans('DESCRIPTION_PUSH_PRO', [], 'JS_MAIN'));
        }
    }
}
