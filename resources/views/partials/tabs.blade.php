@php
    $tabs = [
        'transkrip' => 'Transkrip',
        'notulensi' => 'Notulensi',
        'pengaturan' => 'Pengaturan',
    ];
@endphp

<div class="border-b border-line bg-surface">
    {{-- Ringkasan berkas aktif: menjawab "berkas apa, seberapa panjang, sudah sampai mana". --}}
    <template x-if="active">
        <div class="px-4 pt-4 sm:px-6" x-data="{ item: active }" x-effect="item = active">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="min-w-0 truncate font-serif text-base font-semibold text-ink" x-text="active.name"></h1>
                <span class="text-[11px]">@include('partials.status-badge')</span>
                <span x-show="loadingDetail" class="text-xs text-ink-faint">Memuat…</span>
            </div>

            <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-ink-faint">
                <div class="flex gap-1.5">
                    <dt>Durasi</dt>
                    <dd class="font-medium text-ink-soft"
                        x-text="active.duration_seconds > 0 ? formatDuration(active.duration_seconds) : '—'"></dd>
                </div>
                <div class="flex gap-1.5">
                    <dt>Ukuran</dt>
                    <dd class="font-medium text-ink-soft" x-text="formatSize(active.sizeBytes)"></dd>
                </div>
                <div class="flex gap-1.5">
                    <dt>Bahasa</dt>
                    <dd class="font-medium text-ink-soft" x-text="active.language_label ?? '—'"></dd>
                </div>
                <div class="flex gap-1.5">
                    <dt>Segmen</dt>
                    <dd class="font-medium text-ink-soft"
                        x-text="active.total_chunks > 0 ? `${active.completed_chunks}/${active.total_chunks}` : '—'"></dd>
                </div>
                <div class="flex gap-1.5">
                    <dt>Kata</dt>
                    <dd class="font-medium text-ink-soft" x-text="formatNumber(active.word_count)"></dd>
                </div>
                <div class="flex gap-1.5">
                    <dt>Ditambahkan</dt>
                    <dd class="font-medium text-ink-soft" x-text="formatDateTime(active.created_at)"></dd>
                </div>
            </dl>
        </div>
    </template>

    <template x-if="!active">
        <p class="px-4 pt-4 text-sm text-ink-faint sm:px-6">
            Pilih atau unggah rekaman untuk memulai.
        </p>
    </template>

    <div class="mt-3 flex gap-1 px-4 sm:px-6" role="tablist" aria-label="Bagian aplikasi">
        @foreach ($tabs as $key => $label)
            <button type="button"
                    id="tab-{{ $key }}"
                    role="tab"
                    aria-controls="panel-{{ $key }}"
                    :aria-selected="tab === '{{ $key }}'"
                    :tabindex="tab === '{{ $key }}' ? 0 : -1"
                    class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm transition-colors"
                    :class="tab === '{{ $key }}'
                        ? 'border-accent font-medium text-accent'
                        : 'border-transparent text-ink-faint hover:text-ink'"
                    @click="tab = '{{ $key }}'"
                    @keydown.right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                    @keydown.left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()">
                {{ $label }}

                @if ($key === 'notulensi')
                    <span class="rounded-full bg-positive/12 px-1.5 text-[10px] font-medium text-positive"
                          x-show="active?.has_minutes">siap</span>
                @endif

                @if ($key === 'pengaturan')
                    <span class="size-1.5 rounded-full bg-caution"
                          x-show="!providerReady('groq') || !providerReady('anthropic')"
                          title="Ada API key yang belum diatur" aria-label="Ada API key yang belum diatur"></span>
                @endif
            </button>
        @endforeach
    </div>
</div>
