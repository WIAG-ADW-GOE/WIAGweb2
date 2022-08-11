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
	if (this.hasPageNumberTarget) {
	    // console.log(this.pageNumberTarget);
	    this.pageNumberTarget.value = null;
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
