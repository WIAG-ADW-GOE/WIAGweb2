import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['member'];

    connect() {
	// console.log('sync-input connected')
    }

    /**
     * synchronise values in input fields
     */
    sync(event) {
	for (let m of this.memberTargets) {
	    if (m != event.currentTarget) {
		m.value = event.currentTarget.value
	    }
	}
	return null;
    }

}
