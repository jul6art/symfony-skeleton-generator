import { Controller } from '@hotwired/stimulus';

/*
 * Confirmation avant une action destructive.
 *
 * Se pose sur le formulaire : la soumission est retenue le temps d'afficher la
 * modale (<dialog> natif : focus piégé, fermeture par Échap), puis relancée
 * telle quelle si l'utilisateur confirme.
 *
 *   <form data-controller="confirm" data-action="submit->confirm#request">
 *       <dialog data-confirm-target="dialog">…</dialog>
 *   </form>
 */
export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        this.confirmed = false;
    }

    request(event) {
        if (this.confirmed) {
            return;
        }

        event.preventDefault();
        this.dialogTarget.showModal();
    }

    accept() {
        this.confirmed = true;
        this.dialogTarget.close();
        this.element.requestSubmit();
    }

    cancel() {
        this.dialogTarget.close();
    }
}
