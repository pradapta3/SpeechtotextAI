<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateMinutesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meeting_title' => ['nullable', 'string', 'max:200'],
            'meeting_date' => ['nullable', 'date'],
            'meeting_attendees' => ['nullable', 'string', 'max:500'],
            'meeting_context' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
