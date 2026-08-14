import { api, ApiError, sleep } from './api.js';
import { decodeAudioFile, formatDuration, planSegments, renderSegment } from './audio/chunker.js';

const ACCEPTED_EXTENSIONS = ['mp3', 'mp4', 'm4a', 'wav', 'ogg', 'oga', 'webm', 'mpeg', 'mpga', 'flac', 'aac'];
const MAX_CHUNK_ATTEMPTS = 4;

let sequence = 0;

function nextKey() {
    sequence += 1;
    return `item-${sequence}`;
}

function isSupported(file) {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    return file.type.startsWith('audio/') || file.type.startsWith('video/') || ACCEPTED_EXTENSIONS.includes(extension);
}

function itemFromPayload(payload) {
    return {
        key: nextKey(),
        recordingId: payload.id,
        name: payload.name,
        sizeBytes: payload.size_bytes,
        durationSeconds: payload.duration_seconds,
        status: payload.status,
        statusLabel: payload.status_label,
        progress: payload.progress,
        message: '',
        error: payload.error,
        transcript: payload.transcript,
        minutes: payload.minutes,
        minutesHtml: payload.minutes_html ?? '',
        meeting: {
            meeting_title: payload.meeting?.title ?? '',
            meeting_date: payload.meeting?.date ?? '',
            meeting_attendees: payload.meeting?.attendees ?? '',
            meeting_context: payload.meeting?.context ?? '',
        },
    };
}

function itemFromFile(file) {
    return {
        key: nextKey(),
        recordingId: null,
        name: file.name,
        sizeBytes: file.size,
        durationSeconds: 0,
        status: 'pending',
        statusLabel: 'Menunggu',
        progress: 0,
        message: '',
        error: null,
        transcript: '',
        minutes: null,
        minutesHtml: '',
        meeting: {
            meeting_title: '',
            meeting_date: '',
            meeting_attendees: '',
            meeting_context: '',
        },
    };
}

