<?php

namespace WpEcs\Aws;

use Aws\CloudFormation\CloudFormationClient;
use Exception;
use WpEcs\Traits\Debug;
use WpEcs\Wordpress\InstanceFactory;

class HostingStack
{
    use Debug;

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
     * @var string
     */
    public $sites;

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
    private $instanceFactory;

    public function __construct($description, CloudFormationClient $cloudformation)
    {
        $this->description = $description;
        $this->cloudformation = $cloudformation;
        $this->instanceFactory = new InstanceFactory();

        $appAndEnv = $this->getAppNameAndEnvironment();
        $this->appName = $appAndEnv['app'];
        $this->env = $appAndEnv['env'];
        $this->family = $this->getFamily();
        $this->sites = $this->getMSSites();
        $this->isActive = $this->isActive();
        $this->isUpdating = $this->isUpdating();
    }

    protected function getAppNameAndEnvironment()
    {
        preg_match('/^([a-z0-9-]+)-(dev|staging|prod)$/', $this->description['StackName'], $matches);
        return [
            'app' => $matches[1],
            'env' => $matches[2],
        ];
    }

    protected function getFamily()
    {
        $image = $this->param('DockerImage');

        if (strpos($image, '/wp/')) {
            return 'WordPress' . ($this->isMultisite() ? " Multisite" : '');
        }

        if (strpos($image, '/tp-java/')) {
            return 'Java';
        }

        return 'Unknown';
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function getMSSites(): string
    {
        if ($this->isMultisite()) {
            $source = $this->instanceFactory->create($this->appName . ':' . $this->env);

            $command = [
                'wp',
                '--allow-root',
                'db',
                'query',
                'SELECT CONCAT(domain, path) as Heading from wp_blogs'
            ];

            return $source->execute($command);
        }

        return '';
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

        return null;
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

    /**
     * @return bool
     */
    protected function isMultisite()
    {
        return (!empty($this->param('WpmsSaUsername')));
    }

    /**
     * @throws Exception
     */
    public function start()
    {
        if ($this->isActive) {
            throw new Exception('This stack is already running');
        }

        $this->setActiveParameterValue('true');
    }

    /**
     * @throws Exception
     */
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
                    'ParameterKey' => $param['ParameterKey'],
                    'ParameterValue' => $value,
                ];
            }

            return [
                'ParameterKey' => $param['ParameterKey'],
                'UsePreviousValue' => true,
            ];
        }, $this->description['Parameters']);

        $this->cloudformation->updateStack([
            'StackName' => $this->description['StackName'],
            'UsePreviousTemplate' => true,
            'Capabilities' => ['CAPABILITY_IAM'],
            'Parameters' => $params,
        ]);
    }
}
