<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordingStatus;
use App\Models\Recording;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recording> */
class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_key' => $this->faker->uuid(),
            'name' => $this->faker->word().'.mp3',
            'size_bytes' => 4_000_000,
            'duration_seconds' => 240,
            'language' => 'id',
            'chunk_seconds' => 120,
            'total_chunks' => 2,
            'status' => RecordingStatus::Pending,
            'segments' => [],
        ];
    }

    public function transcribed(string $text = 'Rapat dibuka pukul sembilan pagi.'): self
    {
        return $this->state(fn (): array => [
            'status' => RecordingStatus::Completed,
            'segments' => [['index' => 0, 'start' => 0, 'end' => 120, 'text' => $text]],
        ]);
    }
}
