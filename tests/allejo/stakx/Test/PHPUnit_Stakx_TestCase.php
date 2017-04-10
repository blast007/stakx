<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Test;

use allejo\stakx\Core\StakxLogger;
use allejo\stakx\Manager\CollectionManager;
use allejo\stakx\System\Filesystem;
use allejo\stakx\System\Folder;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

abstract class PHPUnit_Stakx_TestCase extends \PHPUnit_Framework_TestCase
{
    const FM_OBJ_TEMPLATE = "---\n%s\n---\n\n%s";

    /** @var string */
    protected $assetFolder;

    /**
     * @var vfsStreamFile
     */
    protected $dummyFile;

    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    /**
     * @var Filesystem
     */
    protected $fs;

    public function setUp()
    {
        $this->dummyFile = vfsStream::newFile('stakx.html.twig');
        $this->rootDir = vfsStream::setup();
        $this->fs = new Filesystem();

        // Inspect the VFS as an array
        // vfsStream::inspect(new vfsStreamStructureVisitor())->getStructure();
    }

    ///
    // Assertion functions
    ///

    protected function assertStringContains($needle, $haystack, $message = '')
    {
        $this->assertNotFalse(strpos($haystack, $needle), $message);
    }

    protected function assertFileContains($fileContent, $filePath, $message = '')
    {
        (substr($filePath, -1, 1) == '/') && $filePath .= 'index.html';

        $contents = file_get_contents($filePath);

        $this->assertStringContains($fileContent, $contents, $message);
    }

    ///
    // Utility Functions
    ///

    protected function bookCollectionProvider($jailed = false)
    {
        $cm = new CollectionManager();
        $cm->setLogger($this->getMockLogger());
        $cm->parseCollections(array(
            array(
                'name' => 'books',
                'folder' => 'tests/allejo/stakx/Test/assets/MyBookCollection/',
            ),
        ));

        return (!$jailed) ? $cm->getCollections() : $cm->getJailedCollections();
    }

    /**
     * @param string $classType
     * @param array  $frontMatter
     * @param string $body
     *
     * @return mixed
     */
    protected function createVirtualFile($classType, $frontMatter = array(), $body = 'Body Text')
    {
        $this->dummyFile
            ->setContent($this->generateFM($frontMatter, $body))
            ->at($this->rootDir);

        return new $classType($this->dummyFile->url());
    }

    protected function createMultipleVirtualFiles($classType, $elements)
    {
        $results = array();

        foreach ($elements as $element) {
            $filename = (isset($element['filename'])) ? $element['filename'] : hash('sha256', uniqid(mt_rand(), true), false);
            $frontMatter = (empty($element['frontmatter'])) ? '' : Yaml::dump($element['frontmatter'], 2);
            $body = (isset($element['body'])) ? $element['body'] : 'Body Text';

            $file = vfsStream::newFile($filename);
            $file
                ->setContent(sprintf("---\n%s\n---\n\n%s", $frontMatter, $body))
                ->at($this->rootDir);

            $results[] = new $classType($file->url());
        }

        return $results;
    }

    /**
     * Get a mock logger.
     *
     * @return LoggerInterface
     */
    protected function getMockLogger()
    {
        return $this->getMock(LoggerInterface::class);
    }

    /**
     * Get a real logger instance that will save output to the console.
     *
     * @return StakxLogger
     */
    protected function getReadableLogger()
    {
        stream_filter_register('intercept', StreamInterceptor::class);
        $stakxLogger = new StakxLogger(new ConsoleOutput());
        stream_filter_append($stakxLogger->getOutputInterface()->getStream(), 'intercept');

        return $stakxLogger;
    }

    /**
     * Generate a FrontMatter-ready syntax to be used as a file's content.
     *
     * @param array  $frontMatter
     * @param string $body
     *
     * @return string
     */
    protected function generateFM(array $frontMatter = array(), $body = 'Body text')
    {
        $fm = (empty($frontMatter)) ? '' : Yaml::dump($frontMatter, 2);

        return sprintf(self::FM_OBJ_TEMPLATE, $fm, $body);
    }

    /**
     * Create a temporary folder where temporary file writes will be made to
     *
     * Remember to remove the folder in during the ::tearDown()
     *
     * @param string $folderName
     */
    protected function createAssetFolder($folderName)
    {
        $this->assetFolder = $this->fs->getRelativePath($this->fs->appendPath(__DIR__, $folderName));

        $this->fs->mkdir($this->assetFolder);
    }

    /**
     * Write a temporary file to the asset folder
     *
     * @param $fileName
     * @param $content
     *
     * @return string Path to the temporary file; relative to the project's root
     */
    protected function writeTempFile($fileName, $content)
    {
        $folder = new Folder($this->assetFolder);
        $folder->writeFile($fileName, $content);

        return $this->fs->appendPath($this->assetFolder, $fileName);
    }
}