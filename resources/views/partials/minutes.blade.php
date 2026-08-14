<section id="panel-notulensi" role="tabpanel" aria-labelledby="tab-notulensi"
         x-show="tab === 'notulensi'" class="flex h-full flex-col gap-4 p-4 sm:p-6">

    <template x-if="active">
        <div class="card p-4">
            <h2 class="font-serif text-base font-semibold text-ink">Informasi rapat</h2>
            <p class="mt-1 text-xs text-ink-faint">
                Opsional, tapi membuat notulensi jauh lebih rapi dan spesifik.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="meeting-title">Judul rapat</label>
                    <input id="meeting-title" type="text" class="field"
                           placeholder="cth. Rapat Koordinasi Proyek Q3"
                           x-model="active.meeting.meeting_title">
                </div>

                <div>
                    <label class="label" for="meeting-date">Tanggal</label>
                    <input id="meeting-date" type="date" class="field" x-model="active.meeting.meeting_date">
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="meeting-attendees">Peserta</label>
                    <input id="meeting-attendees" type="text" class="field"
                           placeholder="cth. Budi, Sari, Pak Joko"
                           x-model="active.meeting.meeting_attendees">
                </div>

                <div class="sm:col-span-2">
                    <label class="label" for="meeting-context">Konteks tambahan</label>
                    <input id="meeting-context" type="text" class="field"
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
            <span x-text="generating ? 'Menyusun notulensi…' : 'Buat notulensi'"></span>
        </button>

        <button type="button" class="btn btn-ghost btn-sm" :disabled="!active?.minutes"
                @click="copy(active?.minutes)">
            Salin Markdown
        </button>
        <button type="button" class="btn btn-ghost btn-sm" :disabled="!active?.minutes"
                @click="download(active?.minutes, '-notulensi.md')">
            Unduh .md
        </button>
    </div>

    <div class="scroll-area card flex min-h-64 flex-1 flex-col overflow-y-auto p-5">
        <template x-if="active?.minutesHtml">
            <div class="minutes-body" x-html="active.minutesHtml"></div>
        </template>

        <template x-if="!active?.minutesHtml">
            <div class="m-auto max-w-md py-8 text-center">
                <h3 class="font-serif text-base font-semibold text-ink">Notulensi belum dibuat</h3>
                <p class="mt-2 text-sm text-ink-soft">
                    Setelah transkrip selesai, isi informasi rapat di atas lalu klik
                    <em>Buat notulensi</em>. Hasilnya berupa agenda, jalannya rapat, keputusan, dan
                    tabel tindak lanjut.
                </p>
                <p class="mt-3 text-xs text-caution" x-show="!providerReady('anthropic')">
                    API key Anthropic belum diatur — fitur ini akan menolak permintaan.
                </p>
            </div>
        </template>
    </div>
</section>
