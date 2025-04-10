import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['result', 'pageNumber'];

    connect() {
	// console.log('this is hide-on-input')
    }

    /**
     * hide results of a previous search and reset page number
     */
    hideResults(event) {

	// start a new search on page 1
	for (let pnt of this.pageNumberTargets) {
	    pnt.value = null;
	}

	for (let element of this.resultTargets) {
	    element.style.visibility = "hidden";
	}

	// link this function to the div containing the input fields, then the following version is obsolete
	// const eventTargetType = event.target.getAttribute('type');
	// if (eventTargetType == 'text') {
	//     this.resultTarget.style.visibility = "hidden";
	// }
    }

}
