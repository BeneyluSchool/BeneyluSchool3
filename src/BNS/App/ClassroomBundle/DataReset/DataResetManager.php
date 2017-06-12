<?php

namespace BNS\App\ClassroomBundle\DataReset;

use BNS\App\ClassroomBundle\DataReset\User\DataResetUserInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class DataResetManager
{
    /**
     * @var array<DataResetInterface>
     */
    private $dataResets;

    /**
     * @var array<DataResetUserInterface>
     */
    private $dataResetUsers;

    /**
     * @var \Symfony\Component\Form\FormFactory
     */
    private $formFactory;

    /**
     * @var Container
     */
    private $container;


    /**
     * @param \Symfony\Component\Form\FormFactory $formFactory
     */
    public function __construct($formFactory, $container)
    {
        $this->formFactory = $formFactory;
        $this->container = $container;
    }
    
    /**
     * Used by DataResetCompilerPass
     * 
     * @param \BNS\App\ClassroomBundle\DataReset\DataResetInterface $dataReset
     * @param string  $type
     * @param boolean $hasOptions
     */
    public function addDataReset(DataResetInterface $dataReset, $type, $hasOptions)
    {
        $dataReset->setHasOptions($hasOptions);
        $dataReset->setFormFactory($this->formFactory);

        if (isset($this->dataResets[$type]) && isset($this->dataResets[$type][$dataReset->getName()])) {
            throw new \RuntimeException('A "' . $type . '" data reset with the same name (' . $dataReset->getName() . ' already exists ! Please rename one of them');
        }
        
        $this->dataResets[$type][$dataReset->getName()] = $dataReset;
    }

    /**
     * Used by DataResetCompilerPass
     *
     * @param DataResetUserInterface $dataResetUser
     * @param string $type
     */
    public function addDataResetUser(DataResetUserInterface $dataResetUser, $type)
    {
        if (isset($this->dataResets[$type]) && isset($this->dataResets[$type][$dataResetUser->getName()])) {
            throw new \RuntimeException('A "' . $type . '" data reset user with the same name (' . $dataResetUser->getName() . ' already exists ! Please rename one of them');
        }

        $this->dataResetUsers[$type][$dataResetUser->getName()] = $dataResetUser;
    }

    /**
     * Retourne tous les data reset pour un type donné
     *
     * @param string  $type
     * @param boolean $withOptions
     */
    public function getDataResets($type, $withOptions = null)
    {
        if (!isset($this->dataResets[$type])) {
            throw new \InvalidArgumentException('The data reset type "' . $type . '" is NOT found !');
        }

        if (null === $withOptions) {
            return $this->dataResets[$type];
        }

        $dataResets = array();
        foreach ($this->dataResets[$type] as $dataReset) {
            if ($withOptions === $dataReset->hasOptions()) {
                if($dataReset->getName() == "change_year_classroom_pupil")
                {
                    if(!$this->container->hasParameter('new_year_disable_users'))
                    {
                        $dataResets[$dataReset->getName()] = $dataReset;
                    }
                }else{
                    $dataResets[$dataReset->getName()] = $dataReset;
                }
            }
        }

        return $dataResets;
    }

    /**
     * Retourne tous les data reset user pour un type donné
     *
     * @param string $type
     */
    public function getDataResetUsers($type)
    {
        if (!isset($this->dataResetUsers[$type])) {
            throw new \InvalidArgumentException('The data reset type "' . $type . '" is NOT found !');
        }

        return $this->dataResetUsers[$type];
    }

    /**
     * Retourne un unique data reset
     *
     * @param string $type
     * @param string $name
     *
     * @return DataResetInterface
     *
     * @throws \InvalidArgumentException
     */
    public function getDataReset($type, $name)
    {
        if (!isset($this->dataResets[$type][$name])) {
            throw new \InvalidArgumentException('The "' . $type . '" data reset with name "' . $name . '" is NOT found !');
        }

        return $this->dataResets[$type][$name];
    }
}