<?php

namespace SolutionForest\MathCaptcha\Facades;

use Illuminate\Support\Facades\Facade;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

/**
 * @method static array generate()
 * @method static bool verify(string $token, int|string $answer)
 *
 * @see \SolutionForest\MathCaptcha\MathCaptcha
 */
class MathCaptcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CaptchaGenerator::class;
    }
}
