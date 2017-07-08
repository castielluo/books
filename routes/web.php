<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('reset_password/{token}', ['as' => 'password.reset', function($token)
{
    // implement your reset password route here!
}]);

Route::get('/', function () {
    return view('welcome');
});

Route::get('welcome', function () {
    return view('welcome');
});


//wechat授权
    Route::get('oauth/{action}','WeChat\WeChatController@oauth');//授权回调页
    Route::group(['middleware' => ['web', 'wechat.oauth']], function () {
        Route::get('/user', function () {
            $user = session('wechat.oauth_user'); // 拿到授权用户资料
            dd($user);
        });

        Route::get('1/user/profile','WeChat\WeChatController@user');
        Route::get('wechat/test', 'TestController@index');
    });

    Route::any('checkauth', 'WeChat\WeChatController@checkauth');