<?php
/**
 * Created by PhpStorm.
 * User: Slaiem
 * Date: 16/03/2018
 * Time: 15:37
 */

namespace BNS\App\MessagingBundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class PreferencesType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {


        $builder->add('alias', 'text', array(
            'required' => false,
        ));

        $builder->add('mailTo', 'email', array(
            'required' => false,
        ));

        $builder->add('isAbsent', 'checkbox', array(
            'required' => false
        ));
        $builder->add('absentFrom', 'text', array(
            'required' => false
        ));
        $builder->add('absentTo', 'text', array(
            'required' => false
        ));

        $builder->add('absenceSubject', 'text', array(
            'required' => false,
        ));
        $builder->add('absenceContent', 'textarea', array(
            'required' => false,
        ));
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'messaging_preferences';
    }
}
