<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Traits\ProductionInteractionTrait;
use WpEcs\Wordpress\InstanceFactory;

class Import extends Command
{
    use ProductionInteractionTrait;

    protected $instanceFactory;

    public function __construct(InstanceFactory $instanceFactory)
    {
        $this->instanceFactory = $instanceFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('db:import')
            ->setDescription('Import a SQL file into a running WordPress instance')
            ->addArgument(
                'instance',
                InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>" or path to a local directory'
            )
            ->addArgument('filename', InputArgument::REQUIRED, 'The SQL file to import')
            ->addOption(
                'production',
                'p',
                InputOption::VALUE_NONE,
                "Ask for confirmation before importing data to a production instance"
            );

        $this->prodInteractMessage = "It looks like you're trying to import data to a production instance.";
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = $this->instanceFactory->create(
            $input->getArgument('instance')
        );

        $filename = $input->getArgument('filename');
        $file     = fopen($filename, 'r');
        $instance->importDatabase($file);
        fclose($file);

        $output->writeln("<info>Success:</info> Database imported from <comment>$filename</comment>");
    }
}
