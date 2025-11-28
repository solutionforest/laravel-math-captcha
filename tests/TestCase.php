<?php

namespace SolutionForest\MathCaptcha\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SolutionForest\MathCaptcha\MathCaptchaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MathCaptchaServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'MathCaptcha' => \SolutionForest\MathCaptcha\Facades\MathCaptcha::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Set a test encryption key
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('math-captcha', [
            'width' => 200,
            'height' => 60,
            'ttl' => 10,
            'operators' => ['+', '-', '*'],
            'ranges' => [
                '+' => ['min1' => 1, 'max1' => 50, 'min2' => 1, 'max2' => 50],
                '-' => ['min1' => 20, 'max1' => 50, 'min2' => 1, 'max2' => 20],
                '*' => ['min1' => 2, 'max1' => 12, 'min2' => 2, 'max2' => 9],
            ],
            'background_color' => [245, 245, 247],
            'text_colors' => [
                [30, 30, 50],
                [50, 30, 80],
                [30, 60, 60],
            ],
            'noise_colors' => [
                [200, 200, 210],
                [180, 190, 200],
                [210, 200, 190],
            ],
            'noise_lines' => 8,
            'noise_dots' => 100,
            'font_size' => 5,
            'cache_prefix' => 'math_captcha_test',
            'route' => [
                'enabled' => true,
                'uri' => '/captcha',
                'name' => 'captcha.generate',
                'middleware' => ['web'],
            ],
        ]);
    }
}
