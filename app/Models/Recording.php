<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordingStatus;
use App\Support\Markdown;
use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            'transcribed_at' => 'datetime',
            'minutes_generated_at' => 'datetime',
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
     * Simpan hasil satu segmen.
     *
     * Segmen dikunci pada indeksnya, sehingga pengiriman ulang (mis. setelah
     * rate limit) menimpa segmen lama alih-alih menduplikasi teks.
     *
     * Kolom `segments` ditulis dengan pola baca-ubah-tulis, jadi dua permintaan
     * yang tumpang tindih bisa saling menghapus hasil. Transaksi menutup celah
     * itu: pada MySQL/PostgreSQL lewat `lockForUpdate()`, dan pada SQLite —
     * yang mengabaikan klausa tersebut — lewat mode transaksi IMMEDIATE yang
     * disetel di config/database.php.
     */
    public function storeSegment(int $index, float $startSeconds, float $endSeconds, string $text): void
    {
        DB::transaction(function () use ($index, $startSeconds, $endSeconds, $text): void {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $this->segments = collect($fresh->segments)
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

            $this->save();
        });
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

    public function languageLabel(): string
    {
        return config('notulensi.languages')[$this->language] ?? $this->language;
    }

    public function wordCount(): int
    {
        $transcript = trim($this->transcript());

        return $transcript === '' ? 0 : count(preg_split('/\s+/u', $transcript) ?: []);
    }

    /**
     * Ringkasan untuk daftar rekaman: sengaja tanpa transkrip dan notulensi
     * agar memuat lima puluh rekaman tidak berarti mengirim lima puluh
     * transkrip panjang ke browser.
     *
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'size_bytes' => $this->size_bytes,
            'duration_seconds' => $this->duration_seconds,
            'language' => $this->language,
            'language_label' => $this->languageLabel(),
            'chunk_seconds' => $this->chunk_seconds,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'total_chunks' => $this->total_chunks,
            'completed_chunks' => $this->completedChunks(),
            'progress' => $this->progressPercent(),
            'has_transcript' => $this->completedChunks() > 0,
            'has_minutes' => filled($this->minutes),
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'transcribed_at' => $this->transcribed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function toDetail(): array
    {
        return array_merge($this->toSummary(), [
            'loaded' => true,
            'segments' => collect($this->segments)
                ->sortBy('index')
                ->map(fn (array $segment): array => [
                    'index' => $segment['index'],
                    'start' => $segment['start'],
                    'end' => $segment['end'],
                    'text' => $segment['text'],
                ])
                ->values()
                ->all(),
            'transcript' => $this->transcript(),
            'word_count' => $this->wordCount(),
            'minutes' => $this->minutes,
            'minutes_html' => Markdown::toHtml($this->minutes),
            'minutes_model' => $this->minutes_model,
            'minutes_generated_at' => $this->minutes_generated_at?->toIso8601String(),
            'meeting' => [
                'meeting_title' => $this->meeting_title,
                'meeting_date' => $this->meeting_date?->toDateString(),
                'meeting_attendees' => $this->meeting_attendees,
                'meeting_context' => $this->meeting_context,
            ],
        ]);
    }
}
