# Laravel Math CAPTCHA

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x%20%7C%2012.x-red.svg)](https://laravel.com)

A simple, self-hosted math-based CAPTCHA package for Laravel with image generation to prevent AI/OCR bypass. No external API dependencies required.

> **Note:** This package was generated with the assistance of AI (Claude by Anthropic).

## Table of Contents

- [Screenshot](#screenshot)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Generate a CAPTCHA](#generate-a-captcha)
  - [Verify a CAPTCHA](#verify-a-captcha)
  - [Using the Facade](#using-the-facade)
  - [Using Dependency Injection](#using-dependency-injection)
  - [Using the Validation Rule](#using-the-validation-rule)
- [Frontend Integration](#frontend-integration)
  - [Livewire Example](#livewire-example)
  - [React Example](#react-example)
  - [Vue Example](#vue-example)
  - [Vanilla JavaScript Example](#vanilla-javascript-example)
- [Configuration](#configuration)
  - [Image Settings](#image-settings)
  - [Math Operations](#math-operations)
  - [Visual Customization](#visual-customization)
  - [Route Configuration](#route-configuration)
- [API Reference](#api-reference)
- [Security Considerations](#security-considerations)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Screenshot

![CAPTCHA Example](docs/captcha-example.png)

*Example of a generated math CAPTCHA image with noise and distortion*

## Features

- **Math-based CAPTCHA** - Generates simple arithmetic problems (addition, subtraction, multiplication)
- **Image Generation** - Renders CAPTCHA as PNG image with anti-OCR measures:
  - Random noise lines and dots
  - Character position variations
  - Multiple text colors
  - Curved distortion line
- **Self-hosted** - No external API calls, no third-party dependencies
- **Configurable** - Customize difficulty, appearance, and behavior
- **Laravel Integration** - Service provider auto-discovery, Facade support, validation rule
- **One-time Use** - Each CAPTCHA token is invalidated after verification attempt
- **Cache-based Storage** - Uses Laravel's cache system for token storage

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- GD extension (for image generation)

## Installation

Install the package via Composer:

```bash
composer require solution-forest/laravel-math-captcha
```

The package will automatically register its service provider via Laravel's package auto-discovery.

### Publish Configuration (Optional)

To customize the CAPTCHA settings, publish the configuration file:

```bash
php artisan vendor:publish --tag=math-captcha-config
```

This will create a `config/math-captcha.php` file in your application.

## Quick Start

1. **Install the package:**
   ```bash
   composer require solution-forest/laravel-math-captcha
   ```

2. **Add CAPTCHA to your form (frontend):**
   ```html
   <img id="captcha-image" src="" alt="CAPTCHA">
   <button type="button" onclick="refreshCaptcha()">Refresh</button>
   <input type="hidden" name="captcha_token" id="captcha-token">
   <input type="text" name="captcha_answer" placeholder="Enter the answer">

   <script>
   async function refreshCaptcha() {
       const response = await fetch('/captcha');
       const data = await response.json();
       document.getElementById('captcha-image').src = data.image;
       document.getElementById('captcha-token').value = data.token;
   }
   refreshCaptcha(); // Load initial CAPTCHA
   </script>
   ```

3. **Verify in your controller (backend):**
   ```php
   use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

   public function store(Request $request, CaptchaGenerator $captcha)
   {
       if (!$captcha->verify($request->captcha_token, $request->captcha_answer)) {
           return back()->withErrors(['captcha' => 'Incorrect answer']);
       }

       // Process form...
   }
   ```

## Usage

### Generate a CAPTCHA

The package automatically registers a route at `/captcha` (GET) that returns JSON:

```json
{
    "token": "abc123def456...",
    "image": "data:image/png;base64,iVBORw0KGgo..."
}
```

- `token` - A unique 32-character string to identify this CAPTCHA challenge
- `image` - Base64-encoded PNG image data URI that can be used directly in `<img src="">`

### Verify a CAPTCHA

Send the `token` and user's `answer` to your backend for verification. The verification:
- Returns `true` if the answer is correct
- Returns `false` if the answer is wrong or the token is invalid/expired
- Invalidates the token after the first verification attempt (one-time use)

### Using the Facade

```php
use SolutionForest\MathCaptcha\Facades\MathCaptcha;

// Generate a new CAPTCHA
$captcha = MathCaptcha::generate();
// Returns: [
//     'token' => 'abc123...',
//     'image' => 'data:image/png;base64,...'
// ]

// Verify an answer
$isValid = MathCaptcha::verify($token, $userAnswer);
// Returns: true or false
```

### Using Dependency Injection

```php
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

class ContactController extends Controller
{
    public function store(Request $request, CaptchaGenerator $captcha)
    {
        // Verify the CAPTCHA
        $isValid = $captcha->verify(
            $request->input('captcha_token'),
            $request->input('captcha_answer')
        );

        if (!$isValid) {
            return back()->withErrors(['captcha' => 'Invalid CAPTCHA answer. Please try again.']);
        }

        // CAPTCHA is valid, process the form...
    }
}
```

### Using the Validation Rule

For cleaner validation, use the built-in validation rule:

```php
use SolutionForest\MathCaptcha\Rules\ValidCaptcha;

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email',
        'captcha_answer' => ['required', new ValidCaptcha()],
    ]);

    // Validation passed, process the form...
}
```

The rule automatically looks for `captcha_token` and `captcha_answer` fields. You can customize the field names:

```php
new ValidCaptcha(
    tokenField: 'my_captcha_token',
    answerField: 'my_captcha_answer'
)
```

## Frontend Integration

### Livewire Example

**Livewire Component (app/Livewire/ContactForm.php):**

```php
<?php

namespace App\Livewire;

use Livewire\Component;
use SolutionForest\MathCaptcha\Contracts\CaptchaGenerator;

class ContactForm extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $message = '';
    public string $captchaToken = '';
    public string $captchaImage = '';
    public string $captchaAnswer = '';

    public function mount(): void
    {
        $this->refreshCaptcha();
    }

    public function refreshCaptcha(): void
    {
        $captcha = app(CaptchaGenerator::class)->generate();
        $this->captchaToken = $captcha['token'];
        $this->captchaImage = $captcha['image'];
        $this->captchaAnswer = '';
    }

    public function submit(CaptchaGenerator $captcha): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string',
            'captchaAnswer' => 'required',
        ]);

        // Verify CAPTCHA
        if (!$captcha->verify($this->captchaToken, $this->captchaAnswer)) {
            $this->addError('captchaAnswer', 'Incorrect answer. Please try again.');
            $this->refreshCaptcha();
            return;
        }

        // Process form submission...

        session()->flash('success', 'Your message has been sent!');
        $this->reset(['name', 'email', 'phone', 'message', 'captchaAnswer']);
        $this->refreshCaptcha();
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
```

**Blade View (resources/views/livewire/contact-form.blade.php):**

```blade
<form wire:submit="submit">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div>
        <label for="name">Name</label>
        <input type="text" id="name" wire:model="name">
        @error('name') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="email">Email</label>
        <input type="email" id="email" wire:model="email">
        @error('email') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="phone">Phone</label>
        <input type="tel" id="phone" wire:model="phone">
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="message">Message</label>
        <textarea id="message" wire:model="message"></textarea>
    </div>

    <div>
        <label>Security Check</label>
        <div class="captcha-container">
            <img src="{{ $captchaImage }}" alt="CAPTCHA">
            <button type="button" wire:click="refreshCaptcha" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshCaptcha">Refresh</span>
                <span wire:loading wire:target="refreshCaptcha">Loading...</span>
            </button>
        </div>
        <input
            type="text"
            wire:model="captchaAnswer"
            placeholder="Enter the answer"
            inputmode="numeric"
        >
        @error('captchaAnswer') <span class="error">{{ $message }}</span> @enderror
    </div>

    <button type="submit" wire:loading.attr="disabled">
        <span wire:loading.remove>Submit</span>
        <span wire:loading>Sending...</span>
    </button>
</form>
```

### React Example

```tsx
import { useState, useEffect, useCallback } from 'react';

interface CaptchaData {
    token: string;
    image: string;
}

function ContactForm() {
    const [captcha, setCaptcha] = useState<CaptchaData | null>(null);
    const [answer, setAnswer] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const fetchCaptcha = useCallback(async () => {
        setIsLoading(true);
        try {
            const response = await fetch('/captcha');
            const data = await response.json();
            setCaptcha(data);
            setAnswer('');
        } catch (error) {
            console.error('Failed to fetch CAPTCHA:', error);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchCaptcha();
    }, [fetchCaptcha]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        const formData = new FormData(e.target as HTMLFormElement);
        formData.append('captcha_token', captcha?.token || '');
        formData.append('captcha_answer', answer);

        // Submit form...
    };

    return (
        <form onSubmit={handleSubmit}>
            {/* Other form fields... */}

            <div className="captcha-container">
                <div className="captcha-image">
                    {isLoading ? (
                        <span>Loading...</span>
                    ) : (
                        <img
                            src={captcha?.image}
                            alt="CAPTCHA"
                            draggable={false}
                        />
                    )}
                </div>
                <button type="button" onClick={fetchCaptcha} disabled={isLoading}>
                    Refresh
                </button>
            </div>

            <input
                type="text"
                value={answer}
                onChange={(e) => setAnswer(e.target.value)}
                placeholder="Enter the answer"
                inputMode="numeric"
                required
            />

            <button type="submit">Submit</button>
        </form>
    );
}
```

### Vue Example

```vue
<template>
    <form @submit.prevent="handleSubmit">
        <!-- Other form fields... -->

        <div class="captcha-container">
            <div class="captcha-image">
                <span v-if="isLoading">Loading...</span>
                <img v-else :src="captcha?.image" alt="CAPTCHA" />
            </div>
            <button type="button" @click="fetchCaptcha" :disabled="isLoading">
                Refresh
            </button>
        </div>

        <input
            v-model="answer"
            type="text"
            placeholder="Enter the answer"
            inputmode="numeric"
            required
        />

        <button type="submit">Submit</button>
    </form>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const captcha = ref(null);
const answer = ref('');
const isLoading = ref(false);

async function fetchCaptcha() {
    isLoading.value = true;
    try {
        const response = await fetch('/captcha');
        captcha.value = await response.json();
        answer.value = '';
    } catch (error) {
        console.error('Failed to fetch CAPTCHA:', error);
    } finally {
        isLoading.value = false;
    }
}

async function handleSubmit() {
    const formData = {
        // Other form data...
        captcha_token: captcha.value?.token,
        captcha_answer: answer.value,
    };

    // Submit form...
}

onMounted(fetchCaptcha);
</script>
```

### Vanilla JavaScript Example

```html
<form id="contact-form">
    <!-- Other form fields... -->

    <div class="captcha-container">
        <img id="captcha-image" src="" alt="CAPTCHA">
        <button type="button" id="refresh-captcha">Refresh</button>
    </div>

    <input type="hidden" name="captcha_token" id="captcha-token">
    <input
        type="text"
        name="captcha_answer"
        id="captcha-answer"
        placeholder="Enter the answer"
        inputmode="numeric"
        required
    >

    <button type="submit">Submit</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const captchaImage = document.getElementById('captcha-image');
    const captchaToken = document.getElementById('captcha-token');
    const captchaAnswer = document.getElementById('captcha-answer');
    const refreshButton = document.getElementById('refresh-captcha');

    async function fetchCaptcha() {
        try {
            const response = await fetch('/captcha');
            const data = await response.json();

            captchaImage.src = data.image;
            captchaToken.value = data.token;
            captchaAnswer.value = '';
        } catch (error) {
            console.error('Failed to fetch CAPTCHA:', error);
        }
    }

    refreshButton.addEventListener('click', fetchCaptcha);

    // Load initial CAPTCHA
    fetchCaptcha();
});
</script>
```

## Configuration

After publishing the configuration file, you can customize the following options in `config/math-captcha.php`:

### Image Settings

```php
// Image dimensions in pixels
'width' => 200,
'height' => 60,

// Built-in GD font size (1-5, where 5 is largest)
'font_size' => 5,
```

### Math Operations

```php
// How long a CAPTCHA token remains valid (in minutes)
'ttl' => 10,

// Math operators to use: '+' (addition), '-' (subtraction), '*' (multiplication)
'operators' => ['+', '-', '*'],

// Number ranges for each operator to keep problems reasonable
// For subtraction, num1 is always >= num2 to avoid negative answers
'ranges' => [
    '+' => ['min1' => 1, 'max1' => 50, 'min2' => 1, 'max2' => 50],  // Results: 2-100
    '-' => ['min1' => 20, 'max1' => 50, 'min2' => 1, 'max2' => 20], // Results: 0-49
    '*' => ['min1' => 2, 'max1' => 12, 'min2' => 2, 'max2' => 9],   // Results: 4-108
],
```

### Visual Customization

```php
// Background color [R, G, B]
'background_color' => [245, 245, 247],

// Text colors (randomly selected per character) [R, G, B]
'text_colors' => [
    [30, 30, 50],   // Dark blue-gray
    [50, 30, 80],   // Purple-gray
    [30, 60, 60],   // Teal-gray
],

// Noise element colors [R, G, B]
'noise_colors' => [
    [200, 200, 210],
    [180, 190, 200],
    [210, 200, 190],
],

// Number of noise lines to draw
'noise_lines' => 8,

// Number of noise dots to draw
'noise_dots' => 100,
```

### Route Configuration

```php
'route' => [
    // Set to false to disable automatic route registration
    'enabled' => true,

    // The URI for the CAPTCHA endpoint
    'uri' => '/captcha',

    // Route name for URL generation: route('captcha.generate')
    'name' => 'captcha.generate',

    // Middleware to apply to the route
    'middleware' => ['web'],
],
```

### Cache Settings

```php
// Prefix for cache keys (useful if you have multiple apps sharing cache)
'cache_prefix' => 'math_captcha',
```

## API Reference

### `CaptchaGenerator` Interface

```php
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
     *
     * @param string $token The CAPTCHA token
     * @param int|string $answer The user's answer
     * @return bool True if correct, false otherwise
     */
    public function verify(string $token, int|string $answer): bool;
}
```

### `ValidCaptcha` Rule

```php
use SolutionForest\MathCaptcha\Rules\ValidCaptcha;

// Default field names
new ValidCaptcha();

// Custom field names
new ValidCaptcha(
    tokenField: 'my_token_field',
    answerField: 'my_answer_field'
);
```

## Security Considerations

1. **One-time Use**: Each CAPTCHA token is invalidated after the first verification attempt, preventing replay attacks.

2. **Token Expiration**: Tokens expire after a configurable time (default: 10 minutes), limiting the window for brute-force attacks.

3. **Rate Limiting**: Consider adding rate limiting to your CAPTCHA endpoint to prevent abuse:
   ```php
   // In your route configuration or middleware
   Route::middleware(['throttle:60,1'])->get('/captcha', ...);
   ```

4. **HTTPS**: Always serve your application over HTTPS to prevent CAPTCHA images and tokens from being intercepted.

5. **Cache Security**: CAPTCHA answers are stored in your application's cache. Ensure your cache driver is properly secured.

## Troubleshooting

### CAPTCHA Image Shows Strange Characters

**Problem**: The image displays garbled characters like "A" instead of math symbols.

**Solution**: This package uses PHP's built-in GD fonts which only support ASCII characters. The operators are displayed as:
- Addition: `+`
- Subtraction: `-`
- Multiplication: `x` (lowercase x)

### GD Extension Not Found

**Problem**: Error "Call to undefined function imagecreatetruecolor()"

**Solution**: Install the GD extension:
```bash
# Ubuntu/Debian
sudo apt-get install php-gd

# macOS with Homebrew
brew install php-gd

# Then restart your web server
```

### CAPTCHA Always Fails Verification

**Problem**: Correct answers are rejected.

**Possible Causes**:
1. Token expired (default: 10 minutes)
2. Token already used (one-time use)
3. Cache not working properly

**Solution**: Check your cache configuration and ensure the cache driver is working correctly.

### Route Not Found

**Problem**: 404 error when accessing `/captcha`

**Solution**:
1. Clear route cache: `php artisan route:clear`
2. Check if route registration is enabled in config
3. Verify the package is properly installed: `composer dump-autoload`

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).

---

**Made with AI assistance by [Solution Forest](https://solutionforest.net)**
