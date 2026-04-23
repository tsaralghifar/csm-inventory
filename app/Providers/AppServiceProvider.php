<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Super User bypass semua permission
        Gate::before(function ($user, $ability) {
            if ($user->isSuperuser()) {
                return true;
            }
        });
    }
}