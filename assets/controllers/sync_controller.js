import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['copy', 'check', 'fill', 'toggle', 'expand', 'click', 'mark'];
    static values = {
	fillStatus: String,
	fillUrl: String,
	toggleFirst: String,
	toggleSecond: String,
    };

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

	// change HTML only once
	if (this.fillStatusValue == 'empty' && this.fillUrlValue != "") {
	    const response = await fetch(this.fillUrlValue);
	    const new_html = await response.text();

	    this.fillTarget.innerHTML = new_html;
	    this.fillStatusValue = 'filled';
	}

    }

    markChanged(event) {
	console.log('sync#mark');
	this.markTarget.classList.add('wiag-border-changed');
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

    /**
     * expand()
     *
     * 2023-04-06 open edit form when it is manually marked as edited
     */
    expand() {
	console.log('sync#expand');
	var aria_expanded = this.expandTarget.getAttribute('aria-expanded');
	if (aria_expanded == 'false') {
	    this.expandTarget.click();
	}
    }

}
