import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['result'];

    connect() {
	// console.log('this is hide-on-input');
    }

    hideResults(event) {
	// console.log('event bubbles up');
	const eventTargetType = event.target.getAttribute('type');
	// console.log('this.resultTarget');
	if (eventTargetType == 'text') {
	    this.resultTarget.style.visibility = "hidden";
	}
    }

}
