import Alpine from 'alpinejs';
import notulensi from './notulensi.js';
import { applyTheme, initTheme } from './theme.js';

window.Alpine = Alpine;

Alpine.data('notulensi', notulensi);
Alpine.data('themeSwitcher', () => ({
    theme: initTheme(),

    setTheme(theme) {
        this.theme = theme;
        applyTheme(theme);
    },
}));

Alpine.start();
