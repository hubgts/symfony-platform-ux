import { Controller } from '@hotwired/stimulus';

/*
 * Joue le cri du Pokémon.
 * <button data-controller="cry" data-cry-url-value="…ogg" data-action="cry#play">
 */
export default class extends Controller {
    static values = { url: String };

    play() {
        this.audio ??= new Audio(this.urlValue);
        this.audio.currentTime = 0;
        this.element.classList.add('is-playing');
        this.audio.addEventListener('ended', () => this.element.classList.remove('is-playing'), { once: true });
        this.audio.play().catch(() => this.element.classList.remove('is-playing'));
    }

    disconnect() {
        this.audio?.pause();
    }
}
