<?php

namespace BNS\App\WorkshopBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class WorkshopDocumentType extends AbstractType
{
	/**
	 * @var int
	 */
	public static $i = 0;
	
	/**
	 * @var WorkshopDocumentTemplate 
	 */
	private $template;
	
	/**
	 * @var string
	 */
	private $formName;
	
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
		self::$i = 0;
		
		$builder->add('id', 'hidden');
		$builder->add('template_unique_name', 'hidden');
		$builder->add('workshop_document_inputs', 'collection', array(
            'type'          => new \BNS\App\WorkshopBundle\Form\Type\WorkshopDocumentInputType($this->template),
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false
        ));
    }

	/**
	 * @param \BNS\App\BlogBundle\Form\Type\OptionsResolverInterface $resolver
	 */
	public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'BNS\App\WorkshopBundle\Model\WorkshopDocument',
        ));
    }

	/**
	 * @return string 
	 */
    public function getName()
    {
        return !isset($this->formName) ? 'workshop_document_form' : $this->formName;
    }
	
	/**
	 * @param string $formName
	 */
	public function setName($formName)
	{
		$this->formName = $formName;
	}
}