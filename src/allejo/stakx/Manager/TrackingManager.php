<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Manager;

use allejo\stakx\Document\CollectableItem;
use allejo\stakx\Document\JailedDocument;
use allejo\stakx\Document\ReadableDocument;
use allejo\stakx\Document\TemplateReadyDocument;
use allejo\stakx\Filesystem\File;
use allejo\stakx\Filesystem\FileExplorer;
use allejo\stakx\Filesystem\FilesystemLoader as fs;
use allejo\stakx\RuntimeStatus;
use allejo\stakx\Service;

/**
 * Class TrackingManager.
 */
abstract class TrackingManager extends BaseManager
{
    /**
     * @var FileExplorer
     */
    protected $fileExplorer = null;

    /**
     * An array corresponding with $folderDefinitions to store metadata regarding a specificc folder.
     *
     * $folderDefinitionsOption['<folder path>'] = array()
     *
     * @var string[]
     */
    protected $folderDefinitionsOptions = [];

    /**
     * An array of folders which tracked items are stored in.
     *
     * $folderDefinitions[] = '<folder path>'
     *
     * @var string[]
     */
    protected $folderDefinitions = [];

    /**
     * The storage which contains the same information as $trackedItems but organized by relative file path instead of a
     * namespace or file name without extension.
     *
     * $trackedItemsOptions['<relative file path>'] = mixed
     *
     * @var array
     */
    protected $trackedItemsFlattened = [];

    /**
     * The storage used to cache any information needed for a specific FrontMatterObject or DataItem.
     *
     * For example, with a DataItem, which is just an array, the file path to the original file can be stored in this
     * array to be accessible in the future to refresh the contents without parsing all of the files again.
     *
     * $trackedItemsOptions['<relative file path>'] = array
     *
     * @var array
     */
    protected $trackedItemsOptions = [];

    /**
     * The storage used for ReadableDocument in the respective static classes.
     *
     * $trackedItems['<namespace>']['<file name w/o extension>'] = mixed
     * $trackedItems['<file name w/o extension>'] = mixed
     *
     * @var array
     */
    protected $trackedItems = [];

    /**
     * @param File|string $filePath
     *
     * @return mixed|null
     */
    public function createNewItem($filePath)
    {
        return $this->handleTrackableItem($filePath);
    }

    /**
     * Check whether a file is already being tracked.
     *
     * @param string $filePath The relative path of the file
     *
     * @return bool
     */
    public function isTracked($filePath)
    {
        return array_key_exists((string)$filePath, $this->trackedItemsFlattened);
    }

