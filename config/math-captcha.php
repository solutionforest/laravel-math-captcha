<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Dimensions
    |--------------------------------------------------------------------------
    |
    | The width and height of the generated CAPTCHA image in pixels.
    |
    */
    'width' => 200,
    'height' => 60,

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA Time To Live
    |--------------------------------------------------------------------------
    |
    | The number of minutes a CAPTCHA token remains valid. After this time,
    | the CAPTCHA will expire and a new one must be generated.
    |
    */
    'ttl' => 10,

    /*
    |--------------------------------------------------------------------------
    | Math Operators
    |--------------------------------------------------------------------------
    |
    | The mathematical operators to use when generating CAPTCHA challenges.
    | Available options: '+', '-', '*'
    |
    */
    'operators' => ['+', '-', '*'],

    /*
    |--------------------------------------------------------------------------
    | Number Ranges
    |--------------------------------------------------------------------------
    |
    | The minimum and maximum values for each operand based on the operator.
    | This helps ensure the math problems are solvable and the answers
    | are within reasonable ranges.
    |
    */
    'ranges' => [
        '+' => ['min1' => 1, 'max1' => 50, 'min2' => 1, 'max2' => 50],
        '-' => ['min1' => 20, 'max1' => 50, 'min2' => 1, 'max2' => 20],
        '*' => ['min1' => 2, 'max1' => 12, 'min2' => 2, 'max2' => 9],
    ],

    /*
    |--------------------------------------------------------------------------
    | Background Color
    |--------------------------------------------------------------------------
    |
    | The RGB values for the CAPTCHA image background color.
    |
    */
    'background_color' => [245, 245, 247],

    /*
    |--------------------------------------------------------------------------
    | Text Colors
    |--------------------------------------------------------------------------
    |
    | An array of RGB color values that will be randomly used for the
    | CAPTCHA text characters. Using multiple colors adds variety.
    |
    */
    'text_colors' => [
        [30, 30, 50],
        [50, 30, 80],
        [30, 60, 60],
    ],

    /*
    |--------------------------------------------------------------------------
    | Noise Colors
    |--------------------------------------------------------------------------
    |
    | An array of RGB color values used for the noise elements (lines and dots)
    | that help prevent OCR from reading the CAPTCHA.
    |
    */
    'noise_colors' => [
        [200, 200, 210],
        [180, 190, 200],
        [210, 200, 190],
    ],

    /*
    |--------------------------------------------------------------------------
    | Noise Elements
    |--------------------------------------------------------------------------
    |
    | The number of noise lines and dots to add to the image for obfuscation.
    |
    */
    'noise_lines' => 8,
    'noise_dots' => 100,

    /*
    |--------------------------------------------------------------------------
    | Font Size
    |--------------------------------------------------------------------------
    |
    | The built-in GD font size to use (1-5). Larger values create bigger text.
    |
    */
    'font_size' => 5,

    /*
    |--------------------------------------------------------------------------
    | Cache Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix used for cache keys when storing CAPTCHA answers.
    |
    */
    'cache_prefix' => 'math_captcha',

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the CAPTCHA endpoint route.
    |
    */
    'route' => [
        'enabled' => true,
        'uri' => '/captcha',
        'name' => 'captcha.generate',
        'middleware' => ['web'],
    ],
];
