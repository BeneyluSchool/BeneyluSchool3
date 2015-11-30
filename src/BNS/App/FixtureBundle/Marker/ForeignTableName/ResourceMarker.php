<?php

namespace BNS\App\FixtureBundle\Marker\ForeignTableName;

use BNS\App\FixtureBundle\Marker\MarkerManager;
use BNS\App\ResourceBundle\BNSResourceManager;
use BNS\App\ResourceBundle\Creator\BNSResourceCreator;
use BNS\App\ResourceBundle\Model\ResourceLabelGroup;
use BNS\App\ResourceBundle\Model\ResourceLabelGroupQuery;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class ResourceMarker extends AbstractForeignTableNameMarker
{
    /**
     * @var array<String, #Resource> 
     */
    public static $files;

    /**
     * @var array<String, Resource>
     */
    public static $resources;

    /**
     * @var MarkerManager
     */
    private $markerManager;

    /**
     * @var BNSResourceManager
     */
    private $resourceManager;

    /**
     * @var BNSResourceCreator
     */
    private $resourceCreator;

    /**
     * @param \BNS\App\FixtureBundle\Marker\MarkerManager $markerManager
     * @param \BNS\App\ResourceBundle\BNSResourceManager $resourceManager
     * @param BNSResourceCreator $resourceCreator
     */
    public function __construct(MarkerManager $markerManager, BNSResourceManager $resourceManager, BNSResourceCreator $resourceCreator)
    {
        $this->markerManager   = $markerManager;
        $this->resourceManager = $resourceManager;
        $this->resourceCreator = $resourceCreator;
    }

    /**
     * @return string 
     */
    public function getTableName()
    {
        return 'resource';
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param DialogHelper $dialog
     * @param \ColumnMap $column
     * @param mixed $value
     *
     * @return string
     */
    public function dump(InputInterface $input, OutputInterface $output, DialogHelper $dialog, \ColumnMap $column, $value)
    {
        // Downloading the resource
        $resource = ResourceQuery::create('r')->findPk($value);
        if (null == $resource) {
            throw new \RuntimeException('The resource #' . $value . ' is NOT found !');
        }

        // Save resource for TextResourceMarker call
        self::$resources[$resource->getSlug()] = $resource;

        $resourceFile = null;
        $path = $this->resourceManager->setObject($resource)->getAbsoluteFilePath();
        
        try {
            $resourceFile = file_get_contents($path);
        }
        catch (\Exception $e) {
            throw new \RuntimeException('The resource #' . $resource->getId() . ' file "' . $path . '" is NOT found ! Please re-upload the file or delete the resource link');
        }

        $dir = __DIR__ . '/../../../' . $input->getArgument('bundle_dir') . '/Resources/fixtures/resources/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $file = new \SplFileInfo($path);
        file_put_contents($dir . $resource->getSlug() . '.' . $file->getExtension(), $resourceFile);

        return 'RESOURCE(' . $resource->getSlug() . ',' . str_replace(' ', '_', $resource->getLabel()) . ')';
    }

    /**
     * @param \ColumnMap $column
     * @param Object $obj
     * @param mixed $value
     *
     * @return id
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \LogicException
     */
    public function load(InputInterface $input, \ColumnMap $column, $obj, $value)
    {
        if (!$matches = $this->getMarkers('RESOURCE', $value)) {
            throw new \InvalidArgumentException('Unknown marker for foreign table name ' . $this->getTableName() . ' value: ' . $value);
        }

        list($resourceSlug, $resourceLabel) = explode(',', $matches[0]);
        $resourceLabel = str_replace('_', ' ', $resourceLabel);

        // Different checks before processing
        $dir = __DIR__ . '/../../../' . $input->getArgument('bundle_dir') . '/Resources/fixtures/resources/';
        if (!is_dir($dir)) {
            throw new \RuntimeException('There is NO resource in the fixtures resources folder !');
        }

        $label = ResourceLabelGroupQuery::create('rlag')
            ->where('rlag.Label = ?', 'Documents')
            ->where('rlag.GroupId = ?', $this->markerManager->getGroup()->getId())
        ->findOne();

        if (null == $label) {
            $groupId = $this->markerManager->getGroup()->getId();
            $root = ResourceLabelGroupQuery::create('rlag')->findRoot($groupId);
            
            $label = new ResourceLabelGroup();
            $label->setGroupId($groupId);
            $label->setLabel('Documents');
            $label->insertAsLastChildOf($root);
            $label->save();
        }

        if (!isset(self::$files)) {
            $finder = new Finder();
            self::$files = $finder->files()->name('*')
                ->in($dir)
            ;
        }

        // Setting the author resource
        $users = $this->markerManager->getGroupUsers();
        $this->resourceManager->setUser($users['TEACHER'][rand(0, count($users['TEACHER']) - 1)]);

        // Uploading process
        if (isset(self::$resources[$resourceSlug])) {
            return self::$resources[$resourceSlug]->getId();
        }
        
        foreach (self::$files as $path => $file) {
            if ($resourceSlug == substr($file->getFileName(), 0, -strlen($file->getExtension()) - 1)) {
                $resource = $this->resourceCreator->createResourceFromFile($path, $file->getFileName(), $file->getExtension(), $resourceLabel, $label, false);
                self::$resources[$resourceSlug] = $resource;
                
                return $resource->getId();
            }
        }

        throw new \RuntimeException('The resource with slug "' . $resourceSlug . '" is NOT found in the fixtures resources folder !');
    }
}