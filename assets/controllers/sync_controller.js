import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['copy', 'check'];

    connect() {
	// console.log('sync connected')
    }

    /**
     * copy values in input fields
     */
    copy(event) {
	// console.log(event.currentTarget.value);
	for (let m of this.copyTargets) {
	    if (m != event.currentTarget) {
		m.value = event.currentTarget.value
	    }
	}
	return null;
    }

    check(event) {
	// console.log('check');
	this.checkTarget.setAttribute('checked', 'checked');
    }

    uncheck(event) {
	// console.log('uncheck');
	this.checkTarget.removeAttribute('checked');
    }

}
