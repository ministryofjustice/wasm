<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Wordpress\AwsInstance;

class Exec extends Command
{
    protected function configure()
    {
        $this
            ->setName('exec')
            ->setDescription('Executes a command on a running WordPress instance')
            ->addArgument('app', InputArgument::REQUIRED, 'Name of the application')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment to run in')
            ->addArgument('cmd', InputArgument::REQUIRED, 'The command to execute');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $instance = new AwsInstance(
            $input->getArgument('app'),
            $input->getArgument('env')
        );

        $output->write(
            $instance->execute(
                $input->getArgument('cmd')
            )
        );
    }
}
