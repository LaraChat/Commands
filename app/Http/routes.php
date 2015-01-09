<?php

Route::get('/', 'WelcomeController@index');

Route::get('/user/count', [
	'as' => 'user.count',
	'uses' => 'CommandController@userCount'
]);

Route::get('/docs/{version?}/{main?}/{sub?}', [
	'as' => 'docs',
	'uses' => 'CommandController@getDocs'
]);

Route::get('home', 'HomeController@index');

Route::controllers([
	'auth' => 'Auth\AuthController',
	'password' => 'Auth\PasswordController',
]);
