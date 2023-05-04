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
     * insert empty form after as first element in a list
     */
    async insertAfter(event) {
	console.log('insert#insertAfter');
	const currentTarget = event.currentTarget;

	if (this.hasHideTriggerValue) {
	    currentTarget.classList.add("d-none");
	}

	const response = await this.fetch();

	const wrap = document.createElement("div");
	wrap.innerHTML = await response.text();

	// first element is an element containing the button
	const element_one = this.element.firstElementChild;
	console.log(element_one);

	this.element.insertBefore(
	    wrap.firstElementChild,
	    element_one.nextElementSibling
	);
    }

    /**
     * insert empty form for office data etc.
     */
    async insert(event) {
	console.log('insert#insert');
	// this changes after the ajax call!?
	const currentTarget = event.currentTarget;

	if (this.hasHideTriggerValue) {
	    currentTarget.classList.add("d-none");
	}

	const response = await this.fetch();

	const wrap = document.createElement("div");
	wrap.innerHTML = await response.text();

	// console.log(this.pointValue);

	var new_elmt = null;
	if (this.pointValue == "last") {
	    new_elmt = this.element.appendChild(wrap.firstElementChild);
	} else if (this.pointValue == "lastButOne") {
	    new_elmt = this.element.insertBefore(
		wrap.firstElementChild,
		this.element.lastElementChild
	    );
	}
	//var dummy_scroll_target = document.getElementById('ref_insert_bottom');
	//dummy_scroll_target.scrollIntoView();
	if (currentTarget.classList.contains('show-new-element')) {
	    new_elmt.scrollIntoView();
	}
    }

    async fetch(url) {
	// add index to URL parameters; ajax
	const url_parts = this.urlValue.split('?');
	const base_url = url_parts[0];
	const params_init = url_parts.length > 1 ? url_parts[1] : "";
	var params = new URLSearchParams(params_init);
	params.append('current_idx', this.elmt_idx++);

	const final_url = base_url + '?' + params.toString();
	//console.log(final_url);
	return await fetch(final_url);
    }

}
