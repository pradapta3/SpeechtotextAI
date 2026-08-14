<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Notulensi datang sebagai Markdown dari model. Render-nya dilakukan di server
 * dengan CommonMark (HTML mentah dibuang) — jauh lebih aman dan akurat daripada
 * rangkaian regex di browser seperti pada versi statis sebelumnya.
 */
class Markdown
{
    public static function toHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
