import { api, ApiError, sleep } from './api.js';
import { decodeAudioFile, formatDuration, planSegments, renderSegment } from './audio/chunker.js';

const ACCEPTED_EXTENSIONS = ['mp3', 'mp4', 'm4a', 'wav', 'ogg', 'oga', 'webm', 'mpeg', 'mpga', 'flac', 'aac'];
const MAX_CHUNK_ATTEMPTS = 4;

/** Field payload detail yang disalin apa adanya ke item di sisi klien. */
const DETAIL_FIELDS = [
    'status',
    'status_label',
    'progress',
    'chunk_seconds',
    'total_chunks',
    'completed_chunks',
    'has_transcript',
    'has_minutes',
    'error',
    'transcribed_at',
    'duration_seconds',
    'language_label',
    'segments',
    'transcript',
    'word_count',
    'minutes',
    'minutes_html',
    'minutes_model',
    'minutes_generated_at',
];

let sequence = 0;

function nextKey() {
    sequence += 1;
    return `item-${sequence}`;
}

function isSupported(file) {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    return file.type.startsWith('audio/') || file.type.startsWith('video/') || ACCEPTED_EXTENSIONS.includes(extension);
}

function baseItem() {
    return {
        key: nextKey(),
        recordingId: null,
        loaded: false,
        message: '',
        run: null,
        segments: [],
        transcript: '',
        word_count: 0,
        minutes: null,
        minutes_html: '',
        minutes_model: null,
        minutes_generated_at: null,
        meeting: {
            meeting_title: '',
            meeting_date: '',
            meeting_attendees: '',
            meeting_context: '',
        },
    };
}

function itemFromSummary(payload) {
    return {
        ...baseItem(),
        recordingId: payload.id,
        name: payload.name,
        sizeBytes: payload.size_bytes,
        duration_seconds: payload.duration_seconds,
        language_label: payload.language_label,
        status: payload.status,
        status_label: payload.status_label,
        progress: payload.progress,
        chunk_seconds: payload.chunk_seconds,
        total_chunks: payload.total_chunks,
        completed_chunks: payload.completed_chunks,
        has_transcript: payload.has_transcript,
        has_minutes: payload.has_minutes,
        error: payload.error,
        created_at: payload.created_at,
        transcribed_at: payload.transcribed_at,
    };
}

function itemFromFile(file, language) {
    return {
        ...baseItem(),
        name: file.name,
        sizeBytes: file.size,
        duration_seconds: 0,
        language_label: language,
        status: 'pending',
        status_label: 'Menunggu',
        progress: 0,
        total_chunks: 0,
        completed_chunks: 0,
        has_transcript: false,
        has_minutes: false,
        error: null,
        created_at: new Date().toISOString(),
        transcribed_at: null,
        loaded: true,
    };
}

