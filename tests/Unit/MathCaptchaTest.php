<?php

use Illuminate\Support\Facades\Cache;
use SolutionForest\MathCaptcha\MathCaptcha;

describe('MathCaptcha', function () {
    describe('generate()', function () {
        it('returns an array with token and image keys', function () {
            $captcha = new MathCaptcha();
            $result = $captcha->generate();

            expect($result)->toBeArray()
                ->toHaveKeys(['token', 'image']);
        });

        it('generates a 32 character token', function () {
            $captcha = new MathCaptcha();
            $result = $captcha->generate();

            expect($result['token'])->toBeString()
                ->toHaveLength(32);
        });

        it('generates a valid base64 PNG image', function () {
            $captcha = new MathCaptcha();
            $result = $captcha->generate();

            expect($result['image'])->toBeString()
                ->toStartWith('data:image/png;base64,');

            // Verify we can decode the base64 portion
            $base64Data = str_replace('data:image/png;base64,', '', $result['image']);
            $decodedImage = base64_decode($base64Data, true);

            expect($decodedImage)->not->toBeFalse();
        });

        it('stores the answer in cache', function () {
            $captcha = new MathCaptcha(['cache_prefix' => 'test_captcha']);
            $result = $captcha->generate();

            $cacheKey = "test_captcha:{$result['token']}";
            $cachedAnswer = Cache::get($cacheKey);

            expect($cachedAnswer)->not->toBeNull()
                ->toBeInt();
        });

        it('generates different tokens on each call', function () {
            $captcha = new MathCaptcha();

            $result1 = $captcha->generate();
            $result2 = $captcha->generate();
            $result3 = $captcha->generate();

            expect($result1['token'])->not->toBe($result2['token'])
                ->and($result2['token'])->not->toBe($result3['token'])
                ->and($result1['token'])->not->toBe($result3['token']);
        });

        it('uses configured operators', function () {
            // Test with only addition operator
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'cache_prefix' => 'test_op',
            ]);

            // Generate multiple captchas and verify the answers make sense for addition
            for ($i = 0; $i < 5; $i++) {
                $result = $captcha->generate();
                $cacheKey = "test_op:{$result['token']}";
                $answer = Cache::get($cacheKey);

                // With default addition ranges (1-50, 1-50), answer should be between 2 and 100
                expect($answer)->toBeGreaterThanOrEqual(2)
                    ->toBeLessThanOrEqual(100);
            }
        });

        it('respects custom number ranges', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 1, 'max1' => 5, 'min2' => 1, 'max2' => 5],
                ],
                'cache_prefix' => 'test_range',
            ]);

            for ($i = 0; $i < 10; $i++) {
                $result = $captcha->generate();
                $cacheKey = "test_range:{$result['token']}";
                $answer = Cache::get($cacheKey);

                // Answer should be between 2 (1+1) and 10 (5+5)
                expect($answer)->toBeGreaterThanOrEqual(2)
                    ->toBeLessThanOrEqual(10);
            }
        });

        it('generates valid image with custom dimensions', function () {
            $captcha = new MathCaptcha([
                'width' => 300,
                'height' => 80,
            ]);
            $result = $captcha->generate();

            $base64Data = str_replace('data:image/png;base64,', '', $result['image']);
            $imageData = base64_decode($base64Data);
            $image = imagecreatefromstring($imageData);

            expect(imagesx($image))->toBe(300)
                ->and(imagesy($image))->toBe(80);

            imagedestroy($image);
        });
    });

    describe('verify()', function () {
        it('returns true for correct answer', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
            ]);

            $result = $captcha->generate();

            // With fixed numbers 5 + 3, answer is always 8
            expect($captcha->verify($result['token'], 8))->toBeTrue();
        });

        it('returns false for incorrect answer', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
            ]);

            $result = $captcha->generate();

            expect($captcha->verify($result['token'], 999))->toBeFalse();
        });

        it('returns false for non-existent token', function () {
            $captcha = new MathCaptcha();

            expect($captcha->verify('non_existent_token', 42))->toBeFalse();
        });

        it('deletes cache entry after successful verification', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
                'cache_prefix' => 'test_delete',
            ]);

            $result = $captcha->generate();
            $cacheKey = "test_delete:{$result['token']}";

            // Verify cache exists before verification
            expect(Cache::has($cacheKey))->toBeTrue();

            $captcha->verify($result['token'], 8);

            // Cache should be deleted after verification
            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('deletes cache entry after failed verification', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
                'cache_prefix' => 'test_delete_fail',
            ]);

            $result = $captcha->generate();
            $cacheKey = "test_delete_fail:{$result['token']}";

            // Verify cache exists before verification
            expect(Cache::has($cacheKey))->toBeTrue();

            $captcha->verify($result['token'], 999);

            // Cache should be deleted after verification attempt
            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('handles string answer input', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
            ]);

            $result = $captcha->generate();

            expect($captcha->verify($result['token'], '8'))->toBeTrue();
        });

        it('handles whitespace in string answer', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
            ]);

            $result = $captcha->generate();

            // PHP's (int) cast handles leading/trailing whitespace
            expect($captcha->verify($result['token'], ' 8 '))->toBeTrue();
        });

        it('prevents replay attacks (one-time use)', function () {
            $captcha = new MathCaptcha([
                'operators' => ['+'],
                'ranges' => [
                    '+' => ['min1' => 5, 'max1' => 5, 'min2' => 3, 'max2' => 3],
                ],
            ]);

            $result = $captcha->generate();

            // First verification should succeed
            expect($captcha->verify($result['token'], 8))->toBeTrue();

            // Second verification should fail (token already used)
            expect($captcha->verify($result['token'], 8))->toBeFalse();
        });
    });

    describe('calculateAnswer()', function () {
        it('correctly calculates addition', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('calculateAnswer');

            expect($method->invoke($captcha, '+', 10, 5))->toBe(15)
                ->and($method->invoke($captcha, '+', 0, 0))->toBe(0)
                ->and($method->invoke($captcha, '+', 50, 50))->toBe(100);
        });

        it('correctly calculates subtraction', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('calculateAnswer');

            expect($method->invoke($captcha, '-', 10, 5))->toBe(5)
                ->and($method->invoke($captcha, '-', 50, 20))->toBe(30)
                ->and($method->invoke($captcha, '-', 20, 20))->toBe(0);
        });

        it('correctly calculates multiplication', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('calculateAnswer');

            expect($method->invoke($captcha, '*', 10, 5))->toBe(50)
                ->and($method->invoke($captcha, '*', 12, 9))->toBe(108)
                ->and($method->invoke($captcha, '*', 2, 2))->toBe(4);
        });

        it('throws exception for invalid operator', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('calculateAnswer');

            expect(fn () => $method->invoke($captcha, '/', 10, 5))
                ->toThrow(InvalidArgumentException::class, 'Unsupported operator: /');
        });
    });

    describe('getOperatorSymbol()', function () {
        it('returns correct symbols for operators', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('getOperatorSymbol');

            expect($method->invoke($captcha, '+'))->toBe('+')
                ->and($method->invoke($captcha, '-'))->toBe('-')
                ->and($method->invoke($captcha, '*'))->toBe('x');
        });

        it('returns the operator itself for unknown operators', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('getOperatorSymbol');

            expect($method->invoke($captcha, '/'))->toBe('/');
        });
    });

    describe('configuration', function () {
        it('uses default config when no config provided', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $property = $reflection->getProperty('config');

            $config = $property->getValue($captcha);

            expect($config['width'])->toBe(200)
                ->and($config['height'])->toBe(60)
                ->and($config['ttl'])->toBe(10)
                ->and($config['operators'])->toBe(['+', '-', '*']);
        });

        it('merges custom config with defaults', function () {
            $captcha = new MathCaptcha([
                'width' => 300,
                'custom_key' => 'custom_value',
            ]);
            $reflection = new ReflectionClass($captcha);
            $property = $reflection->getProperty('config');

            $config = $property->getValue($captcha);

            expect($config['width'])->toBe(300)
                ->and($config['height'])->toBe(60) // default preserved
                ->and($config['custom_key'])->toBe('custom_value');
        });

        it('uses custom cache prefix', function () {
            $captcha = new MathCaptcha(['cache_prefix' => 'custom_prefix']);
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('getCacheKey');

            expect($method->invoke($captcha, 'test_token'))->toBe('custom_prefix:test_token');
        });
    });

    describe('generateImage()', function () {
        it('generates decodable PNG image', function () {
            $captcha = new MathCaptcha();
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('generateImage');

            $result = $method->invoke($captcha, '5 + 3 = ?');

            $base64Data = str_replace('data:image/png;base64,', '', $result);
            $imageData = base64_decode($base64Data);
            $image = imagecreatefromstring($imageData);

            expect($image)->not->toBeFalse();

            imagedestroy($image);
        });

        it('respects configured dimensions', function () {
            $captcha = new MathCaptcha([
                'width' => 250,
                'height' => 75,
            ]);
            $reflection = new ReflectionClass($captcha);
            $method = $reflection->getMethod('generateImage');

            $result = $method->invoke($captcha, '5 + 3 = ?');

            $base64Data = str_replace('data:image/png;base64,', '', $result);
            $imageData = base64_decode($base64Data);
            $image = imagecreatefromstring($imageData);

            expect(imagesx($image))->toBe(250)
                ->and(imagesy($image))->toBe(75);

            imagedestroy($image);
        });
    });
});
