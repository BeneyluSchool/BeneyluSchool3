<?php

namespace BNS\App\ClassroomBundle\DataReset;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
interface DataResetInterface
{
    /**
     * @return Le nom du data reset
     */
    public function getName();

    /**
     * @param \BNS\App\CoreBundle\Model\GroupData $group Le group à reset
     */
    public function reset($group);

    /**
     * @return Le formulaire du data reset
     */
    public function getForm();

    /**
     * @return Le FormType du data reset
     */
    public function getFormType();

    /**
     * @return Le nom de la vue du data reset
     */
    public function getRender();

    /**
     * @param boolean $bool True si le data reset a des options et doit être affiché dans la vue principale, false sinon
     */
    public function setHasOptions($bool);

    /**
     * @return True si le data reset a des options et doit être affiché dans la vue principale, false sinon
     */
    public function hasOptions();

    /**
     * @param \Symfony\Component\Templating\EngineInterface $formFactory Le service de form factory
     */
    public function setFormFactory($formFactory);
}