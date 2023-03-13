<?php

namespace RoshaniSTPL\utility\Providers;

use Illuminate\Support\ServiceProvider;

class UtilityProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../views', 'utility');
    }

    public function register()
    {
        //Register Our Package routes
        include __DIR__.'/../routes/web.php';

        // Let Laravel Ioc Container know about our Controller
        $this->app->make('RoshaniSTPL\utility\Controllers\UtilityController');
        $this->app->make('RoshaniSTPL\utility\Controllers\FileHandleHelperController');
        $this->app->make('RoshaniSTPL\utility\Controllers\S3WrapperController');
    }
}