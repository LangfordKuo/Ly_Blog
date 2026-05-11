<?php

namespace LyBlog\Core;

class Captcha
{
    private $width  = 120;
    private $height = 40;
    private $length = 4;
    private $fontSize = 20;

    private static $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Generate a random captcha code and store in session.
     */
    public static function generate(): string
    {
        $code = '';
        $chars = str_split(self::$chars);
        $max = count($chars) - 1;

        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        Session::set('captcha_code', $code);
        Session::set('captcha_time', time());

        return $code;
    }

    /**
     * Output the captcha image.
     */
    public static function output(): void
    {
        $code = self::generate();

        $width  = 120;
        $height = 40;

        $image = imagecreatetruecolor($width, $height);

        // Background
        $bgColor = imagecolorallocate($image, 250, 250, 252);
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        // Random lines
        for ($i = 0; $i < 6; $i++) {
            $lineColor = imagecolorallocate($image, random_int(180, 220), random_int(180, 220), random_int(200, 240));
            imageline($image,
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                $lineColor
            );
        }

        // Random dots
        for ($i = 0; $i < 30; $i++) {
            $dotColor = imagecolorallocate($image, random_int(150, 200), random_int(150, 200), random_int(200, 230));
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
        }

        // Draw characters
        $fontFile = self::getFontPath();
        $len = strlen($code);
        $charWidth = $width / ($len + 1);

        for ($i = 0; $i < $len; $i++) {
            $textColor = imagecolorallocate($image, random_int(50, 100), random_int(60, 120), random_int(140, 200));
            $x = (int)($charWidth * ($i + 0.7));
            $y = random_int(22, 30);

            if ($fontFile) {
                imagettftext($image, 18, random_int(-15, 15), $x, $y, $textColor, $fontFile, $code[$i]);
            } else {
                $size = 5;
                $x = (int)($charWidth * ($i + 0.5));
                $y = random_int(8, 14);
                imagestring($image, $size, $x, $y, $code[$i], $textColor);
            }
        }

        // Output
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        imagepng($image);
        imagedestroy($image);
        exit;
    }

    /**
     * Validate the submitted code.
     */
    public static function validate(string $input, bool $caseSensitive = false): bool
    {
        $code = Session::get('captcha_code', '');
        $time = Session::get('captcha_time', 0);

        // Clear session to prevent reuse
        Session::remove('captcha_code');
        Session::remove('captcha_time');

        // Expired after 10 minutes
        if (time() - $time > 600) {
            return false;
        }

        if (empty($code) || empty($input)) {
            return false;
        }

        return $caseSensitive
            ? $input === $code
            : strtoupper($input) === strtoupper($code);
    }

    /**
     * Check if captcha is enabled (based on config).
     */
    public static function isEnabled(): bool
    {
        return Config::get('captcha_enabled', '1') === '1';
    }

    /**
     * Find a suitable TTF font file.
     */
    private static function getFontPath(): ?string
    {
        $paths = [
            '/usr/share/fonts/truetype/lato/Lato-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            'C:\Windows\Fonts\arial.ttf',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
