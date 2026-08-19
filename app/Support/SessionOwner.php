<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/**
 * Aplikasi ini tidak memakai login. Setiap browser mendapat kunci acak di
 * session server yang dipakai untuk membatasi akses ke rekamannya sendiri.
 */
class SessionOwner
{
    public function __construct(private readonly Session $session) {}

    public function key(): string
    {
        $key = $this->session->get('notulensi.owner');

        if (! is_string($key) || $key === '') {
            $key = (string) Str::uuid();
            $this->session->put('notulensi.owner', $key);
        }

        return $key;
    }
}
