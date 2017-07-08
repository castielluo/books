<?php

use Dingo\Api\Routing\Router;

/** @var Router $api */
$api = app(Router::class);

$api->version('v1', function (Router $api) {
    $api->group(['prefix' => 'auth'], function(Router $api) {
        $api->post('signup', 'App\\Api\\V1\\Controllers\\SignUpController@signUp');
        $api->post('login', 'App\\Api\\V1\\Controllers\\LoginController@login');

        $api->post('recovery', 'App\\Api\\V1\\Controllers\\ForgotPasswordController@sendResetEmail');
        $api->post('reset', 'App\\Api\\V1\\Controllers\\ResetPasswordController@resetPassword');
    });

    $api->group(['middleware' => 'jwt.auth'], function(Router $api) {
        $api->get('protected', function() {
            return response()->json([
                'message' => 'Access to this item is only for authenticated user. Provide a token in your request!'
            ]);
        });

        $api->get('refresh', [
            'middleware' => 'jwt.refresh',
            function() {
                return response()->json([
                    'message' => 'By accessing this endpoint, you can refresh your access token at each request. Check out this response headers!'
                ]);
            }
        ]);
    });

    $api->get('hello', function() {
        return response()->json([
            'message' => 'This is a simple example of item returned by your APIs. Everyone123 can see it.'
        ]);
    });

    $api->get('testuser', 'App\\Api\\V1\\Controllers\\UsersController@index');

    $api->any('checkauth', 'App\\Api\\V1\\Controllers\\WechatController@checkauth');



    $api->get('kinds', 'App\\Api\\V1\\Controllers\\BooksController@kinds');
    $api->post('kindbooks', 'App\\Api\\V1\\Controllers\\BooksController@kindbooks');
    $api->post('scanbook', 'App\\Api\\V1\\Controllers\\BooksController@scanbook');
    $api->post('login', 'App\\Api\\V1\\Controllers\\BooksController@login');
});
