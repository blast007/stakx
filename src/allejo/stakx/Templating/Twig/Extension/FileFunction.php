<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Templating\Twig\Extension;

class FileFunction extends AbstractFilesystemTwigExtension implements TwigFunctionInterface
{
    public function __invoke($filePath)
    {
        parent::__invoke($filePath);

        return file_get_contents($this->path);
    }

    public static function get()
    {
        return new \Twig_SimpleFunction('file', new self());
    }
}
