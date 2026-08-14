const STORAGE_KEY = 'notulensi.theme';

export function storedTheme() {
    const value = localStorage.getItem(STORAGE_KEY);

    return ['light', 'dark', 'system'].includes(value) ? value : 'system';
}

export function applyTheme(theme) {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = theme === 'dark' || (theme === 'system' && prefersDark);

    document.documentElement.classList.toggle('dark', dark);

    if (theme === 'system') {
        localStorage.removeItem(STORAGE_KEY);
    } else {
        localStorage.setItem(STORAGE_KEY, theme);
    }
}

export function initTheme() {
    const theme = storedTheme();
    applyTheme(theme);

    // Ikut berubah saat pengguna mengganti tema sistem, selama belum memilih manual.
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (storedTheme() === 'system') {
            applyTheme('system');
        }
    });

    return theme;
}
