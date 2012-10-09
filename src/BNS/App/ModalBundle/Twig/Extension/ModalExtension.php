<?php
namespace BNS\App\ModalBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig_Extension;
use Twig_Function_Method;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ModalExtension extends Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
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
            'modal' => new Twig_Function_Method($this, 'showModal', array('is_safe' => array('html')))
        );
    }

    /**
	 * Options :
	 *  - header_template : the header template
	 *  - body_template : the body template
	 *  - header_template : the header template
	 *  - title : the title of the modal (do NOT used when header template is overrided)
	 *  - body : the body of the modal (do NOT used when body template is overrided)
	 *  - animate : true if you want an animation when the modal appears/disappears
	 *  - type[info, success, error] : change the modal class
	 * 
	 * @param string $id
	 * @param array $options
	 * 
	 * @return type
	 */
    public function showModal($id, $options = array())
    {
		// Récupération des templates
		foreach ($options as $name => $option) {
			if (preg_match('#_template#', $name, $matches)) {
				$param = substr($name, 0, -strlen('_template')) . 'Template';
				if (!isset($option['values'])) {
					$option['values'] = array();
				}
				
				// Checking type, view (include) or action (render)
				$option['type'] = preg_match('#html.twig#', $option['template']) ? 'view' : 'action';
				$$param = $option;
			}
		}
		
		return $this->container->get('templating')->render('BNSAppModalBundle:Modal:index.html.twig', array(
			'id'				=> $id,
			'title'				=> isset($options['title']) ? $options['title'] : "I'm the <u>title</u>, edit me with the \"title\" option",
			'body'				=> isset($options['body']) ? $options['body'] : "Hello, I'm a the <b>body</b>, edit me with the \"body\" option",
			'header_template'	=> isset($headerTemplate) ? $headerTemplate : '',
			'body_template'		=> isset($bodyTemplate) ? $bodyTemplate : '',
			'footer_template'	=> isset($footerTemplate) ? $footerTemplate : '',
			'animate'			=> isset($options['animate']) && $options['animate'] === false ? false : true,
			'type'				=> isset($options['type']) ? $options['type'] : 'info'
		));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'modal';
    }
}