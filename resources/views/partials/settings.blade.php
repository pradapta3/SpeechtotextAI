<section id="panel-pengaturan" role="tabpanel" aria-labelledby="tab-pengaturan"
         x-show="tab === 'pengaturan'" class="scroll-area h-full overflow-y-auto p-4 sm:p-6">
    <div class="mx-auto max-w-2xl space-y-5">

        <div class="card p-4">
            <h2 class="font-serif text-base font-semibold text-ink">Status layanan</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach (['groq' => 'Transkripsi (Groq Whisper)', 'anthropic' => 'Notulensi (Claude)'] as $provider => $label)
                    <div class="flex items-center justify-between gap-3 rounded-lg bg-sunken px-3 py-2">
                        <dt class="text-ink-soft">{{ $label }}</dt>
                        <dd class="flex items-center gap-1.5 text-xs font-medium"
                            :class="providerReady('{{ $provider }}') ? 'text-positive' : 'text-caution'">
                            <span class="size-1.5 rounded-full"
                                  :class="providerReady('{{ $provider }}') ? 'bg-positive' : 'bg-caution'"
                                  aria-hidden="true"></span>
                            <span x-text="settings.providers['{{ $provider }}'].user_key
                                ? `Key Anda (${settings.providers['{{ $provider }}'].masked})`
                                : (settings.providers['{{ $provider }}'].server_key ? 'Key server' : 'Belum diatur')"></span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <template x-if="settings.allow_user_keys">
            <div class="card p-4">
                <h2 class="font-serif text-base font-semibold text-ink">API key Anda</h2>
                <p class="mt-1 text-xs text-ink-soft">
                    Key disimpan terenkripsi di session server dan dipakai untuk menggantikan key bawaan
                    server. Key tidak pernah disimpan di browser dan tidak ditampilkan ulang.
                    Kosongkan kolom untuk mempertahankan key yang sudah tersimpan.
                </p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="label" for="groq-key">Groq API key</label>
                        <input id="groq-key" type="password" class="field font-mono" autocomplete="off"
                               placeholder="gsk_…" x-model="keyForm.groq_key">
                        <p class="mt-1 text-xs text-ink-faint">
                            Buat gratis di
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
                            Hanya dipakai untuk pembuatan notulensi.
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
                </div>

                <div>
                    <label class="label" for="chunk-seconds">Durasi segmen (detik)</label>
                    <input id="chunk-seconds" type="number" class="field" min="15" step="15"
                           :max="limits.max_chunk_seconds"
                           x-model.number="settings.preferences.chunk_seconds">
                    <p class="mt-1 text-xs text-ink-faint">
                        Segmen lebih panjang berarti lebih sedikit permintaan API. Audio dikirim sebagai
                        WAV 16 kHz mono (±1,9 MB per menit); maksimum
                        <span x-text="limits.max_chunk_seconds"></span> detik mengikuti batas unggahan server.
                    </p>
                </div>
            </div>

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
            <ul class="mt-3 space-y-2">
                <li>
                    <strong class="text-ink">Pemotongan di browser.</strong>
                    Berkas audio didekode lalu dipotong dan di-resample menjadi WAV 16 kHz mono. Tidak
                    ada batas ukuran berkas karena yang diunggah hanya potongan kecil.
                </li>
                <li>
                    <strong class="text-ink">Kunci API di server.</strong>
                    Browser tidak pernah memegang API key; setiap segmen dikirim ke aplikasi ini, lalu
                    aplikasi yang meneruskannya ke Groq.
                </li>
                <li>
                    <strong class="text-ink">Kena batas kuota?</strong>
                    Aplikasi otomatis menunggu sesuai header <code>Retry-After</code> lalu melanjutkan
                    dari segmen yang sama. Biarkan tab tetap terbuka.
                </li>
                <li>
                    <strong class="text-ink">Penyimpanan.</strong>
                    Transkrip dan notulensi tersimpan di basis data aplikasi dan hanya bisa dibuka dari
                    session browser yang membuatnya.
                </li>
            </ul>
        </div>
    </div>
</section>
