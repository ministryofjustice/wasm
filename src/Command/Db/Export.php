<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use WpEcs\WordpressInstance;

class Export extends Command
{
    protected function configure()
    {
        $this
            ->setName('db:export')
            ->setDescription('Export the database of a running WordPress instance')
            ->addArgument('app', InputArgument::REQUIRED, 'Name of the application')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment to run in')
            ->addArgument('filename', InputArgument::OPTIONAL, 'The filename to save to');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = new WordpressInstance(
            $input->getArgument('app'),
            $input->getArgument('env')
        );

        $toFile = $this->getFilename($input);
        $fh = fopen($toFile, 'w');
        $instance->exportDatabase($fh);
        fclose($fh);

        $output->writeln("<info>Success:</info> Database exported to <comment>$toFile</comment>");
    }

    protected function getFilename(InputInterface $input)
    {
        $filename = $input->getArgument('filename');

        if (is_null($filename)) {
            $filename = sprintf(
                '%s-%s-%s.sql',
                $input->getArgument('app'),
                $input->getArgument('env'),
                date('Y-m-d-His')
            );
        }

        return $filename;
    }
}
