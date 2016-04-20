<?php

namespace allejo\stakx\Command;

use allejo\stakx\Object\Configuration;
use allejo\stakx\System\Filesystem;
use allejo\stakx\Object\Website;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var Website
     */
    protected $website;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * {@inheritdoc}
     */
    protected function configure ()
    {
        $this->fs = new Filesystem();

        $this->setName('build');
        $this->setDescription('Builds the stakx website');
        $this->addOption('conf', 'c', InputOption::VALUE_REQUIRED, 'The configuration file to be used', $this->fs->absolutePath(Configuration::DEFAULT_NAME));
        $this->addOption('safe', 's', InputOption::VALUE_NONE, 'Disable file system access from Twig');
        $this->addOption('no-conf', 'l', InputOption::VALUE_NONE, 'Build a Stakx website without a configuration file');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute (InputInterface $input, OutputInterface $output)
    {
        $this->website = new Website($output);
        $this->website->setConfLess($input->getOption('no-conf'));

        try
        {
            $this->website->setConfiguration($input->getOption('conf'));
            $this->website->setSafeMode($input->getOption('safe'));
            $this->website->build();
        }
        catch (\Exception $e)
        {
            $output->writeln("An error has occurred.");
            $output->writeln($e->getMessage());
        }
    }

    /**
     * @internal
     *
     * @return \allejo\stakx\Object\Website
     */
    public function _getWebsite()
    {
        return $this->website;
    }
}