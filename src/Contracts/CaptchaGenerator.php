<?php

namespace SolutionForest\MathCaptcha\Contracts;

interface CaptchaGenerator
{
    /**
     * Generate a new CAPTCHA challenge.
     *
     * @return array{token: string, image: string}
     */
    public function generate(): array;

    /**
     * Verify a CAPTCHA answer.
     */
    public function verify(string $token, int|string $answer): bool;
}
