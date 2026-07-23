<?php

namespace App\Providers;

use App\Models\LearningDocument;
use App\Policies\LearningDocumentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{



    public function register(): void
    {

    }




    public function boot(): void
    {
        Gate::policy(LearningDocument::class, LearningDocumentPolicy::class);

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
