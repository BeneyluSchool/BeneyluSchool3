<?php

namespace BNS\App\MiniSiteBundle\Form\Type;

use \Symfony\Component\Form\AbstractType;
use \Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MiniSiteCityStatusType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $statuses = [
            'DRAFT',
            'PUBLISHED_FUTURE',
            'PUBLISHED',
            'PUBLISHED_PAST',
        ];
        $builder->add('status', 'choice', array(
            'choices' => array_combine($statuses, $statuses),
            'expanded' => true,
            'label' => ' ',
            'multiple' => true,
            'attr' => [
                'bns-status' => 'status',
            ],
            'choice_attr' => function ($value) {
                switch ($value) {
                    case 'PUBLISHED_FUTURE':
                        $status = 'FINISHED';
                        break;
                    case 'PUBLISHED_PAST':
                        $status = 'SCHEDULED';
                        break;
                    default:
                        $status = $value;
                }

                return ['status' => $status];
            },
        ));
    }

    /**
     * @inheritDoc
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'translation_domain' => 'MINISITE',
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'mini_site_city_status_form';
    }

}
