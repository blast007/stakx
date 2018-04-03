<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/allejo/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Test\Templating\Twig\Extension;

use allejo\stakx\Document\FrontMatterDocument;
use allejo\stakx\Document\StaticPageView;
use allejo\stakx\Filesystem\File;
use allejo\stakx\System\Filesystem;
use allejo\stakx\Templating\Twig\TwigExtension;
use allejo\stakx\Test\PHPUnit_Stakx_TestCase;
use allejo\stakx\Templating\Twig\Extension\BaseUrlFunction;

class BaseUrlFunctionTest extends PHPUnit_Stakx_TestCase
{
    /** @var \Twig_Environment */
    private $twig_env;

    public function setUp()
    {
        parent::setUp();

        $extension = new TwigExtension();
        $extension->addFilters([new BaseUrlFunction()]);

        $loader = new \Twig_Loader_Filesystem();
        $this->twig_env = new \Twig_Environment($loader);
        $this->twig_env->addExtension($extension);
    }

    public static function dataProvider()
    {
        $fs = new Filesystem();

        return array(
            array('/toast/link.html', '/toast/', 'link.html'),
            array('/toast/link.html', '/toast', '/link.html'),
            array('/toast/link.html', '/toast/', '//link.html'),
            array('/toast/link.html', '/toast//', '/link.html'),
            array('/toast/link.html', 'toast', '/link.html'),
            array('/toast/link.html', 'toast/', 'link.html'),
            array('/toast/butter/', 'toast/', 'butter/'),
            array('/toast/butter/', '//toast/', '//butter///'),
            array('/toast/', 'toast', null),
            array('/toast/bacon/', 'toast', array(
                'permalink' => 'bacon/',
            )),
            array('/toast/bacon', 'toast', array(
                'permalink' => 'bacon',
            )),
            array('/toast/', 'toast', array(
                'some-key' => 'bacon/',
            )),
            array('/toast/static/', 'toast', (
                new StaticPageView(new File(
                    $fs->appendPath(self::getTestRoot(), 'assets', 'PageViews', 'static.html.twig')
                ))
            )),
        );
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $expected
     * @param $base
     * @param $assetPath
     */
    public function testBaseUrlFunction($expected, $base, $assetPath)
    {
        if ($assetPath instanceof FrontMatterDocument)
        {
            $assetPath->evaluateFrontMatter();
        }

        $this->twig_env->addGlobal('site', array(
            'baseurl' => $base,
        ));

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, $assetPath);

        $this->assertEquals($expected, $url);
    }

    public function testUrlFilterAsAbsolute()
    {
        $this->twig_env->addGlobal('site', array(
            'url' => 'http://domain.com/',
        ));

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, '/path/', true);

        $this->assertEquals('http://domain.com/path/', $url);
    }

    public function testUrlFilterAsAbsoluteWithBaseUrl()
    {
        $this->twig_env->addGlobal('site', array(
            'url' => 'http://domain.com/',
            'baseurl' => '/blog',
        ));

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, '/path/', true);

        $this->assertEquals('http://domain.com/blog/path/', $url);
    }

    public function testUrlFilterAsRelativeWithBaseUrl()
    {
        $this->twig_env->addGlobal('site', array(
            'url' => 'http://domain.com/',
            'baseurl' => '/blog',
        ));

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, '/path/');

        $this->assertEquals('/blog/path/', $url);
    }

    public function testUrlFilterAsRelativeWithNoBaseUrl()
    {
        $this->twig_env->addGlobal('site', array(
            'url' => 'http://domain.com/'
        ));

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, '/path/');

        $this->assertEquals('/path/', $url);
    }

    public function testUrlFilterAsAbsoluteWithoutUrl()
    {
        $this->twig_env->addGlobal('site', array());

        $filter = new BaseUrlFunction();
        $url = $filter($this->twig_env, '/hello/');

        $this->assertEquals('/hello/', $url);
    }
}