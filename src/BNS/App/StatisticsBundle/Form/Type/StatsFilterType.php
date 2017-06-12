<?php

namespace BNS\App\StatisticsBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use \BNS\App\StatisticsBundle\Model\MarkerQuery;
use \BNS\App\CoreBundle\Model\ModuleQuery;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * @author Florian Rotagnon <florian.rotagnon@gmail.com>
 */
class StatsFilterType extends AbstractType
{
    private $groupTypesAllowed;
    private $currentGroupName;
    private $rightManager;
    /* Distinction current | global */
    private $type;

    public function __construct(array $groupTypesAllowed, $currentGroupName, $rightManager, $type = "current") {
        $this->groupTypesAllowed = $groupTypesAllowed;
        $this->currentGroupName  = $currentGroupName;
        $this->rightManager = $rightManager;
        $this->type = $type;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $option)
    {
        //récupération de tous les marqueurs
        $markers = MarkerQuery::create()->find();
        $tabMarkers = array();
        //pour V2
        //visible seulement par admins
        //pour distinguer rajouter un if sur les droits d'admin
        $excludeMarkers = array("MAIN_REGISTER_CLASS", "CLASSROOM_CHANGE_GRADE");

        $pattern = "_ACCESS";

        if($this->type == "current")
        {
            $activatedModules = $this->rightManager->getModulesByPermissionPattern($pattern);
        }elseif($this->type == 'global'){
            $activatedModules = ModuleQuery::create()->filterByIsEnabled(true)->find();
        }

        foreach ($activatedModules as $module) {
            //Récupérations des markers qui correspondent au module
            $markers = MarkerQuery::create()
                ->findByModuleUniqueName($module->getUniqueName());

            if(null != $markers) {
                $moduleLabel = $module->getLabel();

                foreach ($markers as $marker) {
                    if(!in_array($marker->getUniqueName(), $excludeMarkers)) {
                        if(isset($tabMarkers[$moduleLabel])) {
                            //on ajoute le marqueur a la liste des marqueurs du module
                            $tabMarkers[$moduleLabel] = array_merge($tabMarkers[$moduleLabel], array($marker->getUniqueName() => $marker->getDescription()));
                        } else {
                            //on cree un tableau de marqueur pour ce module
                            $tabMarkers[$moduleLabel] = array($marker->getUniqueName() => $marker->getDescription());
                        }
                    }
                }
            }
        }




        $builder->add('aggregation', 'checkbox', array(
            'required' => false)
        );

        $builder->add('marker', 'choice', array(
            'required' => true,
            'choices'  => $tabMarkers
        ));

        $builder->add('period', 'choice', array(
            'required' => true,
            'choices'	=> array(
                'DAY' => 'CHOICE_DAY',
                'MONTH'	  => 'CHOICE_MONTH',
                'HOURS'  => 'CHOICE_HOURS'
            )
        ));

        $builder->add('title', 'text', array(
            'required' => false,
            'max_length' => 50
        ));

        // Nom
        $builder->add('group_type', 'choice', array(
            'required'	=> false,
            'choices'	=> $this->groupTypesAllowed,
            'empty_value' => 'CHOICE_ALL_ROLE'
        ));

        $builder->add('date_start', 'datetime', array(
            'widget' => 'single_text',
            'required'    => true,
            'date_format' => 'dd-MMMM-yyyy',
            'attr' => array('class' => 'date')
        ));

        $builder->add('date_end', 'datetime', array(
            'widget' => 'single_text',
            'required'    => true,
            'date_format' => 'dd-MMMM-yyyy',
            'attr' => array('class' => 'date')
        ));

        $builder->add('graph_pro', 'checkbox', array(
            'required' => false
        ));
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => '\BNS\App\StatisticsBundle\Form\Model\StatsFilterFormModel',
            'translation_domain' => 'STATISTICS'
        ));
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'stats_filter';
    }
}
