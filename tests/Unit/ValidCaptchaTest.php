<?php

use Illuminate\Support\Facades\Cache;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\MathCaptcha;
use SolutionForest\MathCaptcha\Rules\ValidCaptcha;

beforeEach(function () {
    // Ensure we have a fresh CaptchaGenerator instance bound
    $this->app->singleton(CaptchaGenerator::class, function ($app) {
        return new MathCaptcha([
            'cache_prefix' => 'test_valid_captcha',
        ]);
    });
});

describe('ValidCaptcha Rule', function () {
    describe('validate()', function () {
        it('passes validation for correct answer', function () {
            $captcha = app(CaptchaGenerator::class);

            // Generate a captcha with known values
            Cache::put('test_valid_captcha:test_token_1', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'test_token_1',
                'captcha_answer' => 42,
            ]);

            $failed = false;
            $rule->validate('captcha_answer', 42, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });

        it('fails validation for incorrect answer', function () {
            Cache::put('test_valid_captcha:test_token_2', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'test_token_2',
                'captcha_answer' => 999,
            ]);

            $failMessage = null;
            $rule->validate('captcha_answer', 999, function ($message) use (&$failMessage) {
                $failMessage = $message;
            });

            expect($failMessage)->toBe('The CAPTCHA answer is incorrect. Please try again.');
        });

        it('fails validation when token is missing', function () {
            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_answer' => 42,
            ]);

            $failMessage = null;
            $rule->validate('captcha_answer', 42, function ($message) use (&$failMessage) {
                $failMessage = $message;
            });

            expect($failMessage)->toBe('The CAPTCHA verification is required.');
        });

        it('fails validation when answer is missing', function () {
            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'some_token',
            ]);

            $failMessage = null;
            $rule->validate('captcha_answer', null, function ($message) use (&$failMessage) {
                $failMessage = $message;
            });

            expect($failMessage)->toBe('The CAPTCHA verification is required.');
        });

        it('fails validation for non-existent token', function () {
            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'non_existent_token',
                'captcha_answer' => 42,
            ]);

            $failMessage = null;
            $rule->validate('captcha_answer', 42, function ($message) use (&$failMessage) {
                $failMessage = $message;
            });

            expect($failMessage)->toBe('The CAPTCHA answer is incorrect. Please try again.');
        });

        it('uses attribute value when answer field not in data', function () {
            Cache::put('test_valid_captcha:test_token_attr', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'test_token_attr',
                // 'captcha_answer' is NOT set - should use attribute value
            ]);

            $failed = false;
            $rule->validate('captcha_answer', 42, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });
    });

    describe('custom field names', function () {
        it('uses custom token field name', function () {
            Cache::put('test_valid_captcha:custom_token_1', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha('my_token', 'captcha_answer');
            $rule->setData([
                'my_token' => 'custom_token_1',
                'captcha_answer' => 42,
            ]);

            $failed = false;
            $rule->validate('captcha_answer', 42, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });

        it('uses custom answer field name', function () {
            Cache::put('test_valid_captcha:custom_token_2', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha('captcha_token', 'my_answer');
            $rule->setData([
                'captcha_token' => 'custom_token_2',
                'my_answer' => 42,
            ]);

            $failed = false;
            $rule->validate('my_answer', 42, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });

        it('uses both custom field names', function () {
            Cache::put('test_valid_captcha:custom_token_3', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha('my_custom_token', 'my_custom_answer');
            $rule->setData([
                'my_custom_token' => 'custom_token_3',
                'my_custom_answer' => 42,
            ]);

            $failed = false;
            $rule->validate('my_custom_answer', 42, function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });
    });

    describe('setData()', function () {
        it('returns the rule instance for method chaining', function () {
            $rule = new ValidCaptcha();
            $result = $rule->setData(['key' => 'value']);

            expect($result)->toBeInstanceOf(ValidCaptcha::class);
        });

        it('correctly stores the data', function () {
            $rule = new ValidCaptcha();
            $data = ['captcha_token' => 'token', 'captcha_answer' => 42];
            $rule->setData($data);

            $reflection = new ReflectionClass($rule);
            $property = $reflection->getProperty('data');

            expect($property->getValue($rule))->toBe($data);
        });
    });

    describe('string answer handling', function () {
        it('handles string answer correctly', function () {
            Cache::put('test_valid_captcha:string_token', 42, now()->addMinutes(10));

            $rule = new ValidCaptcha();
            $rule->setData([
                'captcha_token' => 'string_token',
                'captcha_answer' => '42',
            ]);

            $failed = false;
            $rule->validate('captcha_answer', '42', function () use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        });
    });
});
