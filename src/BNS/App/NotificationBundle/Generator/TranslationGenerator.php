<?php

namespace BNS\App\NotificationBundle\Generator;

use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use RuntimeException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class TranslationGenerator extends Generator
{
    private $filesystem;

    /**
     * @param Filesystem $filesystem
     * @param string $skeletonDirs
     */
    public function __construct(Filesystem $filesystem, $skeletonDirs)
    {
        $this->filesystem = $filesystem;
        $this->setSkeletonDirs($skeletonDirs);
    }

    /**
     * @param string $bundleName Nom du bundle sans le terme "Bundle" à la fin
     * @param string $className Nom de la classe de la notification
     * @param array <string> $languages Liste des langages possibles
     *
     * @throws RuntimeException Si un des fichiers existe déjà : override impossible
     */
    public function generate($bundleName, $className, $languages, $attributes)
    {
        $filePathes = array();
        foreach ($languages as $language) {
            $filePath = __DIR__ . '/../Resources/translations/' . $bundleName . 'Bundle/' . strtoupper(
                    Container::underscore($className)
                ) . '.' . $language . '.xliff';
            if (file_exists($filePath)) {
                throw new RuntimeException(
                    sprintf('Unable to generate the translation as the target file "%s" is not empty.', $filePath)
                );
            }

            $filePathes[] = $filePath;
        }

        $parameters = array(
            'type' => strtoupper(Container::underscore($className)),
            'attributes' => $attributes
        );

        // Création des fichiers
        foreach ($filePathes as $filePath) {
            $this->renderFile('messages.xliff', $filePath, $parameters);
        }
    }
}
