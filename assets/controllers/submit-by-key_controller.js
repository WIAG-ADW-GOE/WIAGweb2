import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['button']

    connect() {
	// console.log('submit-by-key');

	// send CTRL+S to this.elment.click()
	// window catches this event type first
	window.addEventListener("keydown", event => {
	    if (event.ctrlKey && event.code === "KeyS") {
		const btn = this.buttonTargets[0];
		event.preventDefault();
		console.log('submit-by-key#connect');
		btn.click();
	    }
	});

    }

}
