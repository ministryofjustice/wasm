<?php

namespace WpEcs\Aws;

use Aws\CloudFormation\CloudFormationClient;

class HostingStackCollection
{
    /**
     * @var CloudFormationClient
     */
    protected $cloudformation;

    public function __construct(CloudFormationClient $cloudformation)
    {
        $this->cloudformation = $cloudformation;
    }

    public function getStacks()
    {
        $stacks = [];
        $results = $this->cloudformation->getPaginator('DescribeStacks');
        foreach ($results as $result) {
            $stacks = array_merge(
                $stacks,
                array_filter($result['Stacks'], [$this, 'isHostingStack'])
            );
        }

        return array_map(function ($stack) {
            return new HostingStack($stack);
        }, $stacks);
    }

    protected function isHostingStack($description)
    {
        // If the stack name doesn't end with "dev", "staging" or "prod", it's not a hosting stack
        if (!preg_match('/^([a-z0-9-]+)\-(dev|staging|prod)$/', $description['StackName'])) {
            return false;
        }

        // The stack must have these parameters
        $expectParams = [
            'Active',
            'AppName',
            'Environment',
        ];

        foreach ($expectParams as $param) {
            if (!$this->stackHasParam($param, $description)) {
                return false;
            }
        }

        return true;
    }

    protected function stackHasParam($paramKey, $stackDescription)
    {
        foreach ($stackDescription['Parameters'] as $param) {
            if ($param['ParameterKey'] == $paramKey) {
                return true;
            }
        }
        return false;
    }
}