export default function notulensi(initialState) {
    // Berkas asli disimpan di luar state Alpine: objek File tidak boleh
    // dibungkus proxy reaktif karena method-nya kehilangan konteks.
    const files = new Map();

    return {
        tab: 'transkrip',
        items: initialState.recordings.map(itemFromPayload),
        activeKey: null,
        processing: false,
        generating: false,
        dragging: false,
        controller: null,
        announcement: '',
        toasts: [],
        settings: initialState.settings,
        limits: initialState.limits,
        keyForm: { groq_key: '', anthropic_key: '' },
        savingSettings: false,

        init() {
            this.activeKey = this.items[0]?.key ?? null;
            this.$watch('items', () => {
                if (!this.items.some((item) => item.key === this.activeKey)) {
                    this.activeKey = this.items[0]?.key ?? null;
                }
            });
        },

        /* ---------------------------------------------------------- daftar */

        get active() {
            return this.items.find((item) => item.key === this.activeKey) ?? null;
        },

        get pendingItems() {
            return this.items.filter((item) => item.status === 'pending' && files.has(item.key));
        },

        get hasTranscript() {
            return Boolean(this.active?.transcript);
        },

        select(key) {
            this.activeKey = key;
            this.tab = 'transkrip';
        },

        addFiles(fileList) {
            const incoming = Array.from(fileList);
            const accepted = incoming.filter(isSupported);
            const room = this.limits.recordings_per_session - this.items.length;

            if (accepted.length < incoming.length) {
                this.toast(`${incoming.length - accepted.length} berkas dilewati (bukan audio).`, 'caution');
            }

            if (room <= 0) {
                this.toast('Batas jumlah rekaman tercapai. Hapus rekaman lama dulu.', 'caution');

                return;
            }

            accepted.slice(0, room).forEach((file) => {
                const item = itemFromFile(file);
                files.set(item.key, file);
                this.items.push(item);
            });

            this.activeKey ??= this.items[0]?.key ?? null;
        },

        async removeItem(item) {
            files.delete(item.key);
            this.items = this.items.filter((candidate) => candidate.key !== item.key);

            if (item.recordingId) {
                try {
                    await api.deleteRecording(item.recordingId);
                } catch (error) {
                    this.reportError(error);
                }
            }
        },

        async clearFinished() {
            const finished = this.items.filter((item) => ['completed', 'failed', 'cancelled'].includes(item.status));

            for (const item of finished) {
                await this.removeItem(item);
            }
        },

        /* ---------------------------------------------------- transkripsi */

        async transcribeAll() {
            if (this.processing) {
                return;
            }

            const queue = this.pendingItems;

            if (queue.length === 0) {
                this.toast('Tidak ada berkas baru yang menunggu diproses.', 'caution');

                return;
            }

            this.processing = true;
            this.controller = new AbortController();

            try {
                for (const item of queue) {
                    if (this.controller.signal.aborted) {
                        break;
                    }

                    await this.transcribeItem(item);
                }
            } finally {
                this.processing = false;
                this.controller = null;
            }
        },

        async transcribeItem(item) {
            const file = files.get(item.key);
            const signal = this.controller.signal;

            this.activeKey = item.key;
            item.status = 'processing';
            item.statusLabel = 'Diproses';
            item.error = null;
            item.transcript = '';
            this.setMessage(item, 'Membaca berkas audio…');

            try {
                const buffer = await decodeAudioFile(file);
                const chunkSeconds = this.settings.preferences.chunk_seconds;
                const segments = planSegments(buffer.duration, chunkSeconds);

                item.durationSeconds = buffer.duration;
                this.setMessage(
                    item,
                    `Durasi ${formatDuration(buffer.duration)} — dibagi menjadi ${segments.length} segmen.`,
                );

                const { recording } = await api.createRecording({
                    name: item.name,
                    size_bytes: item.sizeBytes,
                    duration_seconds: buffer.duration,
                    language: this.settings.preferences.language,
                    chunk_seconds: chunkSeconds,
                    total_chunks: segments.length,
                });

                item.recordingId = recording.id;

                for (const segment of segments) {
                    if (signal.aborted) {
                        throw new DOMException('Dibatalkan', 'AbortError');
                    }

                    this.setMessage(
                        item,
                        `Segmen ${segment.index + 1}/${segments.length} — ` +
                            `${formatDuration(segment.start)} s/d ${formatDuration(segment.end)}`,
                    );

                    const blob = await renderSegment(buffer, segment.start, segment.end);
                    const response = await this.sendChunkWithRetry(item, segment, blob, signal);

                    item.transcript = response.recording.transcript;
                    item.progress = response.recording.progress;
                    item.status = response.recording.status;
                    item.statusLabel = response.recording.status_label;
                }

                item.progress = 100;
                this.setMessage(item, 'Selesai.');
                this.toast(`Transkrip "${item.name}" selesai.`, 'positive');
            } catch (error) {
                await this.handleTranscriptionError(item, error);
            }
        },

        async sendChunkWithRetry(item, segment, blob, signal) {
            for (let attempt = 1; ; attempt += 1) {
                const form = new FormData();
                form.append('audio', blob, `segment-${segment.index}.wav`);
                form.append('index', String(segment.index));
                form.append('start_seconds', String(segment.start));
                form.append('end_seconds', String(segment.end));

                try {
                    return await api.sendChunk(item.recordingId, form, signal);
                } catch (error) {
                    const canRetry = error instanceof ApiError && error.isRateLimited && attempt < MAX_CHUNK_ATTEMPTS;

                    if (!canRetry) {
                        throw error;
                    }

                    const wait = error.retryAfter ?? 20;
                    this.setMessage(item, `Kena batas kuota Groq — menunggu ${wait} detik lalu melanjutkan…`);
                    await sleep(wait, signal);
                }
            }
        },

        async handleTranscriptionError(item, error) {
            const cancelled = error.name === 'AbortError';

            item.status = cancelled ? 'cancelled' : 'failed';
            item.statusLabel = cancelled ? 'Dibatalkan' : 'Gagal';
            item.error = cancelled ? 'Transkripsi dibatalkan.' : error.message;
            this.setMessage(item, item.error);

            if (!cancelled) {
                this.reportError(error);
            }

            if (item.recordingId) {
                try {
                    await api.updateRecording(item.recordingId, {
                        status: item.status,
                        error: item.error,
                    });
                } catch {
                    // Status lokal sudah benar; kegagalan sinkronisasi tidak menghalangi pengguna.
                }
            }
        },

        cancel() {
            this.controller?.abort();
            this.toast('Menghentikan transkripsi…', 'caution');
        },

        /* ------------------------------------------------------ notulensi */

        async generateMinutes() {
            const item = this.active;

            if (!item?.transcript) {
                this.toast('Belum ada transkrip untuk dibuatkan notulensi.', 'caution');

                return;
            }

            this.generating = true;

            try {
                const response = await api.generateMinutes(item.recordingId, item.meeting);
                item.minutes = response.recording.minutes;
                item.minutesHtml = response.minutes_html;
                this.toast('Notulensi berhasil dibuat.', 'positive');
            } catch (error) {
                this.reportError(error);
            } finally {
                this.generating = false;
            }
        },

        /* ----------------------------------------------------- pengaturan */

        async saveSettings() {
            this.savingSettings = true;

            try {
                const response = await api.saveSettings({
                    ...this.keyForm,
                    language: this.settings.preferences.language,
                    chunk_seconds: this.settings.preferences.chunk_seconds,
                });

                this.settings = { ...this.settings, ...response.settings };
                this.keyForm = { groq_key: '', anthropic_key: '' };
                this.toast('Pengaturan tersimpan.', 'positive');
            } catch (error) {
                this.reportError(error);
            } finally {
                this.savingSettings = false;
            }
        },

        async forgetKeys() {
            this.savingSettings = true;

            try {
                const response = await api.saveSettings({ forget_keys: true });
                this.settings = { ...this.settings, ...response.settings };
                this.toast('API key milik Anda dihapus dari session.', 'positive');
            } catch (error) {
                this.reportError(error);
            } finally {
                this.savingSettings = false;
            }
        },

        providerReady(provider) {
            const state = this.settings.providers[provider];

            return Boolean(state?.user_key || state?.server_key);
        },

        /* ---------------------------------------------------------- utils */

        setMessage(item, message) {
            item.message = message;
            this.announcement = `${item.name}: ${message}`;
        },

        async copy(text) {
            if (!text) {
                this.toast('Belum ada teks untuk disalin.', 'caution');

                return;
            }

            try {
                await navigator.clipboard.writeText(text);
                this.toast('Teks disalin ke papan klip.', 'positive');
            } catch {
                this.toast('Browser menolak akses papan klip.', 'danger');
            }
        },

        download(text, suffix) {
            if (!text) {
                this.toast('Belum ada teks untuk diunduh.', 'caution');

                return;
            }

            const base = (this.active?.name ?? 'notulensi').replace(/\.[^.]+$/, '');
            const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }));
            const link = Object.assign(document.createElement('a'), { href: url, download: `${base}${suffix}` });

            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        },

        formatSize(bytes) {
            if (bytes >= 1024 * 1024) {
                return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
            }

            return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        },

        formatDuration,

        reportError(error) {
            if (error.name === 'AbortError') {
                return;
            }

            const details = error instanceof ApiError ? Object.values(error.errors).flat() : [];

            this.toast(details[0] ?? error.message, 'danger');
        },

        toast(message, tone = 'info') {
            const id = nextKey();
            this.toasts.push({ id, message, tone });
            this.announcement = message;

            setTimeout(() => {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            }, 6000);
        },

        dismissToast(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    };
}
