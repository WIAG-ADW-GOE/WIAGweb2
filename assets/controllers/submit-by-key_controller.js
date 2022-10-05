import { Controller } from 'stimulus';

export default class extends Controller {

    connect() {
	// console.log('this is submit-form');

	// send CTRL+S to this.elment.click()
	window.addEventListener("keydown", event => {
	    if (event.ctrlKey && event.code === "KeyS") {
		// console.log('Ctrl S');
		this.element.click();
		event.preventDefault();
	    }
	});
    }

}
