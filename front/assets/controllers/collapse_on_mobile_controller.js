import { Controller } from '@hotwired/stimulus';

/*
 * Replie un <details> sur petit écran au premier affichage (les filtres prennent alors trop de place).
 * Live Components conserve ce changement d'attribut d'un rendu à l'autre.
 */
export default class extends Controller {
    static values = { breakpoint: { type: Number, default: 960 } };

    connect() {
        if (window.innerWidth < this.breakpointValue) {
            this.element.open = false;
        }
    }
}
