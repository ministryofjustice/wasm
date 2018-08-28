<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use WpEcs\Wordpress\AwsInstance;

class Shell extends Command
{
    protected function configure()
    {
        $this
            ->setName('shell')
            ->setDescription('Opens an interactive shell on a running WordPress instance')
            ->addArgument('app', InputArgument::REQUIRED, 'Name of the application')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment to run in')
            ->addArgument('shell', InputArgument::OPTIONAL, 'The shell to execute', 'bash');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = new AwsInstance(
            $input->getArgument('app'),
            $input->getArgument('env')
        );

        $terminal = new Terminal();

        $process = $instance->newCommand(
            $input->getArgument('shell'),
            [
                '-ti',
                "-e COLUMNS={$terminal->getWidth()}",
                "-e LINES={$terminal->getHeight()}",
            ],
            ['-t']
        );
        $process->setTty(true);
        $process->mustRun();
    }
}
