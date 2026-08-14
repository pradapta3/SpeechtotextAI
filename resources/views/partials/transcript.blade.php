<section id="panel-transkrip" role="tabpanel" aria-labelledby="tab-transkrip"
         x-show="tab === 'transkrip'" class="flex h-full flex-col gap-3 p-4 sm:p-6">

    <template x-if="active && active.status === 'processing'">
        <div class="card p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-ink">Sedang mentranskripsi</p>
                <p class="text-sm tabular-nums text-ink-soft"><span x-text="active.progress"></span>%</p>
            </div>

            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-sunken"
                 role="progressbar" aria-valuemin="0" aria-valuemax="100"
                 :aria-valuenow="active.progress">
                <div class="h-full rounded-full bg-accent transition-[width] duration-300"
                     :style="`width: ${active.progress}%`"></div>
            </div>

            <p class="mt-2 text-xs text-ink-soft" x-text="active.message"></p>
        </div>
    </template>

    <div class="flex flex-wrap items-center gap-2">
        <h2 class="mr-auto font-serif text-base font-semibold text-ink">Transkrip</h2>

        <button type="button" class="btn btn-ghost btn-sm" :disabled="!hasTranscript"
                @click="copy(active?.transcript)">
            Salin
        </button>
        <button type="button" class="btn btn-ghost btn-sm" :disabled="!hasTranscript"
                @click="download(active?.transcript, '-transkrip.txt')">
            Unduh .txt
        </button>
    </div>

    <div class="scroll-area card flex min-h-64 flex-1 flex-col overflow-y-auto p-4">
        <template x-if="active?.transcript">
            <p class="font-mono text-[13px] leading-relaxed whitespace-pre-wrap text-ink-soft"
               x-text="active.transcript"></p>
        </template>

        <template x-if="active && !active.transcript && active.status === 'failed'">
            <p class="text-sm text-danger" x-text="active.error"></p>
        </template>

        <template x-if="!active?.transcript && active?.status !== 'failed'">
            <div class="m-auto max-w-md py-8 text-center">
                <h3 class="font-serif text-base font-semibold text-ink">Belum ada transkrip</h3>
                <ol class="mt-3 space-y-1.5 text-left text-sm text-ink-soft">
                    <li>1. Pastikan API key transkripsi sudah terisi di tab <em>Pengaturan</em>.</li>
                    <li>2. Unggah satu atau beberapa berkas audio rapat.</li>
                    <li>3. Klik <em>Mulai transkripsi</em> lalu tunggu prosesnya.</li>
                    <li>4. Buka tab <em>Notulensi</em> untuk merapikan hasilnya.</li>
                </ol>
                <p class="mt-4 text-xs text-ink-faint">
                    Audio dipotong per segmen di browser, di-resample ke 16 kHz mono, lalu dikirim
                    berurutan — tidak ada batas durasi rekaman.
                </p>
            </div>
        </template>
    </div>
</section>
