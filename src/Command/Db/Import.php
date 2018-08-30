<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Wordpress\InstanceFactory;

class Import extends Command
{
    protected function configure()
    {
        $this
            ->setName('db:import')
            ->setDescription('Import a SQL file into a running WordPress instance')
            ->addArgument('instance', InputArgument::REQUIRED,
                'Instance identifier. Valid format: "<appname>:<env>" or path to a local directory')
            ->addArgument('filename', InputArgument::REQUIRED, 'The SQL file to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = InstanceFactory::createFromInput(
            $input->getArgument('instance')
        );

        $fromFile = $input->getArgument('filename');
        $fh       = fopen($fromFile, 'r');
        $instance->importDatabase($fh);
        fclose($fh);

        $output->writeln("<info>Success:</info> Database imported from <comment>$fromFile</comment>");
    }
}
