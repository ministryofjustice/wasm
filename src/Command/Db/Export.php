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

        $command = 'wp --allow-root db export -';
        $process = new Process($instance->prepareCommand($command));

        $errout = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        $toFile = $this->getFilename($input);
        $fh = fopen($toFile, 'w');

        /**
         * Callback to process Process output
         *
         * Output is saved to the open file handle ($fh) in chunks.
         * Also notice that Process output capturing is disabled with $process->disableOutput();
         *
         * Together, this avoids the need to load the entire DB dump into an in-memory variable before writing it out to disk.
         * Instead, the dump is written to disk as it arrives, and is never stored in memory.
         *
         * @param string $type
         * @param string $buffer
         */
        $saveOutputStream = function($type, $buffer) use ($fh, $errout) {
            if ($type === Process::ERR) {
                $errout->writeln($buffer);
            } else {
                fwrite($fh, $buffer);
            }
        };
        $process->disableOutput();
        $process->mustRun($saveOutputStream);
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