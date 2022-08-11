import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['result', 'pageNumber', 'checkbox'];

    connect() {
	// console.log('this on-input_controller i');
    }

    /**
     * hide results of a previous search and reset page number
     */
    hideResults(event) {
	// console.log('event bubbles up');
	const eventTargetType = event.target.getAttribute('type');
	//
	if (this.hasPageNumberTarget) {
	    // console.log(this.pageNumberTarget);
	    this.pageNumberTarget.value = null;
	}
	if (eventTargetType == 'text') {
	    this.resultTarget.style.visibility = "hidden";
	}
    }

    /**
     * mark checkbox on input events
     */
    mark(event) {
	const eventTargetType = event.target.getAttribute('type');
	// console.log(eventTargetType);
	if (event.target != this.checkboxTarget) {
	    // console.log('checkbox event');
	    this.checkboxTarget.checked = true;
	}
    }
}
