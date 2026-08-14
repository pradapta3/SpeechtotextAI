@php
    $tabs = [
        'transkrip' => 'Transkrip',
        'notulensi' => 'Notulensi',
        'pengaturan' => 'Pengaturan',
    ];
@endphp

<div class="border-b border-line bg-surface">
    <div class="flex flex-wrap items-center gap-3 px-4 pt-3 sm:px-6">
        <p class="min-w-0 flex-1 truncate text-sm text-ink-soft">
            <template x-if="active">
                <span>
                    <span class="font-medium text-ink" x-text="active.name"></span>
                    <span class="text-ink-faint" x-show="active.durationSeconds > 0">
                        · <span x-text="formatDuration(active.durationSeconds)"></span>
                    </span>
                </span>
            </template>
            <template x-if="!active">
                <span class="text-ink-faint">Pilih atau unggah rekaman untuk memulai.</span>
            </template>
        </p>
    </div>

    <div class="flex gap-1 px-4 sm:px-6" role="tablist" aria-label="Bagian aplikasi">
        @foreach ($tabs as $key => $label)
            <button type="button"
                    id="tab-{{ $key }}"
                    role="tab"
                    aria-controls="panel-{{ $key }}"
                    :aria-selected="tab === '{{ $key }}'"
                    :tabindex="tab === '{{ $key }}' ? 0 : -1"
                    class="-mb-px border-b-2 px-3 py-2.5 text-sm transition-colors"
                    :class="tab === '{{ $key }}'
                        ? 'border-accent font-medium text-accent'
                        : 'border-transparent text-ink-faint hover:text-ink'"
                    @click="tab = '{{ $key }}'"
                    @keydown.right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                    @keydown.left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()">
                {{ $label }}
            </button>
        @endforeach
    </div>
</div>
