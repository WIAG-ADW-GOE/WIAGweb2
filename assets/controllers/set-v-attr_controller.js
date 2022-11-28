import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['flag'];
    static values = {
	to: String,
	from: String,
    };


    connect() {
	// console.log('set-v-attr')
    }

    toggle (event) {
	var elmt = this.flagTarget;
	var current = elmt.getAttribute("value");
	if (current == this.fromValue) {
	    elmt.setAttribute("value", this.toValue)
	} else {
	    elmt.setAttribute("value", this.fromValue)
	}
	//console.log(elmt.getAttribute("value"));
    }

}
