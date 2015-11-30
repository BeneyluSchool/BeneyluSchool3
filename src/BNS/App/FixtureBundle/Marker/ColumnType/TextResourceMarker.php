<?php

namespace BNS\App\FixtureBundle\Marker\ColumnType;

use BNS\App\CoreBundle\Date\ExtendedDateTime;
use BNS\App\FixtureBundle\Marker\ForeignTableName\ResourceMarker;
use BNS\App\ResourceBundle\DependencyInjection\TwigExtensions\ResourceExtension;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class TextResourceMarker extends AbstractColumnTypeMarker
{
    /**
     * @var ResourceMarker
     */
    private $resourceMarker;

    /**
     * @var ResourceExtension
     */
    private $resourceExtension;

    /**
     * @param ResourceMarker $resourceMarker
     */
    public function __construct(ResourceMarker $resourceMarker, ResourceExtension $resourceExtension)
    {
        $this->resourceMarker    = $resourceMarker;
        $this->resourceExtension = $resourceExtension;
    }

    /**
     * @return string
     */
    public function getColumnType()
    {
        return 'longvarchar';
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param DialogHelper    $dialog
     * @param \ColumnMap      $column
     * @param mixed           $value
     *
     * @return string
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value)
    {
        $html = null;
        try {
			$html = \simple_html_dom::str_get_html($value);
            if (null == $html->root) {
				return $value;
			}

            $resourcesLinks = $html->find('img[data-slug="*"]');
            foreach ($resourcesLinks as $resourceLink) {
				if (null != $resourceLink->attr && isset($resourceLink->attr['data-slug']) &&
                    isset($resourceLink->attr['data-id']) && isset($resourceLink->attr['data-uid'])) {
                    $this->resourceMarker->dump($input, $output, $dialog, $column, $resourceLink->attr['data-id']);
                    $resource = ResourceMarker::$resources[$resourceLink->attr['data-slug']];
                    $resourceLink->setAttribute('data-filename', $resource->getFileName());
                }
            }

            $html = $html->save();
        }
        catch (\Exception $e) {
            return $value;
        }

        return $html;
    }

    /**
     * @param \ColumnMap $column
     * @param Object $obj
     * @param mixed $value
     *
     * @return ExtendedDateTime
     *
     * @throws \InvalidArgumentException
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value)
    {
        $proceed = array();
        $html = null;

        try {
			$html = \simple_html_dom::str_get_html($value);
            if (null == $html->root) {
				return $value;
			}
        }
        catch (\Exception $e) {
            return $value;
        }

        $resourcesLinks = $html->find('img[data-filename="*"]');
        foreach ($resourcesLinks as $resourceLink) {
            if (null != $resourceLink->attr && isset($resourceLink->attr['data-slug']) &&
                isset($resourceLink->attr['data-id']) && isset($resourceLink->attr['data-uid']) && isset($resourceLink->attr['data-filename'])) {
                if (!isset($proceed[$resourceLink->attr['data-slug']])) {
                    $this->resourceMarker->load($input, $column, $obj, 'RESOURCE(' . $resourceLink->attr['data-slug'] . ',' . $resourceLink->attr['data-filename'] . ')');

                    $resource = ResourceMarker::$resources[$resourceLink->attr['data-slug']];
                    if (null == $resource) {
                        throw new \RuntimeException('Resource "' . $resourceLink->attr['data-filename'] . '" has not been uploaded ?');
                    }

                    $proceed[$resourceLink->attr['data-slug']] = $resource;
                }
                else {
                    $resource = $proceed[$resourceLink->attr['data-slug']];
                }

                $resourceLink->setAttribute('data-slug', $resource->getSlug());
                $resourceLink->setAttribute('data-id', $resource->getId());
                $resourceLink->setAttribute('data-uid', $resource->getUserId());
                $resourceLink->setAttribute('src', $this->resourceExtension->createVisualisationUrlResource($resource, false, true));

                $resourceLink->removeAttribute('data-filename');
            }
        }

        $html = $html->save();

        return $html;
    }
}