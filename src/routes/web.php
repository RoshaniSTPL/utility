<?php
/** @var \Laravel\Lumen\Routing\Router $router */
$this->app->router->get('test', \RoshaniSTPL\utility\Controllers\UtilityController::class);
$this->app->router->get('/s3/{params:.*}', [\RoshaniSTPL\utility\Controllers\S3WrapperController::class, 'getActualFile']);