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
    protected $hostingStackCollection;

    public function __construct(HostingStackCollection $hostingStackCollection)
    {
        $this->hostingStackCollection = $hostingStackCollection;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('aws:stacks')
            ->setDescription('Show the status of hosting stacks in AWS');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stacks = $this->hostingStackCollection->getStacks();
        $rows = $this->formatTableData($stacks);
        $count = count($rows);

        $output->writeln("Found <info>$count</info> apps:");

        $table = new Table($output);
        $table
            ->setHeaders(['App Name', 'Dev', 'Staging', 'Production'])
            ->setRows($rows);
        $table->render();
    }

    public function formatTableData($stacks) {
        foreach ($stacks as $stack) {
            if (!isset($apps[$stack->appName])) {
                $apps[$stack->appName] = [
                    'appName' => $stack->appName,
                    'dev'     => '<fg=blue>Not Deployed</>',
                    'staging' => '<fg=blue>Not Deployed</>',
                    'prod'    => '<fg=blue>Not Deployed</>',
                ];
            }

            $status = '<fg=red>Stopped</>';
            if ($stack->isUpdating) {
                $status = '<fg=blue>Updating</>';
            } else if ($stack->isActive) {
                $status = '<fg=green>Running</>';
            }

            $apps[$stack->appName][$stack->env] = $status;
        }

        ksort($apps);
        return $apps;
    }
}
