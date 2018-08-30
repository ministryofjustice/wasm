<?php

namespace WpEcs\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Service\Migration;
use WpEcs\Wordpress\InstanceFactory;

class Migrate extends Command
{
    protected function configure()
    {
        $this
            ->setName('migrate')
            ->setDescription('Migrate a WordPress instance between environments')
            ->addArgument('from', InputArgument::REQUIRED, 'Source instance identifier')
            ->addArgument('to', InputArgument::REQUIRED, 'Destination instance identifier');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        if ($from == $to) {
            throw new InvalidArgumentException('"from" and "to" arguments cannot be the same');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $from = $input->getArgument('from');
        $to = $input->getArgument('to');

        $migration = new Migration(
            InstanceFactory::create($from),
            InstanceFactory::create($to),
            $output
        );
        $migration->migrate();

        $output->writeln("<info>Success:</info> Migrated <comment>$from</comment> to <comment>$to</comment>");
    }
}
