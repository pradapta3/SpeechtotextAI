<div class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end"
     role="status" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="card pointer-events-auto flex w-full max-w-sm items-start gap-3 border-l-4 p-3 shadow-lg"
             :class="{
                 'border-l-positive': toast.tone === 'positive',
                 'border-l-caution': toast.tone === 'caution',
                 'border-l-danger': toast.tone === 'danger',
                 'border-l-accent': toast.tone === 'info',
             }"
             x-transition.opacity.duration.200ms>
            <p class="flex-1 text-sm text-ink" x-text="toast.message"></p>

            <button type="button" class="rounded p-0.5 text-ink-faint hover:text-ink"
                    aria-label="Tutup notifikasi" @click="dismissToast(toast.id)">
                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                     stroke-width="1.6" aria-hidden="true">
                    <path d="M5 5l10 10M15 5L5 15" stroke-linecap="round"/>
                </svg>
            </button>
        </div>
    </template>
</div>
