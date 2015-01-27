<?php

use Deploy\Account\Role;
use Deploy\Sentry\Permission;
use Deploy\Site\Site;
use Deploy\Site\Deploy;
use Deploy\Site\DeployConfig;
use Deploy\Hosts\HostTypeCatalog;
use Deploy\Account\User;
use Deploy\Facade\Worker;
use Deploy\Worker\Job;
use Deploy\Worker\DeployScript;
use Deploy\Site\PullRequestBuild;
use Deploy\Hosts\HostType;
use Deploy\Hosts\Host;
use Deploy\Worker\DeployHost;


class ApiController extends Controller
{
    public function indexRolePermission(Role $role)
    {
        $permissions = $role->permissions()->lists('name');
        $addIsControlled = function (&$list) use ($permissions) {
            foreach ($list as $key => $value) {
                if (in_array($list[$key]['action'], $permissions)) {
                    $list[$key]['is_controlled'] = 1;
                } else {
                    $list[$key]['is_controlled'] = 0;
                }
            }
        };

        $siteAccess = Site::accessActionList();
        $siteManage = Site::manageActionList();
        $hostTypeCatalogAccess =  HostTypeCatalog::accessActionList();

        $addIsControlled($siteAccess);
        $addIsControlled($siteManage);
        $addIsControlled($hostTypeCatalogAccess);

        return Response::json(array(
            'code' => 0,
            'data' => array(
                'name' => $role->name,
                'id' => $role->id,
                'permissions' => array(
                    array(
                        'description' => '站点管理权限',
                        'list' => $siteManage,
                    ),
                    array(
                        'description' => '站点发布权限',
                        'list' => $siteAccess,
                    ),
                    array(
                        'description' => '环境发布权限',
                        'list' => $hostTypeCatalogAccess
                    )
                )
            )
        ));
    }

    public function storeRolePermission(Role $role)
    {
        $list = Input::get('permissions');
        if (empty($list)) {
            $list = array();
        }
        DB::transaction(function () use($list, $role) {
            $role->permissions()->delete();
            $permissions = [];
            foreach ($list as $value) {
                $permissions[] = $role->permissions()->create(array('name' => $value));
            }
            if (count($permissions) > 0) {
                $role->permissions()->saveMany($permissions);
            }
        });

        return Response::json(array('code' => 0, 'msg' => '权限修改成功'));
    }

    public function storeUserRole(User $user)
    {
        $validator = Validator::make(
            Input::only('role_id'),
            array('role_id' => 'required|numeric|exists:roles,id|unique:role_user,role_id,null,id,user_id,' . $user->id),
            array(
                'required' => '角色 id 不能为空',
                'numeric' => '角色 id 必须为数字',
                'exists' => '角色不存在',
                'unique' => '用户已经拥有该角色',
            )
        );

        if ($validator->fails()) {
            return Response::json(array(
                'code' => 1,
                'msg' => $validator->messages()->first(),
            ));
        }

        $user->roles()->attach(Input::only('role_id'));

        return Response::json(array('code' => 0, 'msg' => '添加成功'));
    }

    public function destroyUserRole(User $user, Role $role)
    {
        $user->roles()->detach($role->id);

        return Response::json(array('code' => 0, 'msg' => '删除成功'));
    }

    public function showSiteConfig(Site $site)
    {
        return Response::json(array(
            'code' => 0,
            'data' => $site
        ));
    }

    public function updateSiteConfig(Site $site)
    {
        $site->fill(Input::only('static_dir', 'rsync_exclude_file', 'default_branch', 'build_command', 'test_command',
                                'hipchat_room', 'hipchat_token', 'pull_key', 'pull_key_passphrase', 'github_token'));
        $site->save();

        $pull_key = Input::get('pull_key');
        if ($pull_key != '******') {
            $user = Sentry::loginUser();
            $job = Worker::createJob(
                'Deploy\Worker\Jobs\StoreKey',
                "操作：Store Keys &nbsp; " . "项目：{$site->name} &nbsp;" . "操作者：{$user->name}({$user->login}) &nbsp;",
                array('site_id' => $site->id)
            );
            Worker::push($job);
        }


        return Response::json(array(
            'code' => 0,
            'msg' => '保存成功',
        ));
    }

    public function showDeployConfig(Site $site)
    {
        $deploy_config = $site->deploy_config()->first();
        if ($deploy_config == null) {
            $deploy_config = new DeployConfig;
            $deploy_config->site()->associate($site);
            $deploy_config->save();
        }

        return Response::json(array(
            'code' => 0,
            'data' => $deploy_config,
        ));
    }

    public function updateDeployConfig(Site $site)
    {
        $deploy_config = $site->deploy_config()->first();
        try {
            $APP_SCRIPT = DeployScript::complie(Input::get('app_script'), DeployScript::varList($site, $deploy_config));
            $STATIC_SCRIPT = DeployScript::complie(Input::get('static_script'), DeployScript::varList($site, $deploy_config));
        } catch (Exception $e) {
            Log::info($e);
            return array('code' => 1, 'msg' => '脚本解析出错, ' . $e->getMessage());
        }

        $deploy_config->fill(Input::only('remote_user', 'remote_owner', 'remote_app_dir', 'remote_static_dir', 
            'app_script', 'static_script', 'deploy_key', 'deploy_key_passphrase'));

        $deploy_config->save();

        $user = Sentry::loginUser();
        $deploy_key = Input::get('deploy_key');
        if ($deploy_key != '******') {
            $user = Sentry::loginUser();
            $job = Worker::createJob(
                'Deploy\Worker\Jobs\StoreKey',
                "操作：Store Keys &nbsp; " . "项目：{$site->name} &nbsp;" . "操作者：{$user->name}({$user->login}) &nbsp;",
                array('site_id' => $site->id)
            );
            Worker::push($job);
        }

        return Response::json(array(
            'code' => 0,
            'msg' => '保存成功'
        ));
    }

