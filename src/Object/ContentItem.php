<?php

namespace allejo\stakx\Object;

use allejo\stakx\Engines\MarkdownEngine;
use allejo\stakx\Engines\RST\SyntaxBlock;
use allejo\stakx\System\Filesystem;
use allejo\stakx\Exception\YamlVariableNotFound;
use Gregwar\RST\Parser as RstEngine;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Yaml\Yaml;

class ContentItem
{
    /**
     * Set to true if the front matter has already been evaluated with variable interpolation
     *
     * @var bool
     */
    protected $frontMatterEvaluated;

    /**
     * An array containing the Yaml of the file
     *
     * @var array
     */
    protected $frontMatter;

    /**
     * Set to true if the body has already been parsed as markdown or any other format
     *
     * @var bool
     */
    protected $bodyContentEvaluated;

    /**
     * Only the body of the file, i.e. the content
     *
     * @var string
     */
    protected $bodyContent;

    /**
     * The extension of the file
     *
     * @var string
     */
    protected $extension;

    /**
     * The original file path to the ContentItem
     *
     * @var string
     */
    protected $filePath;

    /**
     * A filesystem object
     *
     * @var Filesystem
     */
    protected $fs;

    /**
     * ContentItem constructor.
     *
     * @param string $filePath The path to the file that will be parsed into a ContentItem
     *
     * @throws FileNotFoundException The given file path does not exist
     * @throws IOException           The file was not a valid ContentItem. This would meam there was no front matter or
     *                               no body
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
        $this->fs       = new Filesystem();

        if (!$this->fs->exists($filePath))
        {
            throw new FileNotFoundException("The following file could not be found: ${filePath}");
        }

        $this->extension = strtolower($this->fs->getExtension($filePath));
        $rawFileContents = file_get_contents($filePath);

        $frontMatter = array();
        preg_match('/---(.*?)---(.*)/s', $rawFileContents, $frontMatter);

        if (count($frontMatter) != 3)
        {
            throw new IOException(sprintf("'%s' is not a valid ContentItem",
                $this->fs->getFileName($filePath))
            );
        }

        if (empty(trim($frontMatter[2])))
        {
            throw new IOException(sprintf('A ContentItem (%s) must have a body to render',
                $this->fs->getFileName($filePath))
            );
        }

        $this->frontMatter = Yaml::parse($frontMatter[1]);
        $this->bodyContent = trim($frontMatter[2]);

        $this->handleDefaults();
    }

    /**
     * The magic getter returns values from the front matter in order to make these values accessible to Twig templates
     * in a simple fashion
     *
     * @param  string $name The key in the front matter
     *
     * @return mixed|null
     */
    public function __get ($name)
    {
        return (array_key_exists($name, $this->frontMatter) ? $this->frontMatter[$name] : null);
    }

    /**
     * The magic getter returns true if the value exists in the Front Matter. This is used in conjunction with the __get
     * function
     *
     * @param  string $name The name of the Front Matter value being looked for
     *
     * @return bool
     */
    public function __isset ($name)
    {
        return array_key_exists($name, $this->frontMatter);
    }

    /**
     * @param array|null $variables An array of YAML variables to use in evaluating the `$permalink` value
     */
    public function evaluateFrontMatter ($variables = null)
    {
        if (!is_null($variables))
        {
            $this->frontMatter = array_merge($this->frontMatter, $variables);
            $this->evaluateYaml($this->frontMatter);
        }
    }

    /**
     * Return the body of the Content Item parsed as markdown
     *
     * @return string
     */
    public function getContent ()
    {
        if (!$this->bodyContentEvaluated)
        {
            switch ($this->extension)
            {
                case "md":
                case "markdown":
                    $pd = new MarkdownEngine();
                    break;

                case "rst":
                    $pd = new RstEngine();
                    $pd->registerDirective(new SyntaxBlock());
                    break;

                default:
                    return "";
            }

            $this->bodyContent = $pd->parse($this->bodyContent);
            $this->bodyContentEvaluated = true;
        }

        return $this->bodyContent;
    }

    /**
     * Handle default front matter values that need special treatment or have special meaning to a Content Item
     */
    private function handleDefaults ()
    {
        if (isset($this->frontMatter['date']))
        {
            try
            {
                // Coming from a string variable
                $itemDate = new \DateTime($this->frontMatter['date']);
            }
            catch (\Exception $e)
            {
                // YAML has parsed them to Epoch time
                $itemDate = \DateTime::createFromFormat('U', $this->frontMatter['date']);
            }

            if (!$itemDate === false)
            {
                $this->frontMatter['year']  = $itemDate->format('Y');
                $this->frontMatter['month'] = $itemDate->format('m');
                $this->frontMatter['day']   = $itemDate->format('d');
            }
        }
    }

    /**
     * Get the Front Matter of a ContentItem as an array
     *
     * @param  bool $evaluateYaml When set to true, the YAML will be evaluated for variables
     *
     * @return array
     */
    final public function getFrontMatter ($evaluateYaml = true)
    {
        if ($this->frontMatter === null)
        {
            $this->frontMatter = array();
        }
        else if (!$this->frontMatterEvaluated && $evaluateYaml && !empty($evaluateYaml))
        {
            $this->evaluateYaml($this->frontMatter);
            $this->frontMatterEvaluated = true;
        }

        return $this->frontMatter;
    }

    /**
     * Get the permalink of this Content Item
     *
     * @return string
     */
    final public function getPermalink ()
    {
        return $this->frontMatter['permalink'];
    }

    /**
     * Get the destination of where this Content Item would be written to when the website is compiled
     *
     * @return string
     */
    final public function getTargetFile ()
    {
        $extension  = $this->fs->getExtension($this->getPermalink());
        $targetFile = $this->getPermalink();

        if (empty($extension) && !is_null($targetFile))
        {
            $targetFile = rtrim($this->getPermalink(), '/') . '/index.html';
        }
        else if (is_null($targetFile))
        {
            $targetFile = $this->fs->getBaseName($this->filePath);
        }

        return ltrim($targetFile, '/');
    }

    /**
     * Get the original file path
     *
     * @return string
     */
    final public function getFilePath ()
    {
        return $this->filePath;
    }

    final protected function evaluateYaml (&$yaml)
    {
        foreach ($yaml as $key => $value)
        {
            if (is_array($yaml[$key]))
            {
                $this->evaluateYaml($yaml[$key]);
            }
            else
            {
                $yaml[$key] = $this->evaluateYamlVar($value, $this->frontMatter);
            }
        }
    }

    final protected static function evaluateYamlVar ($string, $yaml)
    {
        $variables = array();
        $varRegex  = '/(%[a-zA-Z_\-]+)/';
        $output    = $string;

        preg_match_all($varRegex, $string, $variables);

        foreach ($variables[1] as $variable)
        {
            $yamlVar = substr($variable, 1);

            if (!array_key_exists($yamlVar, $yaml))
            {
                throw new YamlVariableNotFound("Yaml variable `$variable` is not defined");
            }

            $output = str_replace($variable, $yaml[$yamlVar], $output);
        }

        return $output;
    }
}