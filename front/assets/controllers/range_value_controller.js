import { Controller } from '@hotwired/stimulus';

/*
 * Affiche la valeur d'un curseur pendant qu'on le fait glisser (le rendu Live n'arrive qu'après le debounce).
 * <label data-controller="range-value"><output data-range-value-target="output"></output>
 *        <input type="range" data-action="input->range-value#update"></label>
 */
export default class extends Controller {
    static targets = ['output'];

    update(event) {
        this.outputTarget.textContent = Number(event.target.value) > 0 ? event.target.value : '—';
    }
}
