import { Application } from '@hotwired/stimulus';

/**
 * Enregistrement des contrôleurs Stimulus.
 *
 * ⚠️ L'identifiant vient du CHEMIN du fichier : `controllers/ui/modal_controller.js` devient
 * `ui--modal`. Ce n'est pas un détail de rangement — les gabarits d'`admin-bundle` et le balisage
 * que la datatable rend nomment `ui--modal`, `ui--tooltip`, `ui--select2`. Déplacer un fichier
 * change son identifiant, et un contrôleur Stimulus qui n'est pas trouvé ne lève pas : la modale
 * de confirmation disparaît simplement, et la suppression se fait sans demander.
 */
const application = Application.start();
const context = require.context('./controllers', true, /_controller\.js$/);

context.keys().forEach((key) => {
    const controller = context(key).default;

    if (!controller) {
        return;
    }

    application.register(
        key.replace('./', '').replace('_controller.js', '').split('/').join('--'),
        controller,
    );
});

window.Stimulus = application;
