<?php

namespace BNS\App\BoardBundle\Form\Type;

use Symfony\Component\DependencyInjection\ContainerInterface;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use BNS\App\BoardBundle\Model\BoardInformationPeer;

class BoardInformationType extends AbstractType
{
    private $isAdmin;
    protected $container;
    protected $isNew;

    /**
     * @param boolean $isAdmin
     */
    public function __construct(ContainerInterface $container, $isNew = true)
    {
        $this->container = $container;
        $this->isAdmin = $this->container->get('bns.right_manager')->hasRight('BOARD_ACCESS_BACK');
        $this->isNew = $isNew;
    }

	/**
	 * @return array<Mixed>
	 */
    public function createDestination()
    {
        $rightManager = $this->container->get('bns.right_manager');
        $choices = array('board' => "Tableau d'information");
		
        if ('SCHOOL' == $rightManager->getCurrentGroupType()) {
            $classType = GroupTypeQuery::create()->filterByType('CLASSROOM')->findOne();
			$subGroups = $this->container->get('bns.api')->send('group_subgroups', array(
				'route' => array(
					'id' => $this->container->get('bns.right_manager')->getCurrentGroupId()
				)
			));
			
            foreach ($subGroups as $group) {
                if ($classType->getId() === $group['group_type_id'] && $this->container->get('bns.right_manager')->hasRight('LIAISONBOOK_ACCESS_BACK', $group['id'])) {
                    $choices['liaisonbook_' . $group['id']] = 'Carnet de liaison (' . $group['label'] . ')';
                }
            }
        }
		else {
            if ($this->container->get('bns.right_manager')->hasRight('LIAISONBOOK_ACCESS_BACK')) {
                $choices['liaisonbook'] = $rightManager->getCurrentGroup()->getLabel();
            }
        }

        return $choices;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('title');
        $builder->add('content', 'textarea');

        if ($this->isAdmin) {
            if ($this->isNew) {
                $builder->add('destination', 'choice', array(
                    'choices' => $this->createDestination(),
                    'expanded' => true,
                    'multiple' => true,
                ));
            }

            $builder->add('status', 'choice', array(
				'choices' => array('PUBLISHED' => 'published', 'PROGRAMMED' => 'programmed'),
				'expanded' => true
			));

            $builder->add('programmation_day', 'date', array(
				'widget' => 'single_text',
				'required' => false,
				'format' => 'dd/MM/yyyy'
			));

            $builder->add('programmation_time', 'time', array(
				'input' => 'string',
				'widget' => 'choice',
				'required' => false
			));

            $builder->add('publication_day', 'date', array(
				'widget' => 'single_text',
				'required' => false,
				'format' => 'dd/MM/yyyy'
			));

            $builder->add('publication_time', 'time', array(
				'input' => 'string',
				'widget' => 'choice',
				'required' => false
			));

            $builder->add('publication_end_day', 'date', array(
				'widget' => 'single_text',
				'required' => false,
				'format' => 'dd/MM/yyyy'
			));

            $builder->add('publication_end_time', 'time', array(
				'input' => 'string',
				'widget' => 'choice',
				'required' => false
			));

            $builder->add('is_alert', 'checkbox', array(
				'required' => false
			));
        }
    }

    /**
     * @param \BNS\App\BoardBundle\Form\Type\OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
			'data_class' => 'BNS\App\BoardBundle\Form\Model\BoardInformationFormModel',
		));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'board_information_form';
    }
}