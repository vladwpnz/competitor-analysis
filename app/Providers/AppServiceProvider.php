<?php

namespace App\Providers;

use App\Services\AnalysisDeadline;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            AnalysisDeadline::class,
            static fn (): AnalysisDeadline =>
                new AnalysisDeadline()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
