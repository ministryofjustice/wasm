<?php

namespace WpEcs\Aws;

use Aws\CloudFormation\CloudFormationClient;
use Exception;

class HostingStack
{
    /**
     * @var array
     */
    protected $description;

    /**
     * @var string
     */
    public $appName;

    /**
     * @var string
     */
    public $env;

    /**
     * @var string
     */
    public $family;

    /**
     * @var bool
     */
    public $isActive;

    /**
     * @var bool
     */
    public $isUpdating;

    /**
     * @var CloudFormationClient
     */
    protected $cloudformation;

    public function __construct($description, CloudFormationClient $cloudformation)
    {
        $this->description    = $description;
        $this->cloudformation = $cloudformation;

        $appAndEnv        = $this->getAppNameAndEnvironment();
        $this->appName    = $appAndEnv['app'];
        $this->env        = $appAndEnv['env'];
        $this->family     = $this->getFamily();
        $this->isActive   = $this->isActive();
        $this->isUpdating = $this->isUpdating();
    }

    protected function getAppNameAndEnvironment()
    {
        $matches = [];
        $stackName = $this->description['StackName'];
        preg_match('/^([a-z0-9-]+)\-(dev|staging|prod)$/', $stackName, $matches);

        return [
            'app' => $matches[1],
            'env' => $matches[2],
        ];
    }

    protected function getFamily()
    {
        $image = $this->param('DockerImage');

        if (strpos($image, '/wp/')) {
            return 'WordPress';
        }

        if (strpos($image, '/tp-java/')) {
            return 'Java';
        }

        return 'Unknown';
    }

    /**
     * Return the value of a parameter with the specified key
     * null is returned if a no parameter exists with that key
     *
     * @param string $key The stack ParameterKey
     *
     * @return string|null
     */
    protected function param($key)
    {
        foreach ($this->description['Parameters'] as $param) {
            if ($param['ParameterKey'] == $key) {
                return $param['ParameterValue'];
            }
        }
    }

    /**
     * @return bool
     */
    protected function isActive()
    {
        return ($this->param('Active') == 'true');
    }

    /**
     * @return bool
     */
    protected function isUpdating()
    {
        $completeStatuses = [
            'CREATE_COMPLETE',
            'UPDATE_COMPLETE',
            'ROLLBACK_COMPLETE',
        ];

        return !in_array($this->description['StackStatus'], $completeStatuses);
    }

    public function start()
    {
        if ($this->isActive) {
            throw new Exception('This stack is already running');
        }

        $this->setActiveParameterValue('true');
    }

    public function stop()
    {
        if (!$this->isActive) {
            throw new Exception('This stack is already stopped');
        }

        $this->setActiveParameterValue('false');
    }

    /**
     * Set the value for the 'Active' parameter on the stack.
     * This will perform an update of the CloudFormation stack.
     *
     * @param string $value
     */
    protected function setActiveParameterValue($value)
    {
        $params = array_map(function ($param) use ($value) {
            if ($param['ParameterKey'] == 'Active') {
                return [
                    'ParameterKey'   => $param['ParameterKey'],
                    'ParameterValue' => $value,
                ];
            }

            return [
                'ParameterKey'     => $param['ParameterKey'],
                'UsePreviousValue' => true,
            ];
        }, $this->description['Parameters']);

        $this->cloudformation->updateStack([
            'StackName'           => $this->description['StackName'],
            'UsePreviousTemplate' => true,
            'Capabilities'        => ['CAPABILITY_IAM'],
            'Parameters'          => $params,
        ]);
    }
}
