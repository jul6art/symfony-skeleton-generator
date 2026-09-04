import { registerTranslator } from '@jul6art/core-bundle/i18n/registry';
import { trans } from './translator';

// ⚠️ AVANT `./bootstrap` : c'est lui qui démarre Stimulus, et un contrôleur qui se connecte avant
// l'enregistrement traduirait sa première frappe en clés brutes. Le registre est la seule voie par
// laquelle un contrôleur livré dans `vendor/jul6art/*` atteint le catalogue — il ne peut pas
// importer ce fichier-ci, le chemin depuis `vendor/` n'existe pas.
registerTranslator(trans);

import './bootstrap';
import './styles/app.css';
import './datatable/renderers';

// jQuery + Select2 : l'autocomplétion des filtres de datatable et les `<select>` enrichis des
// formulaires. Select2 est un plugin jQuery, d'où les deux globales.
import jQuery from 'jquery';
window.jQuery = jQuery;
window.$ = jQuery;
import 'select2';
import 'select2/dist/css/select2.min.css';

// DataTables. Le contrôleur du bundle l'attend sur `window` plutôt que de l'importer : une page
// sans tableau ne paie alors pas les 300 Ko de la bibliothèque.
import DataTable from 'datatables.net-dt';
import 'datatables.net-responsive-dt';
window.DataTable = DataTable;

// ── Dropdowns et barre latérale mobile ──────────────────────────────────────
// Deux comportements sans état, pilotés par attributs plutôt que par des contrôleurs Stimulus :
// ils s'appliquent à des éléments que la coquille rend elle-même.
const closeAllDropdowns = () => {
    document.querySelectorAll('[data-ui-dropdown-menu]').forEach((menu) => menu.classList.add('hidden'));
};

document.addEventListener('click', (event) => {
    const dropdownToggle = event.target.closest('[data-ui-dropdown-toggle]');
    const sidebarToggle = event.target.closest('[data-ui-sidebar-toggle]');
    const dropdownContainer = event.target.closest('[data-ui-dropdown]');
    const sidebarPanel = document.querySelector('[data-ui-sidebar-panel]');

    if (dropdownToggle) {
        event.preventDefault();
        event.stopPropagation();
        const menu = dropdownToggle.closest('[data-ui-dropdown]')?.querySelector('[data-ui-dropdown-menu]');
        if (!menu) return;
        const isHidden = menu.classList.contains('hidden');
        closeAllDropdowns();
        if (isHidden) menu.classList.remove('hidden');
        return;
    }

    if (sidebarToggle) {
        event.preventDefault();
        sidebarPanel?.classList.toggle('-translate-x-full');
        return;
    }

    if (!dropdownContainer) closeAllDropdowns();

    if (sidebarPanel && window.matchMedia('(max-width: 1023px)').matches && !sidebarPanel.contains(event.target)) {
        sidebarPanel.classList.add('-translate-x-full');
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeAllDropdowns();
});
