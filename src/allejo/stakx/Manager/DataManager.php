<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Manager;

use allejo\stakx\Configuration;
use allejo\stakx\DataTransformer\DataTransformerManager;
use allejo\stakx\Exception\DependencyMissingException;
use allejo\stakx\Document\DataItem;
use allejo\stakx\Exception\UnsupportedDataTypeException;
use allejo\stakx\Utilities\StrUtils;

/**
 * This class handles everything in regards to DataItems and DataSets.
 */
class DataManager extends TrackingManager
{
    private $dataTransformerManager;
    private $configuration;

    /**
     * DataManager constructor.
     */
    public function __construct(DataTransformerManager $dataTransformerManager, Configuration $configuration)
    {
        parent::__construct();

        $this->dataTransformerManager = $dataTransformerManager;
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function compileManager()
    {
        if (!$this->configuration->hasDataItems())
        {
            $this->output->notice('No DataItems or Datasets detected... Ignoring.');
            return;
        }

        $this->parseDataItems($this->configuration->getDataFolders());
        $this->parseDataSets($this->configuration->getDataSets());
    }

    /**
     * Get all of the DataItems and DataSets in this manager.
     *
     * @return array
     */
    public function &getDataItems()
    {
        return $this->trackedItems;
    }

    /**
     * {@inheritdoc}
     */
    public function getJailedDataItems()
    {
        return self::getJailedTrackedItems($this->trackedItemsFlattened);
    }

    /**
     * Loop through all of the DataItems specified in `$folders`. Each folder will have contain just DataItems.
     *
     * For each folder, supported file type is read, parsed, and made available through `$this->getDataItems()`
     *
     * @param string[] $folders An array of folders to be searched for to contain DataItems
     */
    public function parseDataItems($folders)
    {
        if ($folders === null)
        {
            return;
        }

        foreach ($folders as $folder)
        {
            $this->saveFolderDefinition($folder);
            $this->scanTrackableItems($folder);
        }
    }

    /**
     * Loop through all of the DataSets specified in `$dataSets`. Each DataSet contains a name and a folder location.
     *
     * For each folder, supported file type is read, parsed, and made available through `$this->getDataItems()`
     *
     * @param string[] $dataSets An array of DataSets
     */
    public function parseDataSets($dataSets)
    {
        if ($dataSets === null)
        {
            return;
        }

        /**
         * The information which each DataSet has from the configuration file.
         *
         * $dataSet['name']   string The name of the collection
         *         ['folder'] string The folder where this collection has its ContentItems
         *
         * @var array $dataSet
         */
        foreach ($dataSets as $dataSet)
        {
            $this->saveFolderDefinition($dataSet['folder'], array(
                'namespace' => $dataSet['name'],
            ));
            $this->scanTrackableItems(
                $dataSet['folder'],
                array('namespace' => $dataSet['name'])
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function handleTrackableItem($filePath, array $options = array())
    {
        try
        {
            $namespace = (isset($options['namespace'])) ? $options['namespace'] : null;

            $dataItem = new DataItem($filePath);
            $dataItem->setDataTransformer($this->dataTransformerManager);
            $dataItem->setNamespace($namespace);

            $this->addObjectToTracker($dataItem, $namespace);
            $this->saveTrackerOptions($dataItem->getRelativeFilePath(), $options);

            return $dataItem->getRelativeFilePath();
        }
        catch (DependencyMissingException $e)
        {
            if ($e->getDependency() === 'XML')
            {
                $this->output->critical('XML support is not available in your PHP installation. For XML support, please install the appropriate package for your system:');
                $this->output->critical('  e.g. php7.0-xml');
            }
        }
        catch (UnsupportedDataTypeException $e)
        {
            $this->output->warning('There is no function to handle {ext} file format.', [
                'ext' => $e->getDataType(),
            ]);
        }

        return $this->fs->getBaseName($filePath);
    }
}
