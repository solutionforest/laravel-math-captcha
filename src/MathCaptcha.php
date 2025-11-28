<?php

namespace SolutionForest\MathCaptcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

class MathCaptcha implements CaptchaGenerator
{
    /**
     * Create a new MathCaptcha instance.
     */
    public function __construct(
        protected array $config = []
    ) {
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Get the default configuration.
     */
    protected function getDefaultConfig(): array
    {
        return [
            'width' => 200,
            'height' => 60,
            'ttl' => 10, // minutes
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
            'cache_prefix' => 'math_captcha',
        ];
    }

    /**
     * Generate a new math CAPTCHA challenge.
     *
     * @return array{token: string, image: string}
     */
    public function generate(): array
    {
        $operators = $this->config['operators'];
        $operator = $operators[array_rand($operators)];

        $ranges = $this->config['ranges'][$operator];
        $num1 = random_int($ranges['min1'], $ranges['max1']);
        $num2 = random_int($ranges['min2'], $ranges['max2']);

        $answer = $this->calculateAnswer($operator, $num1, $num2);
        $token = Str::random(32);

        // Store the answer in cache
        Cache::put(
            $this->getCacheKey($token),
            $answer,
            now()->addMinutes($this->config['ttl'])
        );

        $operatorSymbol = $this->getOperatorSymbol($operator);
        $question = "{$num1} {$operatorSymbol} {$num2} = ?";
        $image = $this->generateImage($question);

        return [
            'token' => $token,
            'image' => $image,
        ];
    }

    /**
     * Verify a CAPTCHA answer.
     */
    public function verify(string $token, int|string $answer): bool
    {
        $cacheKey = $this->getCacheKey($token);
        $expectedAnswer = Cache::get($cacheKey);

        if ($expectedAnswer === null) {
            return false;
        }

        // Remove the CAPTCHA after verification attempt (one-time use)
        Cache::forget($cacheKey);

        return (int) $answer === (int) $expectedAnswer;
    }

    /**
     * Calculate the answer for a math operation.
     */
    protected function calculateAnswer(string $operator, int $num1, int $num2): int
    {
        return match ($operator) {
            '+' => $num1 + $num2,
            '-' => $num1 - $num2,
            '*' => $num1 * $num2,
            default => throw new \InvalidArgumentException("Unsupported operator: {$operator}"),
        };
    }

    /**
     * Get the display symbol for an operator.
     * Note: Using ASCII characters only as imagestring() doesn't support Unicode.
     */
    protected function getOperatorSymbol(string $operator): string
    {
        return match ($operator) {
            '+' => '+',
            '-' => '-',
            '*' => 'x',
            default => $operator,
        };
    }

    /**
     * Get the cache key for a token.
     */
    protected function getCacheKey(string $token): string
    {
        return "{$this->config['cache_prefix']}:{$token}";
    }

    /**
     * Generate a CAPTCHA image with distortion to prevent OCR.
     */
    protected function generateImage(string $text): string
    {
        $width = $this->config['width'];
        $height = $this->config['height'];

        $image = imagecreatetruecolor($width, $height);

        // Allocate colors
        $bgColor = imagecolorallocate(
            $image,
            $this->config['background_color'][0],
            $this->config['background_color'][1],
            $this->config['background_color'][2]
        );

        $textColors = array_map(
            fn ($color) => imagecolorallocate($image, $color[0], $color[1], $color[2]),
            $this->config['text_colors']
        );

        $noiseColors = array_map(
            fn ($color) => imagecolorallocate($image, $color[0], $color[1], $color[2]),
            $this->config['noise_colors']
        );

        // Fill background
        imagefill($image, 0, 0, $bgColor);

        // Add noise lines
        for ($i = 0; $i < $this->config['noise_lines']; $i++) {
            $noiseColor = $noiseColors[array_rand($noiseColors)];
            imageline(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                random_int(0, $width),
                random_int(0, $height),
                $noiseColor
            );
        }

        // Add noise dots
        for ($i = 0; $i < $this->config['noise_dots']; $i++) {
            $noiseColor = $noiseColors[array_rand($noiseColors)];
            imagesetpixel(
                $image,
                random_int(0, $width),
                random_int(0, $height),
                $noiseColor
            );
        }

        // Draw text character by character with slight variations
        $fontSize = $this->config['font_size'];
        $charWidth = imagefontwidth($fontSize);
        $charHeight = imagefontheight($fontSize);

        $textLength = mb_strlen($text);
        $totalWidth = $textLength * ($charWidth + 2);
        $startX = ($width - $totalWidth) / 2;
        $baseY = ($height - $charHeight) / 2;

        for ($i = 0; $i < $textLength; $i++) {
            $char = mb_substr($text, $i, 1);
            $textColor = $textColors[array_rand($textColors)];

            // Add slight vertical variation
            $y = $baseY + random_int(-4, 4);
            $x = $startX + $i * ($charWidth + 2) + random_int(-1, 1);

            imagestring($image, $fontSize, (int) $x, (int) $y, $char, $textColor);
        }

        // Add curved line through text for additional obfuscation
        $lineColor = imagecolorallocate($image, 150, 150, 160);
        $points = [];
        for ($x = 0; $x < $width; $x += 10) {
            $y = $height / 2 + sin($x / 20) * 10 + random_int(-3, 3);
            $points[] = $x;
            $points[] = (int) $y;
        }

        if (count($points) >= 4) {
            for ($i = 0; $i < count($points) - 2; $i += 2) {
                imageline($image, $points[$i], $points[$i + 1], $points[$i + 2], $points[$i + 3], $lineColor);
            }
        }

        // Convert to base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($imageData);
    }
}
