<?php

namespace BNS\App\ClassroomBundle\DataReset;

use Symfony\Component\Form\Form;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class AbstractDataReset implements DataResetInterface
{
    /**
     * @var boolean
     */
    protected $hasOptions;

    /**
     * @var \Symfony\Component\Form\FormFactory
     */
    protected $formFactory;
    

    /**
     * @param boolean $bool
     */
    public function setHasOptions($bool)
    {
        $this->hasOptions = $bool;
    }

    /**
     * @return boolean
     */
    public function hasOptions()
    {
        return $this->hasOptions;
    }

    /**
     * @param \Symfony\Component\Form\FormFactory $formFactory
     */
    public function setFormFactory($formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->formFactory->create($this->getFormType(), $this);
    }

    public function getRender() {}
    public function getFormType() {}
}