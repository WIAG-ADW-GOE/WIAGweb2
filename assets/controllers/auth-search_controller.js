import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
	// console.log('this is auth-search');
    }

    // TODO build for GS, GND, ...
    search(event) {
	var t_elmt = event.currentTarget

	console.log(t_elmt.href);
	t_elmt.href = "https://adw-goe.de";

    }
}