    /**
     * Check to see if a given file path matches this tracker's definition and would be tracked.
     *
     * This function should be used to check whether or not to add a file to this tracker after the initial scan has
     * already happened.
     *
     * @param string $filePath
     *
     * @return bool True if the file is inside a tracked folder
     */
    public function shouldBeTracked($filePath)
    {
        foreach ($this->folderDefinitions as $folder)
        {
            if (substr($filePath, 0, strlen($folder)) === $folder)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the contents of a specified file.
     *
     * @param File|string $filePath The relative path of the file
     *
     * @return mixed|null
     */
    public function refreshItem($filePath)
    {
        return $this->handleTrackableItem($filePath);
    }

    ///
    // Internal object handling
    ///

    protected function addFileToTracker(File &$file)
    {
        $this->trackedItemsFlattened[$file->getRelativeFilePath()] = &$file;
    }

    protected function delFileFromTracker(File &$file)
    {
        unset($this->trackedItemsFlattened[$file->getRelativeFilePath()]);
    }

    /**
     * Add a ReadableDocument to the tracker.
     *
     * @param ReadableDocument $trackedItem
     * @param string|null      $namespace
     */
    protected function addObjectToTracker(ReadableDocument &$trackedItem, $namespace = null)
    {
        if ($namespace == null)
        {
            $this->trackedItems[$trackedItem->getIndexName()] = &$trackedItem;
        }
        else
        {
            $this->trackedItems[$namespace][$trackedItem->getIndexName()] = &$trackedItem;
        }

        $this->trackedItemsFlattened[$trackedItem->getIndexName()] = &$trackedItem;
    }

    /**
     * Remove an entry from the tracked items array.
     *
     * @param ReadableDocument $trackedItem
     * @param string|null      $namespace
     */
    protected function delObjectFromTracker(ReadableDocument &$trackedItem, $namespace = null)
    {
        if ($namespace == null)
        {
            unset($this->trackedItems[$trackedItem->getIndexName()]);
        }
        else
        {
            unset($this->trackedItems[$namespace][$trackedItem->getIndexName()]);
        }

        unset($this->trackedItemsFlattened[$trackedItem->getIndexName()]);
    }

    ///
    // Extra options stored for future use
    ///

    /**
     * Save a folder that is tracked by this manager and its respective options.
     *
     * @param string $folderPath
     * @param array  $options
     */
    protected function saveFolderDefinition($folderPath, array $options = [])
    {
        $this->folderDefinitions[] = $folderPath;
        $this->folderDefinitionsOptions[(string)$folderPath] = $options;
    }

    /**
     * Save any options related to an item needed in order to refresh the content.
     *
     * @param string $filePath
     * @param array  $options
     */
    protected function saveTrackerOptions($filePath, array $options = [])
    {
        $this->trackedItemsOptions[$filePath] = $options;
    }

    /**
     * Delete any options that were saved corresponding to an item.
     *
     * @param string $filePath
     */
    protected function forgetTrackerOptions($filePath)
    {
        unset($this->trackedItemsOptions[$filePath]);
    }

    ///
    // Handling of trackable items
    ///

    /**
     * Parse the specified folder for items to track.
     *
     * @param string $path
     * @param array  $options  Special options that will be passed to the static::parseTrackableItem() implementation
     * @param array  $includes
     * @param array  $excludes
     */
    protected function scanTrackableItems($path, array $options = [], array $includes = [], array $excludes = [])
    {
        $this->folderDefinitions[] = fs::getRelativePath($path);

        $excludes = empty($excludes) ? self::$documentIgnoreList : $excludes;

        $fileExplorerFlags = array_key_exists('fileExplorer', $options) ? $options['fileExplorer'] : null;
        $this->fileExplorer = FileExplorer::create($path, $excludes, $includes, $fileExplorerFlags);
        $fileExplorer = $this->fileExplorer->getFileIterator();

        foreach ($fileExplorer as $file)
        {
            $this->handleTrackableItem($file, $options);
        }
    }

    /**
     * Handle a specific file type, parse it into the appropriate object type, and add it to the tracker.
     *
     * This function should make use of the appropriate functions:
     *
     *  - TrackingManager::addObjectToTracker()
     *  - TrackingManager::addFileToTracker()
     *  - TrackingManager::saveTrackerOptions()
     *
     * @param File  $filePath
     * @param array $options
     *
     * @return mixed|null
     */
    abstract protected function handleTrackableItem(File $filePath, array $options = []);

    ///
    // Utility functions
    ///

    /**
     * Return an array of JailedDocuments created from the tracked items.
     *
     * @param array    $elements
     * @param \Closure $name
     *
     * @return JailedDocument[]|JailedDocument[][]
     */
    protected static function getJailedTrackedItems(array &$elements, \Closure $name = null)
    {
        $jailItems = [];

        /**
         * @var string
         * @var CollectableItem|ReadableDocument $item
         */
        foreach ($elements as &$item)
        {
            if ($item instanceof TemplateReadyDocument)
            {
                if (!Service::hasRunTimeFlag(RuntimeStatus::USING_DRAFTS) && $item->isDraft())
                {
                    continue;
                }
            }

            $keyName = ($name === null) ? $item->getRelativeFilePath() : $name($item);

            if (empty($item->getNamespace()))
            {
                $jailItems[$keyName] = $item->createJail();
            }
            else
            {
                $jailItems[$item->getNamespace()][$keyName] = $item->createJail();
            }
        }

        return $jailItems;
    }
}
