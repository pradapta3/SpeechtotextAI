<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordingStatus;
use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $owner_key
 * @property string $name
 * @property int $size_bytes
 * @property float $duration_seconds
 * @property string $language
 * @property int $chunk_seconds
 * @property RecordingStatus $status
 * @property int $total_chunks
 * @property array<int, array{index: int, start: float, end: float, text: string}> $segments
 * @property string|null $error
 * @property string|null $minutes
 */
class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'size_bytes',
        'duration_seconds',
        'language',
        'chunk_seconds',
        'total_chunks',
        'meeting_title',
        'meeting_date',
        'meeting_attendees',
        'meeting_context',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordingStatus::class,
            'segments' => 'array',
            'size_bytes' => 'integer',
            'duration_seconds' => 'float',
            'chunk_seconds' => 'integer',
            'total_chunks' => 'integer',
            'meeting_date' => 'date',
        ];
    }

    protected $attributes = [
        'status' => RecordingStatus::Pending->value,
        'segments' => '[]',
    ];

    /** @param  Builder<self>  $query */
    public function scopeOwnedBy(Builder $query, string $ownerKey): void
    {
        $query->where('owner_key', $ownerKey);
    }

    /**
     * Simpan hasil satu segmen. Segmen ditulis berdasarkan indeks sehingga
     * pengiriman ulang (retry setelah rate limit) tidak menduplikasi teks.
     */
    public function storeSegment(int $index, float $startSeconds, float $endSeconds, string $text): void
    {
        $segments = collect($this->segments)
            ->keyBy('index')
            ->put($index, [
                'index' => $index,
                'start' => round($startSeconds, 2),
                'end' => round($endSeconds, 2),
                'text' => $text,
            ])
            ->sortKeys()
            ->values()
            ->all();

        $this->segments = $segments;
        $this->save();
    }

    public function transcript(): string
    {
        return collect($this->segments)
            ->sortBy('index')
            ->pluck('text')
            ->map(fn (?string $text): string => trim((string) $text))
            ->filter()
            ->implode(' ');
    }

    public function completedChunks(): int
    {
        return count($this->segments);
    }

    public function progressPercent(): int
    {
        if ($this->status === RecordingStatus::Completed) {
            return 100;
        }

        if ($this->total_chunks < 1) {
            return 0;
        }

        return (int) min(99, floor($this->completedChunks() / $this->total_chunks * 100));
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'language' => $this->language,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_chunks' => $this->total_chunks,
            'completed_chunks' => $this->completedChunks(),
            'progress' => $this->progressPercent(),
            'transcript' => $this->transcript(),
            'minutes' => $this->minutes,
            'error' => $this->error,
            'meeting' => [
                'title' => $this->meeting_title,
                'date' => $this->meeting_date?->toDateString(),
                'attendees' => $this->meeting_attendees,
                'context' => $this->meeting_context,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
