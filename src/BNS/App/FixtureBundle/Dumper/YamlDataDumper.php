<?php

namespace BNS\App\FixtureBundle\Dumper;

use BNS\App\CoreBundle\Utils\StringUtil;
use BNS\App\FixtureBundle\Marker\ForeignTableName\ResourceMarker;
use BNS\App\FixtureBundle\Marker\MarkerManager;
use BNS\App\ResourceBundle\Model\ResourceJoinObjectQuery;
use Propel\PropelBundle\DataFixtures\Dumper\YamlDataDumper as BaseDumper;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class YamlDataDumper extends BaseDumper
{
    /**
     * @var MarkerManager
     */
    private $markerManager;

    /**
     * @var ResourceMarker
     */
    private $resourceMarker;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var DialogHelper
     */
    private $dialog;

	/**
	 * @var string
	 */
	private $fileQuery;

	/**
	 * @var string
	 */
	private $dir;

	/**
	 *
	 * @param string $rootDir
	 * @param string $fileQuery
	 * @param string $dir
	 */
	public function __construct(ContainerInterface $container, InputInterface $input, OutputInterface $output, DialogHelper $dialog)
	{
		$this->rootDir        = $container->getParameter('kernel.root_dir');
        $this->markerManager  = $container->get('fixture.marker_manager');
        $this->resourceMarker = $container->get('fixture.marker.foreign_table_name.resource');
        $this->input          = $input;
        $this->output         = $output;
        $this->dialog         = $dialog;
		$this->fileQuery      = $input->getArgument('file_query');
		$this->dir            = $input->getArgument('bundle_dir');
	}

    /**
     * {@inheritdoc}
     */
    protected function transformArrayToData($data)
    {
        return \Spyc::YAMLDump($data);
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
            $finder = new Finder();
            $files  = $finder->files()->name($this->fileQuery . '*TableMap.php');

			if (null !== $this->dir) {
                $files->in($this->getRootDir() . '/../src/BNS/App/' . $this->dir . '/Model');
			}
			else {
				$files->in($this->getRootDir() . '/../')
					->exclude('Central')
					->exclude('PropelBundle')
					->exclude('HelloWorldBundle')
				;
			}

            foreach ($files as $file) {
                $class = $this->guessFullClassName((null !== $this->dir ? 'src/BNS/App/' . $this->dir . '/Model/' : '') . $file->getRelativePath(), basename($file, '.php'));

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
     * Dumps data to fixture from a given connection and
     * returns an array.
     *
     * @param  string $connectionName The connection name
     * @return array
     */
    protected function getDataAsArray()
    {
        $tables = array();
        foreach ($this->dbMap->getTables() as $table) {
            $tables[] = $table->getClassname();
        }

        $tables = $this->fixOrderingOfForeignKeyData($tables);

        $dumpData = array();
        foreach ($tables as $tableName) {
            $tableMap    = $this->dbMap->getTable(constant(constant($tableName.'::PEER').'::TABLE_NAME'));
            $hasParent   = false;
            $haveParents = false;
            $fixColumn   = null;

            $shortTableName = substr($tableName, strrpos($tableName, '\\') + 1, strlen($tableName));

            foreach ($tableMap->getColumns() as $column) {
                $col = strtolower($column->getName());
            }

            if ($haveParents) {
                // unable to dump tables having multi-recursive references
                continue;
            }

            // get db info
            $resultsSets = array();
            if ($hasParent) {
                $resultsSets[] = $this->fixOrderingOfForeignKeyDataInSameTable($resultsSets, $tableName, $fixColumn);
            } else {
                $in = array();
                foreach ($tableMap->getColumns() as $column) {
                    $in[] = '`' . strtolower($column->getName()) . '`';
                }
                $stmt = $this
                    ->con
                    ->query(sprintf('SELECT %s FROM `%s`', implode(',', $in), constant(constant($tableName.'::PEER').'::TABLE_NAME')));

                $resultsSets[] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                unset($stmt);
            }

            foreach ($resultsSets as $rows) {
                if (count($rows) > 0 && !isset($dumpData[$tableName])) {
                    $dumpData[$tableName] = array();

                    foreach ($rows as $row) {
                        $pk          = $shortTableName;
                        $values      = array();
                        $primaryKeys = array();
                        $foreignKeys = array();

                        foreach ($tableMap->getColumns() as $column) {
                            $col = strtolower($column->getName());
                            $isPrimaryKey = $column->isPrimaryKey();

                            if (null === $row[$col]) {
                                continue;
                            }

                            if ($isPrimaryKey) {
                                $value = $row[$col];
                                $pk .= '_'.$value;
                                $primaryKeys[$col] = $value;
                            }

                            if ($column->isForeignKey()) {
                                $value = $row[$col];
                                $marker = $this->markerManager->getMarker($column);

                                // Foreign markers
                                if (null !== $marker) {
                                    $this->output->writeln('  - Dumping a marker ' . $marker . ' for ' . $tableMap->getName() . '#' . StringUtil::arrayToString($primaryKeys, true));
                                    $value = $marker->dump($this->input, $this->output, $this->dialog, $column, $value);
                                    $this->output->writeln('');

                                    $values[$col] = $value;

                                    continue;
                                }

								$relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
                                if ($isPrimaryKey) {
                                    $foreignKeys[$col] = $row[$col];
                                    $primaryKeys[$col] = $relatedTable->getPhpName().'_'.$row[$col];
                                } else {
                                    $values[$col] = $relatedTable->getPhpName().'_'.$row[$col];
                                    $values[$col] = strlen($row[$col]) ? $relatedTable->getPhpName().'_'.$row[$col] : '';
                                }
                            } elseif (!$isPrimaryKey || ($isPrimaryKey && !$tableMap->isUseIdGenerator())) {
                                if (!empty($row[$col]) && 'ARRAY' === $column->getType()) {
                                    $serialized = substr($row[$col], 2, -2);
                                    $row[$col] = $serialized ? explode(' | ', $serialized) : array();
                                }

                                // We did not want auto incremented primary keys
                                $value = $row[$col];
                                $marker = $this->markerManager->getMarker($column);
                                if (null !== $marker) {
                                    $this->output->writeln('  - Dumping a marker ' . $marker . ' for ' . $tableMap->getName() . '#' . StringUtil::arrayToString($primaryKeys, true) . '::' . $col);
                                    $value = $marker->dump($this->input, $this->output, $this->dialog, $column, $value);
                                    $this->output->writeln('');
                                }

                                $values[$col] = $value;
                            }

                            if ($column->getType() == \PropelColumnTypes::OBJECT) {
                                $values[$col] = unserialize($row[$col]);
                            }
                        }

                        if (count($primaryKeys) > 1 || (count($primaryKeys) > 0 && count($foreignKeys) > 0)) {
                            $values = array_merge($primaryKeys, $values);
                        }

                        $dumpData[$tableName][$pk] = $values;

                        // Attachment process
                        $behaviors = $tableMap->getBehaviors();
                        if (isset($behaviors['bns_media_attachmentable'])) {
                            if (count($primaryKeys) > 1) {
                                throw new \RuntimeException('Resource join attachment do NOT support more than one primary for the foreign table !');
                            }

                            $this->output->writeln('  - Dumping attachments resource for ' . $tableMap->getName() . '#' . StringUtil::arrayToString($primaryKeys, true));
                            foreach ($primaryKeys as $primaryKey);

                            $attchments = ResourceJoinObjectQuery::create('rjo')
                               ->joinWith('rjo.Resource r')
                               ->where('rjo.ObjectClass = ?', $tableMap->getPhpName())
                               ->where('rjo.ObjectId = ?', $primaryKey)
                            ->find();

                            foreach ($attchments as $attchment) {
                                $dumpData['Resource_Attachment'][$tableMap->getPhpName()][$pk][] = $this->resourceMarker->dump($this->input, $this->output, $this->dialog, $column, $attchment->getResource()->getId());
                            }

                            $this->output->writeln('');
                        }
                    }
                }
            }
        }

        return $dumpData;
    }

	/**
     * Fixes the ordering of foreign key data, by outputting data
     * a foreign key depends on before the table with the foreign key.
     *
     * @param  array $classes The array with the class names
     * @return array
     */
    protected function fixOrderingOfForeignKeyData($classes)
    {
        // reordering classes to take foreign keys into account
        for ($i = 0, $count = count($classes); $i < $count; $i++) {
            $class    = $classes[$i];
            $tableMap = null;

			try {
				$tableMap = $this->dbMap->getTable(constant(constant($class.'::PEER').'::TABLE_NAME'));
			}
			catch (\PropelException $e) {
				continue;
			}

            foreach ($tableMap->getColumns() as $column) {
                if ($column->isForeignKey()) {
					$relatedTable = null;

					try {
						$relatedTable = $this->dbMap->getTable($column->getRelatedTableName());
					}
					catch (\PropelException $e) {
						continue;
					}

                    $relatedTablePos = array_search($relatedTable->getClassname(), $classes);

                    // check if relatedTable is after the current table
                    if ($relatedTablePos > $i) {
                        // move related table 1 position before current table
                        $classes = array_merge(
                            array_slice($classes, 0, $i),
                            array($classes[$relatedTablePos]),
                            array_slice($classes, $i, $relatedTablePos - $i),
                            array_slice($classes, $relatedTablePos + 1)
                        );
                        // we have moved a table, so let's see if we are done
                        return $this->fixOrderingOfForeignKeyData($classes);
                    }
                }
            }
        }

        return $classes;
    }
}
