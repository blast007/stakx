<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Document;

use allejo\stakx\Filesystem\File;
use allejo\stakx\MarkupEngine\MarkupEngine;
use allejo\stakx\MarkupEngine\MarkupEngineManager;
use allejo\stakx\Templating\TemplateErrorInterface;

class ContentItem extends PermalinkFrontMatterDocument implements CollectableItem, TemplateReadyDocument
{
    use CollectableItemTrait;
    use TemplateEngineDependent;

    /** @var MarkupEngine */
    private $markupEngine;

    public function __construct(File $file)
    {
        $this->noReadOnConstructor = true;

        parent::__construct($file);
    }

    public function setMarkupEngine(MarkupEngineManager $manager)
    {
        $this->markupEngine = $manager->getEngineByExtension($this->getExtension());
        $this->readContent();
    }

    ///
    // Permalink management
    ///

    /**
     * {@inheritdoc}
     */
    public function handleSpecialRedirects()
    {
        $fm = $this->getFrontMatter();

        if (isset($fm['redirect_from']))
        {
            $redirects = $fm['redirect_from'];

            if (!is_array($redirects))
            {
                $redirects = [$redirects];
            }

            $this->redirects = array_merge($this->redirects, $redirects);
        }
    }

    ///
    // Document body transformation
    ///

    /**
     * @throws TemplateErrorInterface
     *
     * @return string
     */
    public function getContent()
    {
        if (!$this->bodyContentEvaluated)
        {
            $this->bodyContent = $this->parseTemplateLanguage($this->bodyContent);
            $this->bodyContent = $this->markupEngine->parse($this->bodyContent);

            $this->bodyContentEvaluated = true;
        }

        return (string)$this->bodyContent;
    }

    /**
     * {@inheritdoc}
     */
    public function createJail()
    {
        $whiteListedFunctions = array_merge(self::$whiteListedFunctions, [
        ]);

        $jailedFunctions = [
            'getPageView'   => 'getJailedPageView',
            'getCollection' => 'getNamespace',
        ];

        return (new JailedDocument($this, $whiteListedFunctions, $jailedFunctions));
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return array_merge($this->getFrontMatter(), [
            'content'   => $this->getContent(),
            'permalink' => $this->getPermalink(),
            'redirects' => $this->getRedirects(),
        ]);
    }
}
