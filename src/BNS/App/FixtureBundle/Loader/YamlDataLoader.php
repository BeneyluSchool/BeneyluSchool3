<?php

namespace BNS\App\FixtureBundle\Loader;

use BNS\App\CoreBundle\Utils\Console;
use BNS\App\FixtureBundle\Marker\ForeignTableName\ResourceMarker;
use BNS\App\FixtureBundle\Marker\ForeignTableName\UserMarker;
use BNS\App\FixtureBundle\Marker\MarkerManager;
use BNS\App\ResourceBundle\Model\ResourceJoinObject;
use Propel\PropelBundle\DataFixtures\Loader\AbstractDataLoader;
use Propel\PropelBundle\Util\PropelInflector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class YamlDataLoader extends AbstractDataLoader
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var MarkerManager
     */
    private $markerManager;

    /**
     * @var ResourceMarker
     */
    private $resourceMarker;

    /**
     * @param ContainerInterface $container
     * @param InputInterface $input
     */
    public function __construct(ContainerInterface $container = null, InputInterface $input, OutputInterface $output)
    {
        $this->markerManager  = $container->get('fixture.marker_manager');
        $this->resourceMarker = $container->get('fixture.marker.foreign_table_name.resource');
        $this->input          = $input;
        $this->output         = $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function transformDataToArray($file)
    {
        if (strpos($file, "\n") === false && is_file($file)) {
            if (false === is_readable($file)) {
                throw new ParseException(sprintf('Unable to parse "%s" as the file is not readable.', $file));
            }

            ob_start();
            $retval  = include($file);
            $content = ob_get_clean();

            // if an array is returned by the config file assume it's in plain php form else in YAML
            $file = is_array($retval) ? $retval : $content;

            // if an array is returned by the config file assume it's in plain php form else in YAML
            if (is_array($file)) {
                return $file;
            }
        }

        return \Spyc::YAMLLoad($file);
    }

	/**
     * Loads the data using the generated data model.
     *
     * @param array $data The data to be loaded
     */
    protected function loadDataFromArray($data = null)
    {
        if ($data === null) {
            return;
        }

        $primaryKeys = array();
        foreach ($data as $class => $datas) {
            // At the end only
            if ('Resource_Attachment' == $class) {
                continue;
            }

            $namespaces = explode('\\', $class);
            $this->writeSection('    # Loading class ' . $namespaces[count($namespaces) - 1]);
            $count = count($datas);
            $i = 0;
            Console::progress($this->output, $count);

            $class = trim($class);
            if ('\\' == $class[0]) {
                $class = substr($class, 1);
            }
            $tableMap     = $this->dbMap->getTable(constant(constant($class.'::PEER').'::TABLE_NAME'));
            $column_names = call_user_func_array(array(constant($class.'::PEER'), 'getFieldNames'), array(\BasePeer::TYPE_FIELDNAME));

            // iterate through datas for this class
            // might have been empty just for force a table to be emptied on import
            if (!is_array($datas)) {
                continue;
            }

            foreach ($datas as $key => $item) {
                // create a new entry in the database
                if (!class_exists($class)) {
                    throw new \InvalidArgumentException(sprintf('Unknown class "%s".', $class));
                }

                $obj = new $class();

                if (!$obj instanceof \BaseObject) {
                    throw new \RuntimeException(
                        sprintf('The class "%s" is not a Propel class. There is probably another class named "%s" somewhere.', $class, $class)
                    );
                }

                if (!is_array($item)) {
                    throw new \InvalidArgumentException(sprintf('You must give a name for each fixture data entry (class %s).', $class));
                }

                // Retreive primary keys values
                $linkedPrimaryKeys = array();
                foreach ($item as $name => $value) {
                    $isARealColumn = true;
                    if ($tableMap->hasColumn($name)) {
                        $column = $tableMap->getColumn($name);
                    } elseif ($tableMap->hasColumnByPhpName($name)) {
                        $column = $tableMap->getColumnByPhpName($name);
                    } else {
                        $isARealColumn = false;
                    }

                    if ($isARealColumn && $column->isPrimaryKey()) {
                        $linkedPrimaryKeys[$column->getName()] = $class . '_' . $value;
                    }
                }

                foreach ($item as $name => $value) {
                    if (is_array($value) && 's' === substr($name, -1)) {
                        try {
                            // many to many relationship
                            $this->loadManyToMany($obj, substr($name, 0, -1), $value);

                            continue;
                        } catch (\PropelException $e) {
                            // Check whether this is actually an array stored in the object.
                            if ('Cannot fetch TableMap for undefined table: '.substr($name, 0, -1) === $e->getMessage()) {
                                if ('ARRAY' !== $tableMap->getColumn($name)->getType() && 'OBJECT' !== $tableMap->getColumn($name)->getType()) {
                                    throw $e;
                                }
                            }
                        }
                    }

                    $isARealColumn = true;
                    if ($tableMap->hasColumn($name)) {
                        $column = $tableMap->getColumn($name);
                    } elseif ($tableMap->hasColumnByPhpName($name)) {
                        $column = $tableMap->getColumnByPhpName($name);
                    } else {
                        $isARealColumn = false;
                    }

                    // foreign key?
                    if ($isARealColumn) {
                        // self referencing entry
                        if ($column->isPrimaryKey() && null !== $value) {
                            if (isset($this->object_references[$class.'_'.$value])) {
                                $obj = $this->object_references[$class.'_'.$value];

                                continue;
                            }
                        }

                        if ($column->isForeignKey() && null !== $value) {
                            $relatedTable = null;
                            try {
                                $relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
                                $value = $this
									->object_references[$relatedTable->getClassname().'_'.$value]
									->getByName($column->getRelatedName(), \BasePeer::TYPE_COLNAME);
                            }
                            catch (\Exception $e) {
                                // Nothing, marker will be call after
                            }
                        }
                    }

                    // Marker process
                    $marker = $this->markerManager->getMarker($column);
                    if (null !== $marker) {
                        if ($marker instanceof UserMarker) {
                            $marker->setPrimaryKeys($linkedPrimaryKeys);
                        }
                        
                        $value = $marker->load($this->input, $column, $obj, $value);
                    }

                    if (false !== $pos = array_search($name, $column_names)) {
                        $obj->setByPosition($pos, $value);
                    } elseif (is_callable(array($obj, $method = 'set'.ucfirst(PropelInflector::camelize($name))))) {
                        $obj->$method($value);
                    } else {
                        throw new \InvalidArgumentException(sprintf('Column "%s" does not exist for class "%s".', $name, $class));
                    }
                }

                $obj->save($this->con);

                $primaryKeys[$key] = $obj->getPrimaryKey();

                $this->saveParentReference($class, $key, $obj);

                Console::progress($this->output, $count, ++$i);
            }

            // Clearing already used users to avoid integrity constraint error
            UserMarker::clearUsers();

            Console::progress($this->output, $count, $count, true);
        }

        // Attchaments process
        if (isset($data['Resource_Attachment'])) {
            $this->writeSection('    # Loading attachment files');
            $count = 0;
            $i = 0;

            // Just counting
            foreach ($data['Resource_Attachment'] as $object) {
                foreach ($object as $attachments) {
                    foreach ($attachments as $attachment) {
                        ++$count;
                    }
                }
            }

            Console::progress($this->output, $count);
            foreach ($data['Resource_Attachment'] as $class => $object) {
                foreach ($object as $primaryKey => $attachments) {
                    if (!isset($primaryKeys[$primaryKey])) {
                        throw new \RuntimeException('Unknown reference object ' . $primaryKey . ' with resource attachment in your fixture YAML !');
                    }

                    foreach ($attachments as $attachment) {
                        $resourceId = $this->resourceMarker->load($this->input, $column, $obj, $attachment);
                        $attachmentFile = new ResourceJoinObject();
                        $attachmentFile->setObjectClass($class);
                        $attachmentFile->setObjectId($primaryKeys[$primaryKey]);
                        $attachmentFile->setResourceId($resourceId);
                        $attachmentFile->save();

                        Console::progress($this->output, $count, ++$i);
                    }
                }
            }

            Console::progress($this->output, $count, $count, true);
        }
    }

	/**
     * Loads many to many objects.
     *
     * @param BaseObject $obj             A Propel object
     * @param string     $middleTableName The middle table name
     * @param array      $values          An array of values
     */
    protected function loadManyToMany($obj, $middleTableName, $values)
    {
        $middleTable = $this->dbMap->getTable($middleTableName);
        $middleClass = $middleTable->getClassname();
        $tableName   = constant(constant(get_class($obj).'::PEER').'::TABLE_NAME');

        foreach ($middleTable->getColumns() as $column) {
            if ($column->isForeignKey()) {
                if ($tableName !== $column->getRelatedTableName()) {
                    $relatedClass  = $this->dbMap->getTable($column->getRelatedTableName())->getClassname();
                    $relatedSetter = 'set' . $column->getRelation()->getName();
                } else {
                    $setter = 'set' . $column->getRelation()->getName();
                }
            }
        }

        if (!isset($relatedClass)) {
            throw new \InvalidArgumentException(sprintf('Unable to find the many-to-many relationship for object "%s".', get_class($obj)));
        }

        foreach ($values as $value) {
            if (!isset($this->object_references[$relatedClass.'_'.$value])) {
				// TODO make marker
				
                throw new \InvalidArgumentException(
                    sprintf('The object "%s" from class "%s" is not defined in your data file.', $value, $relatedClass)
                );
            }

            $middle = new $middleClass();
            $middle->$setter($obj);
            $middle->$relatedSetter($this->object_references[$relatedClass.'_'.$value]);
            $middle->save($this->con);
        }
    }

	/**
     * Deletes current data.
     *
     * @param array $data The data to delete
     */
    protected function deleteCurrentData($data = null)
    {
        // Deleting resource attachments, this is NOT TableMap class
        if (isset($data['Resource_Attachment'])) {
            unset($data['Resource_Attachment']);
        }

        if ($this->input->getOption('delete')) {
            parent::deleteCurrentData($data);
        }
    }

    /**
	 * @param string $connectionName
	 */
	protected function loadMapBuilders($connectionName = null)
    {
        if (null !== $this->dbMap) {
            return;
        }

        $this->dbMap = \Propel::getDatabaseMap($connectionName);
        if (0 === count($this->dbMap->getTables())) {
            $dir = $this->input->getArgument('bundle_dir');
            $finder = new Finder();
            $files  = $finder->files()->name('*TableMap.php')
                ->in($this->getRootDir() . '/../src/BNS/App/' . $dir . '/Model')
            ;

            foreach ($files as $file) {
                $class = $this->guessFullClassName('src/BNS/App/' . $dir . '/Model/' . $file->getRelativePath(), basename($file, '.php'));

                if (null !== $class && $this->isInDatabase($class, $connectionName)) {
                    $this->dbMap->addTableFromMapClass($class);
                }
            }
        }
    }

	/**
     * Try to find a valid class with its namespace based on the filename.
     * Based on the PSR-0 standard, the namespace should be the directory structure.
     *
     * @param string $path           The relative path of the file.
     * @param string $shortClassName The short class name aka the filename without extension.
     */
    private function guessFullClassName($path, $shortClassName)
    {
        $array = array();
        $path  = str_replace('/', '\\', $path);

        $array[] = $path;
        while ($pos = strpos($path, '\\')) {
            $path = substr($path, $pos + 1, strlen($path));
            $array[] = $path;
        }

        $array = array_reverse($array);
        while ($ns = array_pop($array)) {

            $class = $ns . '\\' . $shortClassName;
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @param string $text
     */
    protected function writeSection($text)
    {
        $this->output->writeln(array(
            '',
            $text,
            '',
        ));
    }
}