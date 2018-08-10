<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use WpEcs\WordpressInstance;

class Import extends Command
{
    protected function configure()
    {
        $this
            ->setName('db:import')
            ->setDescription('Import a SQL file into a running WordPress instance')
            ->addArgument('app', InputArgument::REQUIRED, 'Name of the application')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment to run in')
            ->addArgument('filename', InputArgument::REQUIRED, 'The filename to import');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = new WordpressInstance(
            $input->getArgument('app'),
            $input->getArgument('env')
        );

        $fromFile = $input->getArgument('filename');
        $fh = fopen($fromFile, 'r');
        $instance->importDatabase($fh);
        fclose($fh);

        $output->writeln("<info>Success:</info> Database imported from <comment>$fromFile</comment>");
    }
}
