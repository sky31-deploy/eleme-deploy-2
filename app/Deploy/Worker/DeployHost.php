<?php
namespace Deploy\Worker;

use Eloquent;
use Deploy\Interfaces\OutputInterface;
use Deploy\Traits\OutputTrait;
use Deploy\Site\Deploy;

class DeployHost extends Eloquent
{
    const TYPE_APP = 'APP';
    const TYPE_STATIC = 'STATIC';

    const STATUS_WAITING = 'Waiting';
    const STATUS_DEPLOYING = 'Deploying';
    const STATUS_ERROR = 'Error';
    const STATUS_FINISH = 'Finish';
    const STATUS_KILL = 'Kill';

    public function scopeDeploying($query)
    {
        return $query->where('status', self::STATUS_DEPLOYING);
    }

    public function scopeApp($query)
    {
        return $query->where('type', self::TYPE_APP);
    }

    public function scopeStatic($query)
    {
        return $query->where('type', self::TYPE_STATIC);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAITING);
    }

    public function scopeOf($query, Deploy $deploy)
    {
        return $query->where('deploy_id', $deploy->id);
    }

    public function scopeType($query, $host_type_id)
    {
        return $query->where('host_type_id', $host_type_id);
    }

    public function scopeDeployType($query, $deploy_type)
    {
        return $query->where('type', $deploy_type);
    }

    public function scopeDeploy($query, $deploy_id)
    {
        return $query->where('deploy_id', $deploy_id);
    }

    public function setStatus($status)
    {
        $this->status = $status;
        $this->save();
    }
}
