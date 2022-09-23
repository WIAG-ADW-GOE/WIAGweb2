import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['carrier'];

    connect() {
	// console.log('this is save-collapse');
    }

    set(event) {
	// console.log(event.type);
	if (event.type == 'shown.bs.collapse') {
	    this.carrierTarget.setAttribute('checked', 'checked');
	} else {
	    this.carrierTarget.removeAttribute('checked');
	}
    }
}
