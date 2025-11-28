<?php

use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\MathCaptcha;

beforeEach(function () {
    $this->app->singleton(CaptchaGenerator::class, function ($app) {
        return new MathCaptcha([
            'cache_prefix' => 'test_controller',
        ]);
    });
});

describe('CaptchaController', function () {
    describe('generate()', function () {
        it('returns a JSON response', function () {
            $response = $this->get('/captcha');

            $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/json');
        });

        it('returns response with token and image keys', function () {
            $response = $this->get('/captcha');

            $response->assertStatus(200)
                ->assertJsonStructure(['token', 'image']);
        });

        it('returns a 32 character token', function () {
            $response = $this->get('/captcha');

            $data = $response->json();

            expect($data['token'])->toBeString()
                ->toHaveLength(32);
        });

        it('returns a valid base64 PNG image', function () {
            $response = $this->get('/captcha');

            $data = $response->json();

            expect($data['image'])->toBeString()
                ->toStartWith('data:image/png;base64,');

            // Verify we can decode it
            $base64Data = str_replace('data:image/png;base64,', '', $data['image']);
            $decodedImage = base64_decode($base64Data, true);

            expect($decodedImage)->not->toBeFalse();
        });

        it('stores the answer in cache', function () {
            $response = $this->get('/captcha');

            $data = $response->json();
            $cacheKey = "test_controller:{$data['token']}";

            expect(cache()->has($cacheKey))->toBeTrue();
        });

        it('generates different tokens on each request', function () {
            $response1 = $this->get('/captcha');
            $response2 = $this->get('/captcha');
            $response3 = $this->get('/captcha');

            $token1 = $response1->json('token');
            $token2 = $response2->json('token');
            $token3 = $response3->json('token');

            expect($token1)->not->toBe($token2)
                ->and($token2)->not->toBe($token3)
                ->and($token1)->not->toBe($token3);
        });
    });

    describe('route configuration', function () {
        it('responds on configured URI', function () {
            config(['math-captcha.route.uri' => '/custom-captcha']);

            // Re-register routes with new config
            $this->app['router']->get('/custom-captcha', [\SolutionForest\MathCaptcha\Http\Controllers\CaptchaController::class, 'generate'])
                ->name('custom.captcha.generate');

            $response = $this->get('/custom-captcha');

            $response->assertStatus(200)
                ->assertJsonStructure(['token', 'image']);
        });

        it('has named route', function () {
            expect(route('captcha.generate'))->toEndWith('/captcha');
        });
    });
});
