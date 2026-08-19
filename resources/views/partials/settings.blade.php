<section id="panel-pengaturan" role="tabpanel" aria-labelledby="tab-pengaturan"
         x-show="tab === 'pengaturan'" class="scroll-area h-full overflow-y-auto p-4 sm:p-6">
    <div class="mx-auto max-w-2xl space-y-5">

        @php
            $providers = [
                'groq' => ['label' => 'Transkripsi', 'service' => 'Groq Whisper', 'model' => 'transcription'],
                'anthropic' => ['label' => 'Notulensi', 'service' => 'Anthropic Claude', 'model' => 'minutes'],
            ];
        @endphp

        <div class="card p-4">
            <h2 class="font-serif text-base font-semibold text-ink">Status layanan</h2>
            <p class="mt-1 text-xs text-ink-faint">
                Permintaan ke kedua layanan dikirim dari server aplikasi, bukan dari browser.
            </p>

            <div class="mt-3 space-y-2">
                @foreach ($providers as $provider => $meta)
                    <div class="rounded-lg border border-line px-3 py-2.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-medium text-ink">
                                {{ $meta['label'] }}
                                <span class="font-normal text-ink-faint">· {{ $meta['service'] }}</span>
                            </p>

                            <p class="flex items-center gap-1.5 text-xs font-medium"
                               :class="providerReady('{{ $provider }}') ? 'text-positive' : 'text-caution'">
                                <span class="size-1.5 rounded-full"
                                      :class="providerReady('{{ $provider }}') ? 'bg-positive' : 'bg-caution'"
                                      aria-hidden="true"></span>
                                <span x-text="providerSource('{{ $provider }}')"></span>
                            </p>
                        </div>

                        <p class="mt-1 font-mono text-[11px] text-ink-faint">
                            <span x-text="models.{{ $meta['model'] }}"></span>
                            @if ($provider === 'anthropic')
                                <span class="font-sans">· tingkat usaha</span> <span x-text="models.effort"></span>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <template x-if="settings.allow_user_keys">
            <div class="card p-4">
                <h2 class="font-serif text-base font-semibold text-ink">API key Anda</h2>
                <p class="mt-1 text-xs text-ink-soft">
                    Key dienkripsi dengan kunci aplikasi dan disimpan di session server — tidak pernah masuk
                    ke penyimpanan browser dan tidak pernah ditampilkan ulang. Key ini menimpa key bawaan
                    server. Kosongkan kolom untuk mempertahankan key yang sudah tersimpan.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="label" for="groq-key">Groq API key</label>
                        <input id="groq-key" type="password" class="field font-mono" autocomplete="off"
                               placeholder="gsk_…" x-model="keyForm.groq_key">
                        <p class="mt-1 text-xs text-ink-faint">
                            Gratis tanpa kartu kredit di
                            <a class="text-accent underline" href="https://console.groq.com/keys"
                               target="_blank" rel="noopener">console.groq.com/keys</a>.
                        </p>
                    </div>

                    <div>
                        <label class="label" for="anthropic-key">Anthropic API key</label>
                        <input id="anthropic-key" type="password" class="field font-mono" autocomplete="off"
                               placeholder="sk-ant-…" x-model="keyForm.anthropic_key">
                        <p class="mt-1 text-xs text-ink-faint">
                            Dibuat di
                            <a class="text-accent underline" href="https://console.anthropic.com"
                               target="_blank" rel="noopener">console.anthropic.com</a>.
                            Hanya dipakai saat membuat notulensi.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="card p-4">
            <h2 class="font-serif text-base font-semibold text-ink">Preferensi transkripsi</h2>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="language">Bahasa audio</label>
                    <select id="language" class="field" x-model="settings.preferences.language">
                        <template x-for="(label, code) in settings.languages" :key="code">
                            <option :value="code" x-text="label"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-ink-faint">
                        Menyebut bahasa secara eksplisit lebih akurat daripada deteksi otomatis.
                    </p>
                </div>

                <div>
                    <label class="label" for="chunk-seconds">Durasi segmen (detik)</label>
                    <input id="chunk-seconds" type="number" class="field" min="15" step="15"
                           :max="limits.max_chunk_seconds"
                           x-model.number="settings.preferences.chunk_seconds">
                    <p class="mt-1 text-xs text-ink-faint">
                        Maksimum <span x-text="limits.max_chunk_seconds"></span> detik pada server ini
                        (batas unggah <span x-text="limits.max_chunk_megabytes"></span> MB).
                    </p>
                </div>
            </div>

            {{-- Dampak langsung dari angka di atas, dihitung ulang saat diubah. --}}
            <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <div class="rounded-lg bg-sunken px-3 py-2">
                    <dt class="text-[11px] text-ink-faint">Ukuran per segmen</dt>
                    <dd class="font-medium tabular-nums text-ink"><span x-text="segmentSizeMb"></span> MB</dd>
                </div>
                <div class="rounded-lg bg-sunken px-3 py-2">
                    <dt class="text-[11px] text-ink-faint">Permintaan per jam audio</dt>
                    <dd class="font-medium tabular-nums text-ink" x-text="requestsPerHour"></dd>
                </div>
                <div class="rounded-lg bg-sunken px-3 py-2">
                    <dt class="text-[11px] text-ink-faint">Format kirim</dt>
                    <dd class="font-medium text-ink">WAV 16 kHz mono</dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" :disabled="savingSettings"
                        @click="saveSettings()">
                    <span x-text="savingSettings ? 'Menyimpan…' : 'Simpan pengaturan'"></span>
                </button>

                <button type="button" class="btn btn-ghost btn-sm"
                        x-show="settings.providers.groq.user_key || settings.providers.anthropic.user_key"
                        :disabled="savingSettings"
                        @click="forgetKeys()">
                    Hapus key tersimpan
                </button>
            </div>
        </div>

        <div class="card p-4 text-sm text-ink-soft">
            <h2 class="font-serif text-base font-semibold text-ink">Cara kerja</h2>
            <ol class="mt-3 space-y-2.5">
                <li class="flex gap-3">
                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-sunken
                                 text-[11px] font-semibold text-ink-soft" aria-hidden="true">1</span>
                    <span>
                        <strong class="text-ink">Dipotong di browser.</strong>
                        Audio didekode, di-<em>downmix</em>, dan di-<em>resample</em> menjadi WAV 16 kHz mono.
                        Berkas asli tidak pernah diunggah utuh, jadi ukuran berkas tidak dibatasi.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-sunken
                                 text-[11px] font-semibold text-ink-soft" aria-hidden="true">2</span>
                    <span>
                        <strong class="text-ink">Diteruskan server.</strong>
                        Browser tidak pernah memegang API key; tiap segmen dikirim ke aplikasi ini yang
                        kemudian memanggil Groq.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-sunken
                                 text-[11px] font-semibold text-ink-soft" aria-hidden="true">3</span>
                    <span>
                        <strong class="text-ink">Tahan rate limit.</strong>
                        Saat kuota Groq penuh, aplikasi menunggu sesuai <code>Retry-After</code> lalu mengulang
                        segmen yang sama — bukan mengulang dari awal. Biarkan tab tetap terbuka.
                    </span>
                </li>
                <li class="flex gap-3">
                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-sunken
                                 text-[11px] font-semibold text-ink-soft" aria-hidden="true">4</span>
                    <span>
                        <strong class="text-ink">Tersimpan per session.</strong>
                        Transkrip dan notulensi disimpan di basis data aplikasi dan hanya bisa dibuka dari
                        browser yang membuatnya. Maksimum
                        <span x-text="limits.recordings_per_session"></span> rekaman.
                    </span>
                </li>
            </ol>
        </div>
    </div>
</section>
