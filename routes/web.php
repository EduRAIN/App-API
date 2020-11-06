<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $_ENV['APP_ENV'] == 'prod' ? null : $router->app->version();
});

$router->group(['prefix' => $_ENV['APP_PREFIX']], function () use ($router) {
    // Scholarship
    $router->post('scholarships', 'ScholarshipController@index');
    $router->post('scholarships/search', 'ScholarshipController@search');
    $router->get('scholarship/{id}', 'ScholarshipController@fetch');
    $router->post('scholarship/new', 'ScholarshipController@update');
    $router->post('scholarship/{id}', 'ScholarshipController@update');
    $router->delete('scholarship/{id}', 'ScholarshipController@remove');
    $router->get('scholarship/{id}/reported', 'ScholarshipController@scholarshipReported');

    // FAFSA Questionnaire
    $router->get('fafsa/{id}', 'QuestionController@fetch');
    $router->post('fafsa/{id}', 'ResponseController@update');

    // Demographics Profile
    $router->get('demographics[/{id}]', 'QuestionController@fetch');
    $router->post('demographics[/{id}]', 'ResponseController@update');
});