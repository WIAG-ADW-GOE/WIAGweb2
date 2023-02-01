import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'insertPoint', 'collapseButton', 'template'];
    static values = {
	url: String,
	hideTrigger: String,
	point: String,
    };

    connect() {
	// form index for element placeholders
	this.elmt_idx = 6000;
    }

    /**
     * insert empty form for office data etc.
     */
    async insert(event) {
	console.log('insert#append');
	// this changes after the ajax call!?
	const currentTarget = event.currentTarget;

	if (this.hasHideTriggerValue) {
	    currentTarget.classList.add("d-none");
	}

	// add index to URL parameters; ajax
	const url_parts = this.urlValue.split('?');
	const base_url = url_parts[0];
	const params_init = url_parts.length > 1 ? url_parts[1] : "";
	var params = new URLSearchParams(params_init);
	params.append('current_idx', this.elmt_idx++);

	const final_url = base_url + '?' + params.toString();
	//console.log(final_url);
	const response = await fetch(final_url);

	const wrap = document.createElement("div");
	wrap.innerHTML = await response.text();

	if (this.pointValue == "last") {
	    this.element.appendChild(wrap.firstElementChild);
	} else if (this.pointValue == "lastButOne") {
	    this.element.insertBefore(
		wrap.firstElementChild,
		this.element.lastElementChild
	    );
	}

    }

}
