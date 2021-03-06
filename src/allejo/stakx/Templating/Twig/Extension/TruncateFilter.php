<?php

/**
 * @copyright 2018 Vladimir Jimenez
 * @license   https://github.com/stakx-io/stakx/blob/master/LICENSE.md MIT
 */

namespace allejo\stakx\Templating\Twig\Extension;

/**
 * This filter is adapted from the Twig Text extension.
 *
 * @copyright 2009 Fabien Potencier
 * @author Henrik Bjornskov <hb@peytz.dk>
 *
 * @see https://github.com/twigphp/Twig-extensions
 */
class TruncateFilter extends AbstractTwigExtension implements TwigFilterInterface
{
    public function __invoke(\Twig_Environment $env, $value, $length = 30, $preserve = false, $separator = '...')
    {
        if (mb_strlen($value, $env->getCharset()) > $length)
        {
            if ($preserve)
            {
                // If breakpoint is on the last word, return the value without separator.
                if (($breakpoint = mb_strpos($value, ' ', $length, $env->getCharset())) === false)
                {
                    return $value;
                }

                $length = $breakpoint;
            }

            return rtrim(mb_substr($value, 0, $length, $env->getCharset())) . $separator;
        }

        return $value;
    }

    /**
     * @return \Twig_SimpleFilter
     */
    public static function get()
    {
        return new \Twig_SimpleFilter('truncate', new self(), [
            'needs_environment' => true,
        ]);
    }
}
