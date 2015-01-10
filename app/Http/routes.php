<?php

Route::get('/', 'WelcomeController@index');


/*
|--------------------------------------------------------------------------
| Commands
|--------------------------------------------------------------------------
*/
Route::group(['namespace' => 'Command'], function () {

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'user'], function () {
        Route::get('all', [
            'as'   => 'user.all',
            'uses' => 'UserController@all'
        ]);
        Route::get('find/{name}', [
            'as'   => 'user.find',
            'uses' => 'UserController@find'
        ]);
        Route::get('count', [
            'as'   => 'user.count',
            'uses' => 'UserController@count'
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Documentation
    |--------------------------------------------------------------------------
    */
    Route::group(['prefix' => 'docs'], function () {
        Route::get('/{version?}/{main?}/{sub?}', [
            'as'   => 'docs.index',
            'uses' => 'DocController@index'
        ]);
    });
});

Route::get('home', 'HomeController@index');

Route::controllers([
                       'auth'     => 'Auth\AuthController',
                       'password' => 'Auth\PasswordController',
                   ]);
