<?php

namespace SolutionForest\MathCaptcha\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

class ValidCaptcha implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Create a new rule instance.
     */
    public function __construct(
        protected string $tokenField = 'captcha_token',
        protected string $answerField = 'captcha_answer'
    ) {}

    /**
     * Set the data under validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $token = $this->data[$this->tokenField] ?? null;
        $answer = $this->data[$this->answerField] ?? $value;

        if (! $token || ! $answer) {
            $fail('The CAPTCHA verification is required.');

            return;
        }

        /** @var CaptchaGenerator $captcha */
        $captcha = app(CaptchaGenerator::class);

        if (! $captcha->verify($token, $answer)) {
            $fail('The CAPTCHA answer is incorrect. Please try again.');
        }
    }
}
