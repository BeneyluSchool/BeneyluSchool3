<?php

namespace BNS\App\AutosaveBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use BNS\App\AutosaveBundle\Autosave\AutosaveInterface;
use BNS\App\CoreBundle\Utils\Crypt;
use Twig_Extension;
use Twig_Function_Method;
use InvalidArgumentException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 * 
 *  Date : 1 juin 2012
 */
class AutosaveExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Initialize autosave helper
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return array(
            'autosave_init' => new Twig_Function_Method($this, 'autosaveInit', array('is_safe' => array('html')))
        );
    }

    /**
	 * Autosave initializations
	 * 
	 * @param string $id Le nom de l'instance de la sauvegarde (servira pour la session)
	 * @param AutosaveInterface $object 
	 * @param string $objectAttributeName 
	 * @param string $objectFormId 
	 * @param array $options 
	 *  - callback_dom_id
	 * 
	 * @return string 
	 */
    public function autosaveInit(AutosaveInterface $object, array $attributesToSave, array $options = array())
    {
		if (isset($attributesToSave['autosaveId'])) {
			throw new InvalidArgumentException('Cannot use "autosaveId" key for the autosave !');
		}
		
		$autosaveQuery = array(
			'object_class'				=> Crypt::encrypt(get_class($object)),
			'attributes_to_save'		=> $attributesToSave,
			'additionnal_attributes'	=> isset($options['data']) ? $options['data'] : array()
		);
		
		// On créer le JSon pour le contrôleur
		// json_encode() n'est pas utilisé car on a besoin d'appeller des fonctions en guise de valeur
		// la fonction aurait transformé cela en string.
		$params = '{';
		$condition = '';
		foreach ($attributesToSave as $attributeName => $inputName) {
			$params .= '"' . $attributeName . '": getContentFor("' . $inputName . '"), ';
			$condition .= "getContentFor('" . $inputName . "').length == 0 || ";
		}
		$params .= '"object_primary_key": primaryKey, "autosave_query": ' . json_encode($autosaveQuery) . '}';
		$condition = substr($condition, 0, -4);
		
		// Récupération des configs de base
		// Surcharge si l'utilisateur l'a renseigné pour l'instance de l'autosave
		$configs = array_merge($this->container->getParameter('bns_autosave'), isset($options['configs']) ? $options['configs'] : array());
		
		$tinyMCEConfigs = $this->container->getParameter('stfalcon_tinymce.config');
        return $this->container->get('templating')->render('BNSAppAutosaveBundle:Autosave:init.html.twig', array(
			'params'			=> $params,
			'condition'			=> $condition,
			'attributesToSave'	=> $attributesToSave,
			'isNew'				=> null == $object->getPrimaryKey(),
			'primary_key'		=> Crypt::encrypt($object->getPrimaryKey()),
			'textarea_class'	=> $tinyMCEConfigs['textarea_class'],
			'configs'			=> $configs,
			'onSuccess'			=> isset($options['onSuccess']) ? $options['onSuccess'] : '',
			'onStart'			=> isset($options['onStart']) ? $options['onStart'] : '',
			'debug'				=> isset($options['debug']) && $options['debug'] === true
        ));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'autosave';
    }
}
