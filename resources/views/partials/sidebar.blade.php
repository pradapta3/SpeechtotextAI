<aside class="flex min-w-0 flex-col border-b border-line bg-surface lg:border-r lg:border-b-0"
       aria-label="Daftar rekaman">
    <div class="border-b border-line p-4">
        <div @dragover.prevent="dragging = true"
             @dragleave.prevent="dragging = false"
             @drop.prevent="dragging = false; addFiles($event.dataTransfer.files)">
            <button type="button"
                    class="flex w-full flex-col items-center gap-1 rounded-xl border-2 border-dashed px-4 py-6
                           text-center transition-colors"
                    :class="dragging
                        ? 'border-accent bg-accent-soft'
                        : 'border-line-strong hover:border-accent hover:bg-accent-soft/50'"
                    @click="$refs.fileInput.click()">
                <span class="text-sm font-medium text-ink">Unggah berkas audio</span>
                <span class="text-xs text-ink-faint">Klik untuk memilih, atau seret berkas ke sini</span>
                <span class="mt-1 text-[11px] text-ink-faint">MP3 · WAV · M4A · MP4 · OGG · FLAC · WEBM</span>
            </button>
        </div>

        <input type="file" x-ref="fileInput" class="sr-only" multiple
               accept="audio/*,video/mp4,video/webm,.mp3,.m4a,.wav,.ogg,.flac,.aac,.mp4,.webm"
               @change="addFiles($event.target.files); $event.target.value = ''">
    </div>

    <div class="flex flex-wrap gap-2 border-b border-line p-3">
        <button type="button" class="btn btn-primary flex-1"
                :disabled="processing || pendingItems.length === 0"
                @click="transcribeAll()">
            <span x-text="processing ? 'Memproses…' : 'Mulai transkripsi'"></span>
        </button>

        <button type="button" class="btn btn-danger btn-sm" x-show="processing" @click="cancel()">
            Batalkan
        </button>

        <button type="button" class="btn btn-ghost btn-sm"
                :disabled="processing"
                @click="clearFinished()"
                title="Hapus rekaman yang sudah selesai, gagal, atau dibatalkan">
            Bersihkan
        </button>
    </div>

    <ul class="scroll-area max-h-[40vh] min-h-0 flex-1 space-y-1 overflow-y-auto p-2 lg:max-h-none"
        role="list">
        <template x-if="items.length === 0">
            <li class="px-3 py-8 text-center text-sm text-ink-faint">
                Belum ada rekaman. Unggah berkas audio untuk memulai.
            </li>
        </template>

        <template x-for="item in items" :key="item.key">
            <li>
                <div class="w-full rounded-lg border p-3 text-left transition-colors"
                     :class="item.key === activeKey
                        ? 'border-accent bg-accent-soft'
                        : 'border-transparent hover:bg-sunken'">
                    <div class="flex items-start gap-2">
                        <button type="button" class="min-w-0 flex-1 text-left"
                                :aria-current="item.key === activeKey ? 'true' : 'false'"
                                @click="select(item.key)">
                            <span class="block truncate text-sm font-medium text-ink" x-text="item.name"></span>
                            <span class="mt-0.5 block text-xs text-ink-faint">
                                <span x-text="formatSize(item.sizeBytes)"></span>
                                <span aria-hidden="true"> · </span>
                                <span x-text="item.statusLabel"></span>
                            </span>
                        </button>

                        <button type="button"
                                class="rounded-md p-1 text-ink-faint transition-colors hover:bg-danger-soft hover:text-danger"
                                :aria-label="`Hapus ${item.name}`"
                                @click="removeItem(item)">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                 stroke-width="1.6" aria-hidden="true">
                                <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>

                    <div x-show="item.status === 'processing'" class="mt-2">
                        <div class="h-1 overflow-hidden rounded-full bg-sunken">
                            <div class="h-full rounded-full bg-accent transition-[width] duration-300"
                                 :style="`width: ${item.progress}%`"></div>
                        </div>
                        <p class="mt-1 truncate text-[11px] text-ink-faint" x-text="item.message"></p>
                    </div>

                    <p x-show="item.status === 'failed'" class="mt-2 text-[11px] text-danger" x-text="item.error"></p>
                </div>
            </li>
        </template>
    </ul>
</aside>
