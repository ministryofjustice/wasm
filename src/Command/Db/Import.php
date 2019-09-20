<?php

namespace WpEcs\Command\Db;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WpEcs\Wordpress\InstanceFactory;

class Import extends Command
{
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

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $stackName = $this->getStackName($input->getArgument('instance'));

        if (preg_match('/-prod$/', $stackName) && !$input->getOption('production')) {
            $output->writeln("<error>It looks like you're trying to import data to a production instance.</error>");
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Are you sure you want to do that? [y/n]\n", false);
            if (!$helper->ask($input, $output, $question)) {
                throw new RuntimeException('Aborting');
            }
        }
    }

    /**
     * @param string $instance
     *
     * @return string
     */
    protected function getStackName($instance)
    {
        return str_replace(':', '-', $instance);
    }
}
