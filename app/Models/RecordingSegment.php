<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu potongan audio beserta hasil transkripsinya.
 *
 * @property int $position
 * @property float $start_seconds
 * @property float $end_seconds
 * @property string|null $text
 */
class RecordingSegment extends Model
{
    protected $fillable = ['position', 'start_seconds', 'end_seconds', 'text'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_seconds' => 'float',
            'end_seconds' => 'float',
        ];
    }

    /** @return BelongsTo<Recording, $this> */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'index' => $this->position,
            'start' => round($this->start_seconds, 2),
            'end' => round($this->end_seconds, 2),
            'text' => (string) $this->text,
        ];
    }
}
