<?php

namespace SolutionForest\MathCaptcha;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\Http\Controllers\CaptchaController;

class MathCaptchaServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/math-captcha.php',
            'math-captcha'
        );

        $this->app->singleton(CaptchaGenerator::class, function ($app) {
            return new MathCaptcha($app['config']->get('math-captcha', []));
        });

        $this->app->alias(CaptchaGenerator::class, 'math-captcha');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/math-captcha.php' => config_path('math-captcha.php'),
            ], 'math-captcha-config');
        }

        $this->registerRoutes();
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $config = $this->app['config']->get('math-captcha.route', []);

        if (! ($config['enabled'] ?? true)) {
            return;
        }

        Route::middleware($config['middleware'] ?? ['web'])
            ->get($config['uri'] ?? '/captcha', [CaptchaController::class, 'generate'])
            ->name($config['name'] ?? 'captcha.generate');
    }
}
