<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RecordingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecordingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Hanya status yang memang ditentukan browser. "completed" diberikan
            // server saat seluruh segmen selesai, bukan lewat endpoint ini.
            'status' => ['nullable', Rule::enum(RecordingStatus::class)->only([
                RecordingStatus::Processing,
                RecordingStatus::Failed,
                RecordingStatus::Cancelled,
            ])],
            'error' => ['nullable', 'string', 'max:2000'],
            'meeting_title' => ['nullable', 'string', 'max:200'],
            'meeting_date' => ['nullable', 'date'],
            'meeting_attendees' => ['nullable', 'string', 'max:500'],
            'meeting_context' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
