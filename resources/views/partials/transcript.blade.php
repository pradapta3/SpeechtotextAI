<section id="panel-transkrip" role="tabpanel" aria-labelledby="tab-transkrip"
         x-show="tab === 'transkrip'" class="flex h-full flex-col gap-3 p-4 sm:p-6">

    {{-- Kartu progres: menjawab "sudah sampai segmen berapa, sudah berapa lama, sisa berapa lama". --}}
    <template x-if="active && active.status === 'processing' && runStats">
        <div class="card p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-medium text-ink">
                    Segmen <span x-text="runStats.done"></span> dari <span x-text="runStats.total"></span> selesai
                </p>
                <p class="text-sm tabular-nums text-ink-soft"><span x-text="active.progress"></span>%</p>
            </div>

            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-sunken"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="active.progress">
                <div class="h-full rounded-full bg-accent transition-[width] duration-300"
                     :style="`width: ${active.progress}%`"></div>
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-ink-faint">Waktu berjalan</dt>
                    <dd class="font-medium tabular-nums text-ink" x-text="formatClock(runStats.elapsed)"></dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Perkiraan sisa</dt>
                    <dd class="font-medium tabular-nums text-ink"
                        x-text="runStats.eta === null ? 'menghitung…' : formatClock(runStats.eta)"></dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Rata-rata/segmen</dt>
                    <dd class="font-medium tabular-nums text-ink"
                        x-text="runStats.average === null ? '—' : `${runStats.average.toFixed(1)} dtk`"></dd>
                </div>
                <div>
                    <dt class="text-ink-faint">Kata terkumpul</dt>
                    <dd class="font-medium tabular-nums text-ink" x-text="formatNumber(active.word_count)"></dd>
                </div>
            </dl>

            <p class="mt-3 border-t border-line pt-2 text-xs text-ink-soft" x-text="active.message"></p>
        </div>
    </template>

    <div class="flex flex-wrap items-center gap-2">
        <h2 class="mr-auto font-serif text-base font-semibold text-ink">Transkrip</h2>

        {{-- Tampilan per segmen memperlihatkan penanda waktu, berguna untuk mencocokkan dengan audio asli. --}}
        <div class="flex items-center rounded-lg border border-line p-0.5" role="group"
             aria-label="Tampilan transkrip" x-show="hasTranscript">
            @foreach (['gabungan' => 'Gabungan', 'segmen' => 'Per segmen'] as $value => $label)
                <button type="button" class="rounded-md px-2 py-1 text-xs transition-colors"
                        :class="transcriptView === '{{ $value }}'
                            ? 'bg-accent-soft font-medium text-accent'
                            : 'text-ink-faint hover:text-ink'"
                        :aria-pressed="transcriptView === '{{ $value }}'"
                        @click="transcriptView = '{{ $value }}'">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button type="button" class="btn btn-ghost btn-sm" :disabled="!hasTranscript"
                @click="copy(active?.transcript)">
            Salin
        </button>
        <button type="button" class="btn btn-ghost btn-sm" :disabled="!hasTranscript"
                @click="download(active?.transcript, '-transkrip.txt')">
            Unduh .txt
        </button>
    </div>

    {{-- Statistik hasil akhir. --}}
    <template x-if="hasTranscript">
        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Jumlah kata</dt>
                <dd class="font-medium tabular-nums text-ink" x-text="formatNumber(active.word_count)"></dd>
            </div>
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Karakter</dt>
                <dd class="font-medium tabular-nums text-ink" x-text="formatNumber(active.transcript.length)"></dd>
            </div>
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Segmen</dt>
                <dd class="font-medium tabular-nums text-ink"
                    x-text="`${active.completed_chunks} × ${active.chunk_seconds ?? settings.preferences.chunk_seconds} dtk`"></dd>
            </div>
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Selesai</dt>
                <dd class="truncate font-medium text-ink" x-text="formatDateTime(active.transcribed_at)"></dd>
            </div>
        </dl>
    </template>

    <div class="scroll-area card flex min-h-64 flex-1 flex-col overflow-y-auto p-4">
        <template x-if="hasTranscript && transcriptView === 'gabungan'">
            <p class="font-mono text-[13px] leading-relaxed whitespace-pre-wrap text-ink-soft"
               x-text="active.transcript"></p>
        </template>

        <template x-if="hasTranscript && transcriptView === 'segmen'">
            <ol class="w-full space-y-3">
                <template x-for="segment in active.segments" :key="segment.index">
                    <li class="grid gap-1 border-b border-line pb-3 last:border-0 sm:grid-cols-[7rem_minmax(0,1fr)]">
                        <p class="font-mono text-[11px] text-ink-faint tabular-nums">
                            <span x-text="formatClock(segment.start)"></span>–<span x-text="formatClock(segment.end)"></span>
                        </p>
                        <p class="font-mono text-[13px] leading-relaxed text-ink-soft" x-text="segment.text || '—'"></p>
                    </li>
                </template>
            </ol>
        </template>

        <template x-if="!hasTranscript && active?.status === 'failed'">
            <div class="m-auto max-w-md text-center">
                <p class="font-serif text-base font-semibold text-danger">Transkripsi gagal</p>
                <p class="mt-2 text-sm text-ink-soft" x-text="active.error"></p>
            </div>
        </template>

        {{-- Selama segmen pertama belum kembali, daftar periksa tidak relevan lagi. --}}
        <template x-if="!hasTranscript && active?.status === 'processing'">
            <div class="m-auto max-w-sm text-center">
                <p class="text-sm font-medium text-ink">Menunggu hasil segmen pertama…</p>
                <p class="mt-1 text-xs text-ink-faint">
                    Teks akan muncul di sini segmen demi segmen begitu Groq membalas.
                </p>
            </div>
        </template>

        {{-- Kondisi kosong dipakai sebagai daftar periksa: apa yang sudah beres, apa yang kurang. --}}
        <template x-if="!hasTranscript && !['failed', 'processing'].includes(active?.status)">
            <div class="m-auto w-full max-w-md py-4">
                <h3 class="text-center font-serif text-base font-semibold text-ink">Langkah yang perlu dilalui</h3>

                <ol class="mt-4 space-y-2.5">
                    <template x-for="(step, index) in readiness" :key="index">
                        <li class="flex gap-3 rounded-lg border border-line px-3 py-2.5"
                            :class="step.done ? 'bg-positive/8' : 'bg-surface'">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                  :class="step.done ? 'bg-positive text-white' : 'bg-sunken text-ink-faint'"
                                  aria-hidden="true"
                                  x-text="step.done ? '✓' : index + 1"></span>
                            <span class="min-w-0">
                                <span class="block text-sm text-ink" x-text="step.label"></span>
                                <span class="block text-xs text-ink-faint" x-show="!step.done" x-text="step.hint"></span>
                            </span>
                        </li>
                    </template>
                </ol>

                <p class="mt-4 text-center text-xs text-ink-faint">
                    Audio dipotong per <span x-text="settings.preferences.chunk_seconds"></span> detik di browser
                    (±<span x-text="segmentSizeMb"></span> MB per segmen) lalu dikirim berurutan lewat server —
                    tidak ada batas durasi rekaman.
                </p>
            </div>
        </template>
    </div>
</section>
