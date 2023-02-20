import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['copy', 'check', 'fill', 'toggle'];
    static values = {
	fillUrl: String,
	toggleFirst: String,
	toggleSecond: String,
    };

    is_filled = false;

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

    async fill(event) {
	console.log('sync#fill');
	// console.log(this.fillTarget.id);

	if (!this.is_filled && this.fillUrlValue != "") {
	    const response = await fetch(this.fillUrlValue);
	    const new_html = await response.text();

	    this.fillTarget.innerHTML = new_html;
	    this.is_filled = true;
	}

    }

    toggle () {
	console.log('sync#toggle');
	var elmt = this.toggleTarget;
	// console.log(elmt);
	var current = elmt.getAttribute("value");
	if (current == this.toggleFirstValue) {
	    elmt.setAttribute("value", this.toggleSecondValue)
	} else {
	    elmt.setAttribute("value", this.toggleFirstValue)
	}
	//console.log(elmt.getAttribute("value"));
    }


}