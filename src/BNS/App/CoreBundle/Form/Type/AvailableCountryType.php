<?php

namespace BNS\App\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class AvailableCountryType
 *
 * @package BNS\App\CoreBundle\Form\Type
 */
class AvailableCountryType extends AbstractType
{

    protected $preferredCountries = [];

    public function __construct($preferredCountries = [])
    {
        $this->preferredCountries = $preferredCountries;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'preferred_choices' => $this->preferredCountries,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'country';
    }

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'available_country';
    }

}
