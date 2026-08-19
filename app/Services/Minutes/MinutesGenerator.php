<?php

declare(strict_types=1);

namespace App\Services\Minutes;

use App\Exceptions\MinutesGenerationFailed;
use App\Models\Recording;

interface MinutesGenerator
{
    /**
     * Menghasilkan notulensi dalam format Markdown.
     *
     * @throws MinutesGenerationFailed
     */
    public function generate(Recording $recording, string $transcript, string $apiKey): string;
}
