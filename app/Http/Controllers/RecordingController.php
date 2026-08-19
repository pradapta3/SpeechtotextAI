<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecordingStatus;
use App\Http\Requests\StoreRecordingRequest;
use App\Http\Requests\UpdateRecordingRequest;
use App\Models\Recording;
use App\Support\SessionOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class RecordingController extends Controller
{
    public function __construct(private readonly SessionOwner $owner) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'recordings' => Recording::query()
                ->ownedBy($this->owner->key())
                ->latest('id')
                ->get()
                ->map->toSummary(),
        ]);
    }

    /** Transkrip dan notulensi hanya diambil saat rekaman dibuka. */
    public function show(Recording $recording): JsonResponse
    {
        return response()->json(['recording' => $recording->toDetail()]);
    }

    public function store(StoreRecordingRequest $request): JsonResponse
    {
        $ownerKey = $this->owner->key();
        $limit = (int) config('notulensi.limits.recordings_per_session');

        if (Recording::query()->ownedBy($ownerKey)->count() >= $limit) {
            throw ValidationException::withMessages([
                'name' => "Batas {$limit} rekaman tersimpan tercapai. Hapus rekaman lama terlebih dahulu.",
            ]);
        }

        $recording = new Recording($request->validated());
        $recording->owner_key = $ownerKey;
        $recording->save();

        return response()->json(['recording' => $recording->toDetail()], 201);
    }

    public function update(UpdateRecordingRequest $request, Recording $recording): JsonResponse
    {
        $recording->fill($request->safe()->except(['status', 'error']));

        if ($status = $request->validated('status')) {
            $recording->status = RecordingStatus::from($status);
        }

        if ($request->has('error')) {
            $recording->error = $request->validated('error');
        }

        $recording->save();

        return response()->json(['recording' => $recording->toDetail()]);
    }

    public function destroy(Recording $recording): JsonResponse
    {
        $recording->delete();

        return response()->json(status: 204);
    }
}
