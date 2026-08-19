<section id="panel-notulensi" role="tabpanel" aria-labelledby="tab-notulensi"
         x-show="tab === 'notulensi'" class="flex h-full flex-col gap-4 p-4 sm:p-6">

    <template x-if="active">
        <div class="card p-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-serif text-base font-semibold text-ink">Informasi rapat</h2>
                <p class="text-xs text-ink-faint">
                    Opsional — tapi judul, tanggal, dan peserta membuat notulensi jauh lebih spesifik.
                </p>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="meeting-title">Judul rapat</label>
                    <input id="meeting-title" type="text" class="field" maxlength="200"
                           placeholder="cth. Rapat Koordinasi Proyek Q3"
                           x-model="active.meeting.meeting_title">
                </div>

                <div>
                    <label class="label" for="meeting-date">Tanggal</label>
                    <input id="meeting-date" type="date" class="field" x-model="active.meeting.meeting_date">
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="meeting-attendees">Peserta</label>
                    <input id="meeting-attendees" type="text" class="field" maxlength="500"
                           placeholder="cth. Budi, Sari, Pak Joko"
                           x-model="active.meeting.meeting_attendees">
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="meeting-context">Konteks tambahan</label>
                    <input id="meeting-context" type="text" class="field" maxlength="1000"
                           placeholder="cth. Evaluasi kinerja kuartal II divisi pemasaran"
                           x-model="active.meeting.meeting_context">
                </div>
            </div>
        </div>
    </template>

    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="btn btn-primary"
                :disabled="generating || !hasTranscript"
                @click="generateMinutes()">
            <span x-show="!generating" x-text="active?.has_minutes ? 'Buat ulang notulensi' : 'Buat notulensi'"></span>
            <span x-show="generating">Menyusun notulensi…</span>
        </button>

        <button type="button" class="btn btn-ghost btn-sm" :disabled="!active?.minutes"
                @click="copy(active?.minutes)">
            Salin Markdown
        </button>
        <button type="button" class="btn btn-ghost btn-sm" :disabled="!active?.minutes"
                @click="download(active?.minutes, '-notulensi.md')">
            Unduh .md
        </button>

        {{-- x-show hanya menyembunyikan elemen; binding anaknya tetap dievaluasi,
             jadi akses ke `active` di sini harus tetap aman ketika belum ada rekaman. --}}
        <p class="ml-auto text-xs text-ink-faint" x-show="hasTranscript && !generating">
            Sumber: <span x-text="formatNumber(active?.word_count)"></span> kata transkrip ·
            model <span class="font-mono" x-text="models.minutes"></span>
        </p>
    </div>

    {{-- Jejak pembuatan: model apa, kapan, dari transkrip sepanjang apa. --}}
    <template x-if="active?.has_minutes">
        <dl class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Dibuat</dt>
                <dd class="truncate font-medium text-ink" x-text="formatDateTime(active.minutes_generated_at)"></dd>
            </div>
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Model</dt>
                <dd class="truncate font-mono text-xs font-medium text-ink" x-text="active.minutes_model ?? '—'"></dd>
            </div>
            <div class="card px-3 py-2">
                <dt class="text-[11px] text-ink-faint">Panjang notulensi</dt>
                <dd class="font-medium tabular-nums text-ink"
                    x-text="`${formatNumber(active.minutes?.length ?? 0)} karakter`"></dd>
            </div>
        </dl>
    </template>

    <div class="scroll-area card flex min-h-64 flex-1 flex-col overflow-y-auto p-5">
        <template x-if="active?.minutes_html">
            <div class="minutes-body" x-html="active.minutes_html"></div>
        </template>

        <template x-if="!active?.minutes_html">
            <div class="m-auto max-w-md py-8 text-center">
                <h3 class="font-serif text-base font-semibold text-ink">Notulensi belum dibuat</h3>
                <p class="mt-2 text-sm text-ink-soft">
                    Transkrip mentah akan dirapikan menjadi dokumen dengan bagian berikut:
                </p>

                <ul class="mx-auto mt-3 grid max-w-xs gap-1.5 text-left text-sm text-ink-soft">
                    @foreach ([
                        'Informasi rapat' => 'judul, tanggal, peserta',
                        'Agenda' => 'topik yang dibahas',
                        'Jalannya rapat' => 'ringkasan diskusi',
                        'Keputusan' => 'hasil yang disepakati',
                        'Tindak lanjut' => 'tabel tindakan, penanggung jawab, tenggat',
                        'Kesimpulan' => 'penutup singkat',
                    ] as $section => $description)
                        <li class="flex gap-2">
                            <span class="text-accent" aria-hidden="true">•</span>
                            <span><strong class="text-ink">{{ $section }}</strong> — {{ $description }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 text-xs text-caution" x-show="!providerReady('anthropic')">
                    Anthropic API key belum diatur — isi di tab Pengaturan sebelum membuat notulensi.
                </p>
                <p class="mt-4 text-xs text-ink-faint" x-show="providerReady('anthropic') && !hasTranscript">
                    Selesaikan transkripsi terlebih dahulu.
                </p>
            </div>
        </template>
    </div>
</section>
