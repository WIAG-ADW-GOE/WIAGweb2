import { Controller } from 'stimulus';

export default class extends Controller {
    connect() {
	// console.log('this is facet-submit');
    }

    submit(event) {
	if (event.target.getAttribute('type') == 'checkbox') {
	    this.element.submit();
	}
    }

}
