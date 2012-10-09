<?php

namespace BNS\App\NotificationBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use RuntimeException;

use BNS\App\CoreBundle\Date\ExtendedDateTime;

/**
 * @author Sylvain Lorinet  <sylvain.lorinet@pixel-cookers.com>
 */
class NotificationClassGenerator extends Generator
{
	private $filesystem;
    private $skeletonDir;

	/**
	 * @param Filesystem $filesystem
	 * @param string $skeletonDir 
	 */
    public function __construct(Filesystem $filesystem, $skeletonDir)
    {
        $this->filesystem = $filesystem;
        $this->skeletonDir = $skeletonDir;
    }
	
	/**
	 * @param string $bundleName Nom du bundle sans le terme "Bundle" à la fin
	 * @param string $namespace Namespace de l'application, exemple "BNS\App"
	 * @param string $className Nom de la classe de la notification
	 * @param array $attributes Liste des attributs de la notification
	 * 
	 * @throws RuntimeException Si le fichier existe déjà : override impossible
	 */
	public function generate($bundleName, $className, array $attributes)
	{
		// Propagation d'une exception si le fichier n'existe pas
		$filePath = __DIR__ . '/../Notification/' . $bundleName . 'Bundle/' . $className . 'Notification.php';
		if (file_exists($filePath)) {
            throw new RuntimeException(sprintf('Unable to generate the class as the target file "%s" is not empty.', $filePath));
		}
		
		$date = new ExtendedDateTime();
		$parameters = array(
			'bundleName'	=> $bundleName,
			'className'		=> $className,
			'type'			=> strtoupper(Container::underscore($className)),
			'attributes'	=> $attributes,
			'date'			=> $date
		);
		
		// Templating des attributs en PHP : $attributA, $attributB, $attributC, [...]
		$parameters['phpAttributes'] = '';
		foreach ($attributes as $attribute) {
			$parameters['phpAttributes'] .= '$' . $attribute . ', ';
		}
		
		// Creation du fichier
		$this->renderFile($this->skeletonDir, 'NotificationType.php', $filePath, $parameters);
	}
}