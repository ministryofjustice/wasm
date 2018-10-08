<?php

namespace WpEcs\Aws;

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
     * @var bool
     */
    public $isActive;

    /**
     * @var bool
     */
    public $isUpdating;

    public function __construct($description)
    {
        $this->description = $description;

        $appAndEnv = $this->getAppNameAndEnvironment();
        $this->appName = $appAndEnv['app'];
        $this->env = $appAndEnv['env'];
        $this->isActive = $this->isActive();
        $this->isUpdating = $this->isUpdating();
    }

    protected function getAppNameAndEnvironment()
    {
        $stackName = $this->description['StackName'];
        preg_match('/^([a-z0-9-]+)\-(dev|staging|prod)$/', $stackName, $matches);

        return [
            'app' => $matches[1],
            'env' => $matches[2],
        ];
    }

    /**
     * @return bool
     */
    protected function isActive()
    {
        foreach ($this->description['Parameters'] as $param) {
            if ($param['ParameterKey']   == 'Active' &&
                $param['ParameterValue'] == 'true') {
                return true;
            }
        }
        return false;
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
}
