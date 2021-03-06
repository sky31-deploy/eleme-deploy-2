<?php

/*
|--------------------------------------------------------------------------
| Application & Route Filters
|--------------------------------------------------------------------------
|
| Below you will find the "before" and "after" events for the application
| which may be used to do any work before or after a request into your
| application. Here you may also register your custom route filters.
|
*/

App::before(function($request) {
    //
});

App::after(function($request, $response) {
    //
});

/*
|--------------------------------------------------------------------------
| Authentication Filters
|--------------------------------------------------------------------------
|
| The following filters are used to verify that the user of the current
| session is logged into this application. The "basic" filter easily
| integrates HTTP Basic authentication for quick, simple checking.
|
*/

Route::filter('auth', function () {
    if (!Sentry::checkLogin()) {
        if (Request::ajax()) {
            return Response::make('Unauthorized', 401);
        }

        return Redirect::guest('login');
    }

});

Route::filter('guest', function () {
    if (Sentry::checkLogin()) {
        return Redirect::route('dashboard');
    }
});

Route::filter('waiting', function () {
    $user = Sentry::loginUser();
    if ($user->isWaiting()) {
        return Redirect::route('wait');
    } elseif ($user->isRegister()) {
        return Redirect::route('register');
    }
});

Route::filter('no.wait', function () {
    $user = Sentry::loginUser();
    if (!$user->isWaiting()) {
        return Redirect::route('dashboard');
    }
});

Route::filter('no.register', function () {
    $user = Sentry::loginUser();
    if (!$user->isRegister()) {
        return Redirect::route('dashboard');
    }
});

/*
|--------------------------------------------------------------------------
| CSRF Protection Filter
|--------------------------------------------------------------------------
|
| The CSRF filter is responsible for protecting your application against
| cross-site request forgery attacks. If this special token in a user
| session does not match the one given in this request, we'll bail.
|
*/

Route::filter('csrf', function() {
    //Log::info('csrf');
    if (Session::token() !== Input::get('_token')) {
        throw new Illuminate\Session\TokenMismatchException;
    }
});


// api
//
Route::filter('api', function () {
    //if (!Request::ajax()) {
        //return Response::json(array('res' => 0, 'info' => 'Bad Request'), 400);
    //}
});

Route::filter('admin', function () {
    $user = Sentry::loginUser();
    if (!$user->isAdmin()) {

        return Response::make('非管理员角色无法访问此页面<a href="/">dashboard</a>', 403);;
    }
});


Route::filter('site.control', function ($route) {
    $site = $route->getParameter('site');
    $user = Sentry::loginUser();

    if (!$user->control($site->accessAction()) && !$user->isAdmin()) {
        return Response::make('你没有发布该项目的权限', 403);
    }
});
