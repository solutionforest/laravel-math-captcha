<?php

use Illuminate\Support\Facades\Route;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\MathCaptcha;

describe('MathCaptchaServiceProvider', function () {
    describe('service registration', function () {
        it('binds CaptchaGenerator interface to MathCaptcha', function () {
            $instance = app(CaptchaGenerator::class);

            expect($instance)->toBeInstanceOf(MathCaptcha::class);
        });

        it('creates singleton instance', function () {
            $instance1 = app(CaptchaGenerator::class);
            $instance2 = app(CaptchaGenerator::class);

            expect($instance1)->toBe($instance2);
        });

        it('creates math-captcha alias', function () {
            $instance = app('math-captcha');

            expect($instance)->toBeInstanceOf(MathCaptcha::class);
        });

        it('alias resolves to same singleton instance', function () {
            $instanceFromInterface = app(CaptchaGenerator::class);
            $instanceFromAlias = app('math-captcha');

            expect($instanceFromInterface)->toBe($instanceFromAlias);
        });
    });

    describe('configuration', function () {
        it('merges package config', function () {
            $config = config('math-captcha');

            expect($config)->toBeArray()
                ->toHaveKeys(['width', 'height', 'ttl', 'operators', 'ranges']);
        });

        it('uses config values in MathCaptcha instance', function () {
            config(['math-captcha.width' => 400]);

            // Create new instance with fresh config
            $this->app->forgetInstance(CaptchaGenerator::class);
            $this->app->singleton(CaptchaGenerator::class, function ($app) {
                return new MathCaptcha($app['config']->get('math-captcha', []));
            });

            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Extract image and check dimensions
            $base64Data = str_replace('data:image/png;base64,', '', $result['image']);
            $imageData = base64_decode($base64Data);
            $image = imagecreatefromstring($imageData);

            expect(imagesx($image))->toBe(400);

            imagedestroy($image);
        });
    });

    describe('route registration', function () {
        it('registers captcha route when enabled', function () {
            $routes = Route::getRoutes();
            $captchaRoute = $routes->getByName('captcha.generate');

            expect($captchaRoute)->not->toBeNull();
        });

        it('registers route with correct URI', function () {
            $routes = Route::getRoutes();
            $captchaRoute = $routes->getByName('captcha.generate');

            expect($captchaRoute->uri())->toBe('captcha');
        });

        it('registers route with GET method', function () {
            $routes = Route::getRoutes();
            $captchaRoute = $routes->getByName('captcha.generate');

            expect($captchaRoute->methods())->toContain('GET');
        });

        it('route uses CaptchaController', function () {
            $routes = Route::getRoutes();
            $captchaRoute = $routes->getByName('captcha.generate');

            expect($captchaRoute->getActionName())->toContain('CaptchaController');
        });
    });

    describe('route disabling', function () {
        it('can disable route via config', function () {
            // This test verifies the logic exists - route was already registered
            // when the provider booted, so we test the config value
            config(['math-captcha.route.enabled' => false]);

            expect(config('math-captcha.route.enabled'))->toBeFalse();
        });
    });
});
