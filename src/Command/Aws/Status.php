<?php

namespace WpEcs\Command\Aws;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WpEcs\Aws\HostingStack;
use WpEcs\Aws\HostingStackCollection;

class Status extends Command
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
            ->setName('aws:status')
            ->setDescription('Show the status of hosting stacks in AWS');
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter) because we don't use $input, but the parent method defines it
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $stacks = $this->collection->getStacks();
        $rows = $this->formatTableData($stacks);
        $count = count($rows);

        $output->writeln("Found <info>$count</info> apps:");

        $table = new Table($output);
        $table
            ->setHeaders(['App Name', 'Family', 'Dev', 'Staging', 'Production'])
            ->setRows($rows);
        $table->render();
    }

    /**
     * @param HostingStack[] $stacks
     *
     * @return array
     */
    public function formatTableData($stacks)
    {
        $apps = [];
        foreach ($stacks as $stack) {
            if (!isset($apps[$stack->appName])) {
                $apps[$stack->appName] = [
                    'appName' => $stack->appName,
                    'family' => $stack->family,
                    'dev' => '<fg=blue>Not Deployed</>',
                    'staging' => '<fg=blue>Not Deployed</>',
                    'prod' => '<fg=blue>Not Deployed</>',
                ];
            }

            $apps[$stack->appName][$stack->env] = $this->getStackStatus($stack);
        }

        ksort($apps);
        return $apps;
    }

    /**
     * @param HostingStack $stack
     *
     * @return string
     */
    protected function getStackStatus($stack)
    {
        if ($stack->isUpdating) {
            return '<fg=blue>Updating</>' . $this->getMSSites($stack);
        }

        if ($stack->isActive) {
            return '<fg=green>Running</>' . $this->getMSSites($stack);
        }

        return '<fg=red>Stopped</>' . $this->getMSSites($stack);
    }

    protected function getMSSites($stack)
    {
        if (!empty($stack->sites)) {
            return "\n" . $this->formatMSSiteData($stack);
        }

        return '';
    }

    /**
     * @param $stack
     * @return string
     */
    protected function formatMSSiteData($stack)
    {
        $sites = '';
        $domains = explode("\n", $stack->sites);
        foreach ($domains as $domain) {
            if ($domain !== 'Heading' && !empty($domain)) {
                $removeString = [
                    '.wp.dsd.io',
                    $stack->appName . '.',
                    $stack->env
                ];

                $domain = str_replace($removeString, '', $domain);
                $domain = (strlen($domain) > 1 ? substr($domain, 0, -1) : $domain);
                $sites .= $domain . "\n";
            }
        }

        return rtrim($sites);
    }
}
