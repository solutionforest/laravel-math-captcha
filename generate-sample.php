<?php

/**
 * Script to generate sample CAPTCHA images for documentation.
 */

// Configuration
$config = [
    'width' => 200,
    'height' => 60,
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
];

/**
 * Generate a CAPTCHA image with distortion.
 */
function generateCaptchaImage(string $text, array $config): GdImage
{
    $width = $config['width'];
    $height = $config['height'];

    $image = imagecreatetruecolor($width, $height);

    // Allocate colors
    $bgColor = imagecolorallocate(
        $image,
        $config['background_color'][0],
        $config['background_color'][1],
        $config['background_color'][2]
    );

    $textColors = array_map(
        fn ($color) => imagecolorallocate($image, $color[0], $color[1], $color[2]),
        $config['text_colors']
    );

    $noiseColors = array_map(
        fn ($color) => imagecolorallocate($image, $color[0], $color[1], $color[2]),
        $config['noise_colors']
    );

    // Fill background
    imagefill($image, 0, 0, $bgColor);

    // Add noise lines
    for ($i = 0; $i < $config['noise_lines']; $i++) {
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
    for ($i = 0; $i < $config['noise_dots']; $i++) {
        $noiseColor = $noiseColors[array_rand($noiseColors)];
        imagesetpixel(
            $image,
            random_int(0, $width),
            random_int(0, $height),
            $noiseColor
        );
    }

    // Draw text character by character with slight variations
    $fontSize = $config['font_size'];
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

    return $image;
}

// Create docs directory if it doesn't exist
if (!is_dir(__DIR__ . '/docs')) {
    mkdir(__DIR__ . '/docs', 0755, true);
}

// Generate sample captcha images
$samples = [
    '24 + 17 = ?' => 'captcha-addition.png',
    '45 - 12 = ?' => 'captcha-subtraction.png',
    '7 x 8 = ?' => 'captcha-multiplication.png',
];

echo "Generating sample CAPTCHA images...\n";

foreach ($samples as $text => $filename) {
    $image = generateCaptchaImage($text, $config);
    $filepath = __DIR__ . '/docs/' . $filename;
    imagepng($image, $filepath);
    imagedestroy($image);
    echo "  Created: docs/{$filename}\n";
}

// Generate a main sample image
$mainImage = generateCaptchaImage('32 + 15 = ?', $config);
imagepng($mainImage, __DIR__ . '/docs/captcha-example.png');
imagedestroy($mainImage);
echo "  Created: docs/captcha-example.png\n";

echo "\nDone! Sample images saved to the docs/ directory.\n";
