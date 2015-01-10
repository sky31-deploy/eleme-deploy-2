<?php

use Deploy\Account\User;
use Deploy\Worker\Job;

class ManagerController extends BaseController
{
    public function role()
    {
        return Response::view('manager.role');
    }

    public function hosttypecatalogs()
    {
        return Response::view('manager.hosttypecatalogs');
    }

    public function users()
    {
        return Response::view('manager.users');
    }

    public function sites()
    {
        return Response::view('manager.sites');
    }
}