<?php

namespace BNS\App\WorkshopBundle\Form\Type;

use BNS\App\WorkshopBundle\Model\WorkshopDocumentTemplate;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class WorkshopDocumentInputType extends AbstractType
{
	/**
	 * @var WorkshopDocumentTemplate 
	 */
	private $template;
	
	/**
	 * @param WorkshopDocumentTemplate $template
	 */
	public function __construct($template)
	{
		$this->template = $template;
	}
	
	/**
	 * @param FormBuilderInterface $builder
	 * @param array $options 
	 */
	public function buildForm(FormBuilderInterface $builder, array $options)
    {
		$inputs = $this->template->getWorkshopDocumentTemplateInputs();
		if (!isset($inputs[WorkshopDocumentType::$i])) {
			WorkshopDocumentType::$i = 0;
		}
		
		$builder->add('input_unique_name', 'hidden', array(
			'data' => $inputs[WorkshopDocumentType::$i]->getUniqueName()
		));
		$builder->add('value', $inputs[WorkshopDocumentType::$i]->getTypeForm());
		
		WorkshopDocumentType::$i++;
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopDocumentInput',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return 'workshop_document_input_form';
    }
}