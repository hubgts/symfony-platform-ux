import { Controller } from '@hotwired/stimulus';
import { visit } from '@hotwired/turbo';

/*
 * Navigation au clavier entre Pokémon : ← précédent, → suivant (via Turbo Drive).
 * <article data-controller="keyboard-nav" data-keyboard-nav-previous-value="/pokemon/24" data-keyboard-nav-next-value="/pokemon/26"
 *          data-action="keydown@window->keyboard-nav#navigate">
 */
export default class extends Controller {
    static values = { previous: String, next: String };

    navigate(event) {
        if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || event.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }

        const url = { ArrowLeft: this.previousValue, ArrowRight: this.nextValue }[event.key];
        if (url) {
            event.preventDefault();
            visit(url);
        }
    }
}
