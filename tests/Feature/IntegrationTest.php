<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\Facades\MathCaptcha;
use SolutionForest\MathCaptcha\Rules\ValidCaptcha;

describe('Integration Tests', function () {
    describe('end-to-end CAPTCHA flow', function () {
        it('complete flow: generate via HTTP -> verify with correct answer', function () {
            // Step 1: Generate CAPTCHA via HTTP endpoint
            $response = $this->get('/captcha');
            $response->assertStatus(200);

            $data = $response->json();
            $token = $data['token'];

            // Step 2: Get the cached answer (simulating we know the correct answer)
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$token;
            $correctAnswer = Cache::get($cacheKey);

            expect($correctAnswer)->not->toBeNull();

            // Step 3: Verify with correct answer
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->verify($token, $correctAnswer);

            expect($result)->toBeTrue();
        });

        it('complete flow: generate via HTTP -> verify with wrong answer', function () {
            // Step 1: Generate CAPTCHA via HTTP endpoint
            $response = $this->get('/captcha');
            $data = $response->json();
            $token = $data['token'];

            // Step 2: Verify with wrong answer
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->verify($token, 999999);

            expect($result)->toBeFalse();
        });

        it('complete flow: generate via facade -> verify via facade', function () {
            // Step 1: Generate using facade
            $result = MathCaptcha::generate();

            expect($result)->toHaveKeys(['token', 'image']);

            // Step 2: Get the correct answer from cache
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $correctAnswer = Cache::get($cacheKey);

            // Step 3: Verify using facade
            $verified = MathCaptcha::verify($result['token'], $correctAnswer);

            expect($verified)->toBeTrue();
        });

        it('complete flow: generate via dependency injection', function () {
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            expect($result)->toHaveKeys(['token', 'image']);

            // Get correct answer and verify
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $correctAnswer = Cache::get($cacheKey);

            expect($captcha->verify($result['token'], $correctAnswer))->toBeTrue();
        });
    });

    describe('validation rule integration', function () {
        it('passes validation with correct captcha answer', function () {
            // Generate captcha
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Get correct answer
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $correctAnswer = Cache::get($cacheKey);

            // Validate
            $validator = Validator::make([
                'captcha_token' => $result['token'],
                'captcha_answer' => $correctAnswer,
            ], [
                'captcha_answer' => ['required', new ValidCaptcha()],
            ]);

            expect($validator->passes())->toBeTrue();
        });

        it('fails validation with incorrect captcha answer', function () {
            // Generate captcha
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Validate with wrong answer
            $validator = Validator::make([
                'captcha_token' => $result['token'],
                'captcha_answer' => 999999,
            ], [
                'captcha_answer' => ['required', new ValidCaptcha()],
            ]);

            expect($validator->fails())->toBeTrue()
                ->and($validator->errors()->first('captcha_answer'))->toBe('The CAPTCHA answer is incorrect. Please try again.');
        });

        it('fails validation when captcha token is missing', function () {
            $validator = Validator::make([
                'captcha_answer' => 42,
            ], [
                'captcha_answer' => ['required', new ValidCaptcha()],
            ]);

            expect($validator->fails())->toBeTrue()
                ->and($validator->errors()->first('captcha_answer'))->toBe('The CAPTCHA verification is required.');
        });

        it('validation with custom field names works', function () {
            // Generate captcha
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Get correct answer
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $correctAnswer = Cache::get($cacheKey);

            // Validate with custom field names
            $validator = Validator::make([
                'my_token' => $result['token'],
                'my_answer' => $correctAnswer,
            ], [
                'my_answer' => ['required', new ValidCaptcha('my_token', 'my_answer')],
            ]);

            expect($validator->passes())->toBeTrue();
        });
    });

    describe('facade functionality', function () {
        it('facade generate() returns expected structure', function () {
            $result = MathCaptcha::generate();

            expect($result)->toBeArray()
                ->toHaveKeys(['token', 'image'])
                ->and($result['token'])->toHaveLength(32)
                ->and($result['image'])->toStartWith('data:image/png;base64,');
        });

        it('facade verify() works correctly', function () {
            // Set up known values in cache
            Cache::put(config('math-captcha.cache_prefix', 'math_captcha').':facade_test_token', 42, now()->addMinutes(10));

            expect(MathCaptcha::verify('facade_test_token', 42))->toBeTrue();
        });

        it('facade resolves to CaptchaGenerator', function () {
            $facade = MathCaptcha::getFacadeRoot();

            expect($facade)->toBeInstanceOf(CaptchaGenerator::class);
        });
    });

    describe('security features', function () {
        it('token is one-time use', function () {
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Get correct answer
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $correctAnswer = Cache::get($cacheKey);

            // First verification succeeds
            expect($captcha->verify($result['token'], $correctAnswer))->toBeTrue();

            // Second verification fails (token already used)
            expect($captcha->verify($result['token'], $correctAnswer))->toBeFalse();
        });

        it('expired tokens are rejected', function () {
            // Manually create a captcha entry with short TTL
            $token = 'expired_test_token';
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$token;

            Cache::put($cacheKey, 42, now()->subMinutes(1)); // Already expired

            $captcha = app(CaptchaGenerator::class);

            expect($captcha->verify($token, 42))->toBeFalse();
        });

        it('invalid tokens are rejected', function () {
            $captcha = app(CaptchaGenerator::class);

            expect($captcha->verify('completely_invalid_token', 42))->toBeFalse();
        });

        it('brute force attempt fails after token used', function () {
            $captcha = app(CaptchaGenerator::class);
            $result = $captcha->generate();

            // Attempt wrong answer first
            $captcha->verify($result['token'], 1);

            // Now even correct answer won't work (token was deleted)
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            expect(Cache::has($cacheKey))->toBeFalse();
        });
    });

    describe('math operations validation', function () {
        it('addition produces valid results', function () {
            config(['math-captcha.operators' => ['+']]);
            config(['math-captcha.ranges' => [
                '+' => ['min1' => 10, 'max1' => 20, 'min2' => 5, 'max2' => 10],
            ]]);

            $this->app->forgetInstance(CaptchaGenerator::class);
            $this->app->singleton(CaptchaGenerator::class, function ($app) {
                return new \SolutionForest\MathCaptcha\MathCaptcha($app['config']->get('math-captcha', []));
            });

            $captcha = app(CaptchaGenerator::class);

            for ($i = 0; $i < 10; $i++) {
                $result = $captcha->generate();
                $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
                $answer = Cache::get($cacheKey);

                // Answer should be between 15 (10+5) and 30 (20+10)
                expect($answer)->toBeGreaterThanOrEqual(15)
                    ->toBeLessThanOrEqual(30);
            }
        });

        it('subtraction produces valid results', function () {
            config(['math-captcha.operators' => ['-']]);
            config(['math-captcha.ranges' => [
                '-' => ['min1' => 20, 'max1' => 30, 'min2' => 1, 'max2' => 10],
            ]]);

            $this->app->forgetInstance(CaptchaGenerator::class);
            $this->app->singleton(CaptchaGenerator::class, function ($app) {
                return new \SolutionForest\MathCaptcha\MathCaptcha($app['config']->get('math-captcha', []));
            });

            $captcha = app(CaptchaGenerator::class);

            for ($i = 0; $i < 10; $i++) {
                $result = $captcha->generate();
                $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
                $answer = Cache::get($cacheKey);

                // Answer should be between 10 (20-10) and 29 (30-1)
                expect($answer)->toBeGreaterThanOrEqual(10)
                    ->toBeLessThanOrEqual(29);
            }
        });

        it('multiplication produces valid results', function () {
            config(['math-captcha.operators' => ['*']]);
            config(['math-captcha.ranges' => [
                '*' => ['min1' => 2, 'max1' => 5, 'min2' => 2, 'max2' => 5],
            ]]);

            $this->app->forgetInstance(CaptchaGenerator::class);
            $this->app->singleton(CaptchaGenerator::class, function ($app) {
                return new \SolutionForest\MathCaptcha\MathCaptcha($app['config']->get('math-captcha', []));
            });

            $captcha = app(CaptchaGenerator::class);

            for ($i = 0; $i < 10; $i++) {
                $result = $captcha->generate();
                $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
                $answer = Cache::get($cacheKey);

                // Answer should be between 4 (2*2) and 25 (5*5)
                expect($answer)->toBeGreaterThanOrEqual(4)
                    ->toBeLessThanOrEqual(25);
            }
        });
    });
});