export default function notulensi(initialState) {
    // Berkas asli disimpan di luar state Alpine: objek File tidak boleh
    // dibungkus proxy reaktif karena method-nya kehilangan konteks.
    const files = new Map();

    return {
        tab: 'transkrip',
        transcriptView: 'gabungan',
        items: initialState.recordings.map(itemFromSummary),
        activeKey: null,
        processing: false,
        generating: false,
        loadingDetail: false,
        dragging: false,
        controller: null,
        clock: Date.now(),
        clockTimer: null,
        announcement: '',
        toasts: [],
        settings: initialState.settings,
        limits: initialState.limits,
        models: initialState.models,
        keyForm: { groq_key: '', anthropic_key: '' },
        savingSettings: false,

        init() {
            if (this.items.length > 0) {
                this.select(this.items[0].key);
            }
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

        get readiness() {
            return [
                {
                    label: 'API key transkripsi tersedia',
                    done: this.providerReady('groq'),
                    hint: 'Isi Groq API key di tab Pengaturan.',
                },
                {
                    label: 'Berkas audio diunggah',
                    done: this.items.length > 0,
                    hint: 'Seret berkas rapat ke panel kiri.',
                },
                {
                    label: 'Transkrip selesai',
                    done: Boolean(this.active?.has_transcript),
                    hint: 'Klik "Mulai transkripsi" setelah berkas terunggah.',
                },
                {
                    label: 'Notulensi dibuat',
                    done: Boolean(this.active?.has_minutes),
                    hint: 'Butuh Anthropic API key dan transkrip yang sudah jadi.',
                },
            ];
        },

        /** Statistik proses berjalan: dipakai untuk progres, waktu tempuh, dan perkiraan sisa. */
        get runStats() {
            const run = this.active?.run;

            if (!run) {
                return null;
            }

            const elapsed = Math.max(0, (this.clock - run.startedAt) / 1000);
            const average = run.done > 0 ? elapsed / run.done : null;
            const remaining = Math.max(0, run.total - run.done);

            return {
                done: run.done,
                total: run.total,
                elapsed,
                average,
                eta: average !== null && remaining > 0 ? average * remaining : null,
            };
        },

        async select(key) {
            this.activeKey = key;
            await this.loadDetail(this.active);
        },

        async loadDetail(item) {
            if (!item || item.loaded || !item.recordingId) {
                return;
            }

            this.loadingDetail = true;

            try {
                const { recording } = await api.showRecording(item.recordingId);
                this.applyDetail(item, recording);
            } catch (error) {
                this.reportError(error);
            } finally {
                this.loadingDetail = false;
            }
        },

        applyDetail(item, payload) {
            for (const field of DETAIL_FIELDS) {
                if (field in payload) {
                    item[field] = payload[field];
                }
            }

            if (payload.meeting) {
                item.meeting = { ...item.meeting, ...payload.meeting };
            }

            item.loaded = true;
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

            const language = this.settings.languages[this.settings.preferences.language];

            accepted.slice(0, room).forEach((file) => {
                const item = itemFromFile(file, language);
                files.set(item.key, file);
                this.items.unshift(item);
            });

            this.activeKey = this.items[0].key;
            this.tab = 'transkrip';
        },

        async removeItem(item) {
            if (item.status === 'processing') {
                this.toast('Hentikan transkripsi dulu sebelum menghapus rekaman ini.', 'caution');

                return;
            }

            files.delete(item.key);
            this.items = this.items.filter((candidate) => candidate.key !== item.key);

            if (this.activeKey === item.key) {
                this.activeKey = this.items[0]?.key ?? null;
                await this.loadDetail(this.active);
            }

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
            this.startClock();

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
                this.stopClock();
            }
        },

        async transcribeItem(item) {
            const file = files.get(item.key);
            const signal = this.controller.signal;

            this.activeKey = item.key;
            this.tab = 'transkrip';
            item.status = 'processing';
            item.status_label = 'Diproses';
            item.error = null;
            item.transcript = '';
            item.segments = [];
            this.setMessage(item, 'Membaca dan mendekode berkas audio…');

            try {
                const buffer = await decodeAudioFile(file);
                const chunkSeconds = this.settings.preferences.chunk_seconds;
                const segments = planSegments(buffer.duration, chunkSeconds);

                item.duration_seconds = buffer.duration;
                item.total_chunks = segments.length;
                item.run = { startedAt: Date.now(), done: 0, total: segments.length };
                this.clock = Date.now();

                this.setMessage(
                    item,
                    `Durasi ${formatDuration(buffer.duration)} — dibagi menjadi ${segments.length} segmen ` +
                        `berdurasi ${chunkSeconds} detik.`,
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
                this.applyDetail(item, recording);

                // Rekaman baru lahir dengan status "pending" di server; kembalikan
                // ke status lokal agar kartu progres tidak berkedip ke "Menunggu"
                // sampai segmen pertama selesai.
                item.status = 'processing';
                item.status_label = 'Diproses';

                for (const segment of segments) {
                    if (signal.aborted) {
                        throw new DOMException('Dibatalkan', 'AbortError');
                    }

                    this.setMessage(
                        item,
                        `Segmen ${segment.index + 1} dari ${segments.length} · ` +
                            `menit ${this.formatClock(segment.start)}–${this.formatClock(segment.end)}`,
                    );

                    const blob = await renderSegment(buffer, segment.start, segment.end);
                    const response = await this.sendChunkWithRetry(item, segment, blob, signal);

                    this.applyDetail(item, response.recording);
                    item.run = { ...item.run, done: segment.index + 1 };
                    this.clock = Date.now();
                }

                this.setMessage(item, `Selesai dalam ${formatDuration(this.runStats?.elapsed ?? 0)}.`);
                item.run = null;
                this.toast(`Transkrip "${item.name}" selesai — ${item.word_count} kata.`, 'positive');
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
                    this.setMessage(
                        item,
                        `Kuota Groq penuh — menunggu ${wait} detik lalu mengulang segmen ` +
                            `${segment.index + 1} (percobaan ${attempt + 1} dari ${MAX_CHUNK_ATTEMPTS}).`,
                    );
                    await sleep(wait, signal);
                }
            }
        },

        async handleTranscriptionError(item, error) {
            const cancelled = error.name === 'AbortError';

            item.status = cancelled ? 'cancelled' : 'failed';
            item.status_label = cancelled ? 'Dibatalkan' : 'Gagal';
            item.error = cancelled ? 'Transkripsi dihentikan sebelum selesai.' : error.message;
            item.run = null;
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
            this.toast('Menghentikan transkripsi setelah segmen berjalan…', 'caution');
        },

        startClock() {
            this.clockTimer ??= setInterval(() => {
                this.clock = Date.now();
            }, 1000);
        },

        stopClock() {
            clearInterval(this.clockTimer);
            this.clockTimer = null;
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
                const { recording } = await api.generateMinutes(item.recordingId, item.meeting);
                this.applyDetail(item, recording);
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

        providerSource(provider) {
            const state = this.settings.providers[provider];

            if (state?.user_key) {
                return `Key Anda (${state.masked})`;
            }

            return state?.server_key ? 'Key server' : 'Belum diatur';
        },

        /* ------------------------------------------------- estimasi biaya */

        get segmentSizeMb() {
            // 16 kHz × 16 bit mono = 32 KB per detik.
            return ((this.settings.preferences.chunk_seconds * 32) / 1024).toFixed(1);
        },

        get requestsPerHour() {
            return Math.ceil(3600 / this.settings.preferences.chunk_seconds);
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

        /* --------------------------------------------------- pemformatan */

        formatSize(bytes) {
            if (bytes >= 1024 * 1024) {
                return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
            }

            return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        },

        formatDuration,

        formatClock(seconds) {
            const total = Math.max(0, Math.round(seconds));
            const minutes = String(Math.floor(total / 60)).padStart(2, '0');

            return `${minutes}:${String(total % 60).padStart(2, '0')}`;
        },

        formatNumber(value) {
            return new Intl.NumberFormat('id-ID').format(value ?? 0);
        },

        formatDateTime(iso) {
            if (!iso) {
                return '—';
            }

            return new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(iso));
        },

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
