import { encodeWav } from './wav.js';

/** Whisper bekerja pada 16 kHz mono; mengirim lebih dari itu hanya membuang bandwidth. */
export const TARGET_SAMPLE_RATE = 16000;

export class AudioDecodeError extends Error {
    constructor(fileName) {
        super(
            `Browser tidak bisa membaca "${fileName}". Konversi dulu ke MP3 atau WAV ` +
                '(mis. lewat VLC: Media → Convert/Save → Profil "Audio - MP3"), lalu unggah ulang.',
        );
        this.name = 'AudioDecodeError';
    }
}

/**
 * Mendekode berkas audio menjadi AudioBuffer.
 *
 * AudioContext selalu ditutup, termasuk saat dekode gagal, agar tab tidak
 * menahan sumber daya audio setelah unggahan berkas yang tidak didukung.
 */
export async function decodeAudioFile(file) {
    const AudioContextClass = window.AudioContext ?? window.webkitAudioContext;

    if (!AudioContextClass) {
        throw new Error('Browser ini tidak mendukung Web Audio API.');
    }

    const context = new AudioContextClass();

    try {
        return await context.decodeAudioData(await file.arrayBuffer());
    } catch {
        throw new AudioDecodeError(file.name);
    } finally {
        context.close().catch(() => {});
    }
}

export function chunkCount(durationSeconds, chunkSeconds) {
    return Math.max(1, Math.ceil(durationSeconds / chunkSeconds));
}

/**
 * Memotong satu segmen sekaligus me-resample ke 16 kHz mono.
 *
 * OfflineAudioContext yang melakukan slicing, downmix, dan resample dalam satu
 * langkah — hasilnya jauh lebih kecil (≈1,9 MB per menit) sehingga satu segmen
 * bisa berdurasi menit, bukan detik.
 */
export async function renderSegment(audioBuffer, startSeconds, endSeconds) {
    const duration = Math.max(0, Math.min(endSeconds, audioBuffer.duration) - startSeconds);
    const frames = Math.max(1, Math.ceil(duration * TARGET_SAMPLE_RATE));
    const offline = new OfflineAudioContext(1, frames, TARGET_SAMPLE_RATE);

    const source = offline.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(offline.destination);
    source.start(0, startSeconds, duration);

    return encodeWav(await offline.startRendering());
}

/** Panjang segmen yang lebih pendek dari ini tidak layak dikirim ke API. */
const MIN_SEGMENT_SECONDS = 0.25;

/** Membangun daftar segmen (metadata saja — audio dirender saat dibutuhkan). */
export function planSegments(durationSeconds, chunkSeconds) {
    const total = chunkCount(durationSeconds, chunkSeconds);

    return Array.from({ length: total }, (_, index) => ({
        index,
        start: index * chunkSeconds,
        end: Math.min((index + 1) * chunkSeconds, durationSeconds),
    }))
        // Durasi yang tidak habis dibagi bisa menyisakan potongan beberapa
        // milidetik di ujung; mengirimnya hanya membuang satu permintaan API.
        .filter((segment, index) => index === 0 || segment.end - segment.start >= MIN_SEGMENT_SECONDS)
        .map((segment, index) => ({ ...segment, index }));
}

export function formatDuration(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
        return '0 dtk';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = Math.floor(seconds % 60);

    if (hours > 0) {
        return minutes > 0 ? `${hours} jam ${minutes} mnt` : `${hours} jam`;
    }

    if (minutes > 0) {
        return rest > 0 ? `${minutes} mnt ${rest} dtk` : `${minutes} mnt`;
    }

    return `${rest} dtk`;
}
