import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['result', 'pageNumber'];

    connect() {
	// console.log('this is hide-on-input');
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

}
