<?php

namespace SolutionForest\MathCaptcha\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

class CaptchaController extends Controller
{
    /**
     * Generate a new CAPTCHA challenge.
     */
    public function generate(CaptchaGenerator $captcha): JsonResponse
    {
        return response()->json($captcha->generate());
    }
}
