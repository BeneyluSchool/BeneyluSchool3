<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use BNS\App\UserDirectoryBundle\Model\DistributionListPeer;
use BNS\App\UserDirectoryBundle\Model\DistributionListQuery;
use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MiniSitePageCityNewsType extends AbstractType
{

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title', 'text');
        $builder->add('content', 'textarea');

        $statuses = ['DRAFT', 'PUBLISHED'];
        $builder->add('status', 'choice', [
            'choices' => array_combine($statuses, $statuses),
            'expanded' => true,
            'label' => 'CITY_NEWS_STATUS',
            'proxy' => true,
            'attr' => [
                'bns-status' => 'status',
                'bns-choice-watch' => '',
            ],
        ]);

        $builder->add('published_at', 'date', [
            'widget' => 'single_text',
            'error_bubbling' => true,
        ]);
        $builder->add('published_end_at', 'date', [
            'widget' => 'single_text',
            'error_bubbling' => true,
        ]);

        $builder->add('is_all_schools');

        if ($options['group']) {
            $query = DistributionListQuery::create()
                ->filterByType(DistributionListPeer::TYPE_STRUCT)
                ->filterByGroup($options['group'])
            ;
            $builder->add('distributionLists', 'model', [
                'class' => 'BNS\\App\\UserDirectoryBundle\\Model\\DistributionList',
                'multiple' => true,
                'expanded' => true,
                'error_bubbling' => true,
                'query' => $query,
                'choice_label' => 'name',
                'index_property' => 'id',
                'ng_order' => 'label'
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\MiniSiteBundle\Model\MiniSitePageCityNews',
            'translation_domain' => 'MINISITE',
            'group' => null,
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'mini_site_page_city_news_form';
    }

}
