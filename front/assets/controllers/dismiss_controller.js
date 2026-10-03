import { Controller } from '@hotwired/stimulus';

/*
 * Message flash qui se ferme seul après un délai, ou au clic.
 * <div data-controller="dismiss" data-dismiss-delay-value="5000">
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 0 } };

    connect() {
        if (this.delayValue > 0) {
            this.timeout = setTimeout(() => this.close(), this.delayValue);
        }
    }

    close() {
        this.element.classList.add('is-leaving');
        this.element.addEventListener('animationend', () => this.element.remove(), { once: true });
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
