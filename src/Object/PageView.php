<?php

namespace allejo\stakx\Object;

use allejo\stakx\System\Filesystem;
use allejo\stakx\System\StakxResource;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Symfony\Component\Yaml\Yaml;

class PageView extends FrontMatterObject
{
    const TEMPLATE = "---\n%s\n---\n\n%s";

    /**
     * @var vfsStreamDirectory
     */
    private static $vfsRoot;

    /**
     * @var Filesystem
     */
    private static $fileSys;

    /**
     * The Content Items that belong to this Page View. This array will only have elements if it is a dynamic Page View.
     *
     * @var ContentItem[]
     */
    private $contentItems;

    /**
     * @var PageView[]
     */
    private $children;

    /**
     * {@inheritdoc}
     */
    public function __construct($filePath)
    {
        parent::__construct($filePath);

        $this->children = array();
    }

    /**
     * @param ContentItem $contentItem
     */
    public function addContentItem (&$contentItem)
    {
        $filePath = $this->fs->getRelativePath($contentItem->getFilePath());

        $this->contentItems[$filePath] = &$contentItem;
        $contentItem->setPageView($this);
    }

    /**
     * Get child PageViews
     *
     * A child is defined as a static PageView whose URL has a parent. For example, a PageView with a URL of
     * `/gallery/france/` would have the PageView whose URL is `/gallery` as a parent.
     *
     * @return PageView[]
     */
    public function &getChildren ()
    {
        return $this->children;
    }

    /**
     * @return string Twig body
     */
    public function getContent ()
    {
        return $this->bodyContent;
    }

    /**
     * Get all of the Content Items
     *
     * @return ContentItem[]
     */
    public function getContentItems ()
    {
        return $this->contentItems;
    }

    /**
     * A page is considered "dynamic" if it is dynamically generated from data in a collection.
     *
     * @return bool
     */
    public function isDynamicPage ()
    {
        return isset($this->frontMatter['collection']);
    }

    /**
     * A fallback for the site menus that use the `url` field.
     *
     * @deprecated 0.1.0
     * @todo Remove this in the next major release
     */
    public function getUrl ()
    {
        return $this->getPermalink();
    }

    /**
     * Create a virtual PageView to create redirect files
     *
     * @param  string      $redirectFrom     The URL that will be redirecting to the target location
     * @param  string      $redirectTo       The URL of the destination
     * @param  string|bool $redirectTemplate The path to the template
     *
     * @return PageView A virtual PageView with the redirection template
     */
    public static function createRedirect ($redirectFrom, $redirectTo, $redirectTemplate = false)
    {
        $frontMatter  = array(
            'permalink' => $redirectFrom,
            'redirect'  => $redirectTo,
            'menu' => false
        );

        if (!$redirectTemplate || !self::$fileSys->exists(self::$fileSys->absolutePath($redirectTemplate)))
        {
            $contentItemBody = StakxResource::getResource('redirect.html.twig');
        }
        else
        {
            $contentItemBody = file_get_contents(self::$fileSys->absolutePath($redirectTemplate));
        }

        return self::createVirtual($frontMatter, $contentItemBody);
    }

    /**
     * Create a virtual PageView
     *
     * @param  array  $frontMatter The Front Matter that this virtual PageView will have
     * @param  string $body        The body of the virtual PageView
     *
     * @return PageView
     */
    public static function createVirtual ($frontMatter, $body)
    {
        if (is_null(self::$vfsRoot))
        {
            self::$vfsRoot = vfsStream::setup();
            self::$fileSys = new Filesystem();
        }

        $redirectFile = vfsStream::newFile(sprintf('%s.html.twig', uniqid()));
        $redirectFile
            ->setContent(sprintf(self::TEMPLATE, Yaml::dump($frontMatter, 2), $body))
            ->at(self::$vfsRoot);

        return (new PageView($redirectFile->url()));
    }
}