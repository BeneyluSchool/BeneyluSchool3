<?php

namespace BNS\App\MainBundle\Twig;
use Symfony\Component\Form\FormView;

/**
 * Class MaterialExtension
 *
 * @package BNS\App\MainBundle\Twig
 */
class MaterialExtension extends \Twig_Extension
{

    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('getModelName', array($this, 'getModelName')),
            new \Twig_SimpleFunction('getRootName', array($this, 'getRootName')),
            new \Twig_SimpleFunction('getBindValue', array($this, 'getBindValue')),
        );
    }

    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('array', function ($value) {
                return is_array($value);
            }),
            new \Twig_SimpleTest('string', function ($value) {
                return is_string($value);
            }),
        );
    }

    /**
     * Gets the model name of the given form. The result is a string ready to be interpolated to create form models on
     * the fly.
     *
     * @example
     *  form[my_field] => form['my_field']
     *  form[checkboxes][] => form['checkboxes']
     *
     * @param FormView $form
     * @return mixed
     */
    public function getModelName(FormView $form)
    {
        $name = $form->vars['full_name'];
        $name = preg_replace('#^form#', 'model', $name);
        $name = str_replace('[]', '', $name);                                   // remove checkbox array
        $name = str_replace(array('[', ']'), array('[\'', '\']'), $name);       // wrap subpaths into quotes

        return $name;
    }

    public function getRootName(FormView $form)
    {
        while (isset($form->parent) && $form->parent) {
            $form = $form->parent;
        }

        return $form->vars['name'];
    }

    /**
     * Gets the binding-safe model value
     *
     * @param $value
     * @return mixed
     */
    public function getBindValue($value)
    {
        if (is_numeric($value)) {
            return $value;
        }

        return '\'' . str_replace('\'', '\\\'', $value) . '\'';
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'material_extension';
    }

}
