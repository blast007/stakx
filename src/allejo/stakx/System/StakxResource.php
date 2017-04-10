<?php

/**
 * @copyright 2017 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\System;

class StakxResource
{
    /**
     * @var Filesystem
     */
    private static $fs;

    public static function getResource($fileName)
    {
        self::initFileSystem();

        return file_get_contents(
            self::$fs->appendPath(__DIR__, '..', 'Resources', $fileName)
        );
    }

    private static function initFileSystem()
    {
        if (is_null(self::$fs))
        {
            self::$fs = new Filesystem();
        }
    }
}