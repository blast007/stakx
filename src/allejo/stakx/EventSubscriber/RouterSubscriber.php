<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\EventSubscriber;

use allejo\stakx\Event\PageViewAdded;
use allejo\stakx\Server\PageViewRouter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RouterSubscriber implements EventSubscriberInterface
{
    private $routerMapping;

    public function __construct(PageViewRouter $routerMapping)
    {
        $this->routerMapping = $routerMapping;
    }

    public function registerPageView(PageViewAdded $event)
    {
        $this->routerMapping->registerPageView($event->getPageView());
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            PageViewAdded::NAME => 'registerPageView',
        ];
    }
}
