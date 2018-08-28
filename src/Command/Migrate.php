<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Wordpress\AwsInstance;
use WpEcs\Service\Migration;

class Migrate extends Command
{
    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrate a WordPress instance between environments')
            ->addArgument('app', InputArgument::REQUIRED, 'Name of the application')
            ->addArgument('from', InputArgument::REQUIRED, 'Source environment')
            ->addArgument('to', InputArgument::REQUIRED, 'Destination environment');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = $input->getArgument('app');
        $fromEnv = $input->getArgument('from');
        $toEnv = $input->getArgument('to');

        $from = new AwsInstance($app, $fromEnv);
        $to = new AwsInstance($app, $toEnv);

        $migration = new Migration($from, $to, $output);
        $migration->migrate();

        $output->writeln("<info>Success:</info> Migrated <comment>$app</comment> from <comment>$fromEnv</comment> to <comment>$toEnv</comment>");
    }
}
