<?php

use Illuminate\Support\Facades\Cache;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;
use SolutionForest\MathCaptcha\Facades\MathCaptcha;
use SolutionForest\MathCaptcha\MathCaptcha as MathCaptchaClass;

describe('MathCaptcha Facade', function () {
    describe('getFacadeAccessor()', function () {
        it('resolves to CaptchaGenerator interface', function () {
            $instance = MathCaptcha::getFacadeRoot();

            expect($instance)->toBeInstanceOf(CaptchaGenerator::class);
        });

        it('resolves to MathCaptcha class', function () {
            $instance = MathCaptcha::getFacadeRoot();

            expect($instance)->toBeInstanceOf(MathCaptchaClass::class);
        });
    });

    describe('generate()', function () {
        it('returns array with token and image', function () {
            $result = MathCaptcha::generate();

            expect($result)->toBeArray()
                ->toHaveKeys(['token', 'image']);
        });

        it('generates 32 character token', function () {
            $result = MathCaptcha::generate();

            expect($result['token'])->toHaveLength(32);
        });

        it('generates valid base64 PNG image', function () {
            $result = MathCaptcha::generate();

            expect($result['image'])->toStartWith('data:image/png;base64,');

            $base64Data = str_replace('data:image/png;base64,', '', $result['image']);
            $decodedImage = base64_decode($base64Data, true);

            expect($decodedImage)->not->toBeFalse();
        });

        it('stores answer in cache', function () {
            $result = MathCaptcha::generate();

            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];

            expect(Cache::has($cacheKey))->toBeTrue();
        });
    });

    describe('verify()', function () {
        it('returns true for correct answer', function () {
            // Set up known value
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':facade_verify_test';
            Cache::put($cacheKey, 42, now()->addMinutes(10));

            expect(MathCaptcha::verify('facade_verify_test', 42))->toBeTrue();
        });

        it('returns false for incorrect answer', function () {
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':facade_verify_wrong';
            Cache::put($cacheKey, 42, now()->addMinutes(10));

            expect(MathCaptcha::verify('facade_verify_wrong', 999))->toBeFalse();
        });

        it('returns false for non-existent token', function () {
            expect(MathCaptcha::verify('non_existent_facade_token', 42))->toBeFalse();
        });

        it('handles string answer', function () {
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':facade_string_test';
            Cache::put($cacheKey, 42, now()->addMinutes(10));

            expect(MathCaptcha::verify('facade_string_test', '42'))->toBeTrue();
        });
    });

    describe('facade integration', function () {
        it('generate and verify work together', function () {
            $result = MathCaptcha::generate();

            // Get the cached answer
            $cacheKey = config('math-captcha.cache_prefix', 'math_captcha').':'.$result['token'];
            $answer = Cache::get($cacheKey);

            expect(MathCaptcha::verify($result['token'], $answer))->toBeTrue();
        });

        it('generates unique tokens', function () {
            $result1 = MathCaptcha::generate();
            $result2 = MathCaptcha::generate();

            expect($result1['token'])->not->toBe($result2['token']);
        });
    });
});
