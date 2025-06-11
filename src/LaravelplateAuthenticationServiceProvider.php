<?php

namespace Laravelplate\Authentication;

use Illuminate\Support\ServiceProvider;
use Laravelplate\Authentication\Commands\InstallCommand;

class LaravelplateAuthenticationServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }

        // Publish migrations (excluding users table - handled by install command)
        $this->publishes([
            __DIR__.'/Migrations/2014_09_25_055221_create_roles_table.php' => database_path('migrations/2014_09_25_055221_create_roles_table.php'),
            __DIR__.'/Migrations/2014_09_26_034833_create_user_statuses_table.php' => database_path('migrations/2014_09_26_034833_create_user_statuses_table.php'),
        ], 'laravelplate-auth-migrations');

        // Publish models (excluding User - handled by install command)
        $this->publishes([
            __DIR__.'/Models/Role.php' => app_path('Models/Role.php'),
            __DIR__.'/Models/UserStatus.php' => app_path('Models/UserStatus.php'),
        ], 'laravelplate-auth-models');

        // Publish controllers
        $this->publishes([
            __DIR__.'/Controllers' => app_path('Http/Controllers'),
        ], 'laravelplate-auth-controllers');
    }
}