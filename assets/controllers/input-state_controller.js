import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox'];

    connect() {
	// console.log('connect input-state');
    }

    /**
     * disable or enable inputs with this.checkboxTarget
     */
    set(event) {
	if (event.target == this.checkboxTarget) {
	    const input_list = this.element.getElementsByTagName('input');
	    if (event.target.checked) {
		for (let input of input_list) {
		    if (input != this.checkboxTarget && !input.hasAttribute('hidden')) {
			input.setAttribute('disabled', 'disabled');
		    }
		}
	    } else {
		for (let input of input_list) {
		    input.removeAttribute('disabled');
		}
	    }

	}
    }

}
