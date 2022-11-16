import { Controller } from 'stimulus';

export default class extends Controller {
    static values = {
	newData: String,
	oldData: String,
	targetId: String,
    };


    connect() {
	// console.log('set-id', 'new: ', this.newIdValue, ' old: ', this.oldIdValue);
    }

    // e.g. for blur-events
    set (event) {
	var elmt = document.getElementById(this.targetIdValue);
	var current = elmt.getAttribute("value");
	if (current == this.newDataValue) {
	    elmt.setAttribute("value", this.oldDataValue)
	} else {
	    elmt.setAttribute("value", this.newDataValue)
	}
	// console.log(elmt.id);
    }
}
