<?php

namespace WpEcs\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\WordpressInstance;
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
        $from = new WordpressInstance(
            $input->getArgument('app'),
            $input->getArgument('from')
        );

        $to = new WordpressInstance(
            $input->getArgument('app'),
            $input->getArgument('to')
        );

        $migration = new Migration($from, $to);
        $migration->migrate();
    }
}
