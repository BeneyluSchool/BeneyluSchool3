<?php
namespace BNS\App\MediaLibraryBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class ThumbCreatorCompilerPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('bns_app_media_library.thumb.thumb_creator_manager')) {
            return;
        }

        $definition = $container->getDefinition('bns_app_media_library.thumb.thumb_creator_manager');
        $prioritizedCreators = [];
        foreach ($container->findTaggedServiceIds('bns_thumb_creator') as $id => $tag) {
            $priority = isset($tag['priority']) ? $tag['priority'] : 0;
            $prioritizedCreators[$priority][] = $id;
        }

        krsort($prioritizedCreators);

        $thumbCreators = [];
        foreach ($prioritizedCreators as $creators) {
            foreach ($creators as $creator) {
                $thumbCreators[] = new Reference($creator);
            }
        }
        $definition->replaceArgument(0, $thumbCreators);
    }

}
