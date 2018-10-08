<?php

namespace WpEcs\Command\Aws;

use Aws\CloudFormation\CloudFormationClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Aws\AppStatusMatrix;
use WpEcs\Aws\HostingStackCollection;

class Stacks extends Command
{
    /**
     * @var HostingStackCollection
     */
    protected $collection;

    public function __construct(HostingStackCollection $collection)
    {
        $this->collection = $collection;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('aws:stacks')
            ->setDescription('Show the status of hosting stacks in AWS');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) because we don't use $input, but the parent method defines it
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stacks = $this->collection->getStacks();
        $rows = $this->formatTableData($stacks);
        $count = count($rows);

        $output->writeln("Found <info>$count</info> apps:");

        $table = new Table($output);
        $table
            ->setHeaders(['App Name', 'Dev', 'Staging', 'Production'])
            ->setRows($rows);
        $table->render();
    }

    public function formatTableData($stacks)
    {
        foreach ($stacks as $stack) {
            if (!isset($apps[$stack->appName])) {
                $apps[$stack->appName] = [
                    'appName' => $stack->appName,
                    'dev'     => '<fg=blue>Not Deployed</>',
                    'staging' => '<fg=blue>Not Deployed</>',
                    'prod'    => '<fg=blue>Not Deployed</>',
                ];
            }

            $apps[$stack->appName][$stack->env] = $this->getStackStatus($stack);
        }

        ksort($apps);
        return $apps;
    }

    protected function getStackStatus($stack)
    {
        if ($stack->isUpdating) {
            return '<fg=blue>Updating</>';
        }

        if ($stack->isActive) {
            return '<fg=green>Running</>';
        }

        return '<fg=red>Stopped</>';
    }
}
