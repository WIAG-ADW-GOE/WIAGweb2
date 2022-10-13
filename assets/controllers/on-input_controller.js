import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox'];

    connect() {
	// console.log('this is on-input_controller');
    }

    /**
     * mark checkbox on input events
     */
    mark(event) {
	if (event.target != this.checkboxTarget) {
	    // console.log('checkbox event');
	    this.checkboxTarget.checked = true;
	}
    }

    /**
     * toggle checkbox
     */
    toggle(event) {
	this.checkboxTarget.click();
    }
}