    public function showSystemConfig()
    {
        $config = SystemConfig::firstOrNew(array('name' => 'system'));
        return Response::json(array(
            'code' => 0,
            'data' => $config
        ));
    }

    public function prRebuild(Site $site)
    {
        $pr = PullRequestBuild::findOrFail(Input::get('pr_id'));
        $pr->setCommandStatus(PullRequestBuild::STATUS_WAITING, PullRequestBuild::STATUS_WAITING);
        $job = Job::findOrFail($pr->job_id);
        $job->status = Job::STATUS_WAITING;
        $job->clear();
        $job->save();
        Worker::push($job);

        return Response::json(array(
            'code' => 0,
            'data' => array(
                'jobId' => $job->id
            )
        ));
    }

    public function siteTypeAndEnv(Site $site)
    {
        $type = Input::get('type');

        $catalogs = HostTypeCatalog::all();
        $hostTypes = HostType::where('site_id', $site->id)->with('catalog')->orderBy('catalog_id')->get();
        $commits = array();
        if ($type == 'deploy') {
            $commits = $site->commits()->orderBy('id', 'desc')->limit(30)->get();
        } else {
            $commits = PullRequestBuild::of($site)->open()->success()->orderBy('id', 'desc')->limit(30)->get();
        }

        return Response::json(array(
            'code' => 0,
            'data' => array(
                'envs' => $catalogs,
                'types' => $hostTypes,
                'commits' => $commits
            )
        ));
    }

    public function siteDeploy(Site $site)
    {
        $user = Sentry::loginUser();
        $deploy_kind = Input::get('deploy_kind');
        $deploy_to = Input::get('deploy_to');
        $hosts = array();

        if ($deploy_kind == 'host') {
            $host = Host::where(array('site_id' => $site->id, 'ip' => $deploy_to))->first();
            $hostType = $host->host_types()->first();
            $catalog = $hostType->catalog()->first();

            if ($host == null) {
                return Response::json(array('code' => 1, 'msg' => 'IP错误或不存在'));
            }

            if (!$user->control($catalog->accessAction())) {
                return Response::json(array('code' => 1, 'msg' => '你没有发布到这台主机的权限'));
            }
            $hosts = array($host);
            $toName = "$host->name($host->ip)";

        } elseif ($deploy_kind == 'type') {
            $hostType = HostType::findorFail($deploy_to);
            if ($hostType == null) {
                return Response::json(array('code' => 1, 'msg' => '分组不存在'));
            }
            $catalog = $hostType->catalog()->first();
            $hosts = $hostType->hosts()->get();

            $toName = $hostType->name;
        } else {
            $catalog = HostTypeCatalog::find($deploy_to);
            if ($catalog == null) {
                return Response::json(array('code' => 1, 'msg' => '环境不存在'));
            }
            $types = HostType::where('catalog_id', $deploy_to)->with('hosts')->get();

            foreach ($types as $hostType) {
                $hosts = array_merge($hosts, $hostType->hosts()->get());
            }
            $toName = $catalog->name;
        }
        if (count($hosts) == 0) {
            return Response::json(array(
                'code' => 1,
                'msg' => '所选的发布环境没有配置主机'
            ));
        }

        $deployType = Input::get('type');
        $commit = substr(Input::get('commit'), 0, 7);

        $job = Worker::createJob(
            'Deploy\Worker\Jobs\DeployCommit',
            '操作：' . ($deployType == 'prdeploy' ? 'PR ' : '') .  "Deploy {$commit} To {$toName}; " . "项目：{$site->name} &nbsp;" . "操作者：{$user->name}({$user->login}) &nbsp;"
        );

        $deploy = new Deploy;
        $deploy->fill(Input::only('deploy_kind', 'deploy_to', 'commit'));
        $deploy->user_id = $user->id;
        $deploy->job_id = $job->id;
        $deploy->site_id = $site->id;
        $deploy->total_hosts = count($hosts);
        $deploy->description = $toName;
        $deploy->type = Input::get('type');
        $deploy->status = Deploy::STATUS_WAITING;
        $deploy->save();

        $deployHosts = array();
        $datetime = date('Y:m:d H:i:s');
        foreach ($hosts as $host) {
            $deployHosts[] = array(
                'job_id' => $job->id,
                'site_id' => $site->id,
                'host_type_id' => $host->host_type_id,
                'deploy_id' => $deploy->id,
                'type' => $host->type,
                'host_ip' => $host->ip,
                'host_name' => $host->name,
                'host_port' => $host->port,
                'created_at' => $datetime,
                'updated_at' => $datetime,
                'status' => DeployHost::STATUS_WAITING
            );
        }
        DeployHost::insert($deployHosts);

        $job->message = array(
            'site_id' => $site->id,
            'deploy_id' => $deploy->id
        );
        Worker::push($job);

        return Response::json(array(
            'code' => 0,
            'data' => array(
                'jobId' => $job->id
            ),
            'msg' => '发布任务创建成功'
        ));
    }

    public function indexDeploy(Site $site)
    {
        return Response::json(array(
            'code' => 0,
            'data' => Deploy::where(
                array(
                    'site_id' => $site->id,
                    'type' => Input::get('type'),
                )
            )->orderBy('id', 'desc')->limit(30)->get(),
        ));
    }
}

