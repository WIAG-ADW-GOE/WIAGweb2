import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'insertPoint', 'collapseButton', 'template'];
    static values = {
	url: String,
	baseId: String,
	baseInputName: String
    };

    connect() {
	// console.log('connect new-element');
	this.elmt_idx = 60;
    }

    async insert(event) {
	const insertId = event.currentTarget.dataset.idSansIdx;

	// find insert position
	var current_insert = this.insertPointTargets.filter((el) => {
	    return el.dataset.idSansIdx == id_sans_idx;
	});

	if (current_insert.length > 0) {
	    const insert_dataset = current_insert[0].dataset;
	    const insert_class_name = current_insert[0].className;


	    // build form id and name for the new elements
	    // const base_id = insert_dataset.idSansIdx + '_' + insert_dataset.nextIdx;
	    const base_id = insert_dataset.idSansIdx + '_' + this.elmt_idx;
	    // const base_input_name = insert_dataset.inputNameSansIdx + '[' + insert_dataset.nextIdx + ']';
	    const base_input_name = insert_dataset.inputNameSansIdx + '[' + this.elmt_idx + ']';

	    // fetch HTML
	    const params = new URLSearchParams({
		base_id: base_id,
		base_input_name: base_input_name
	    });

	    var new_element = document.createElement('div');
	    new_element.className = insert_class_name;
	    const url = event.currentTarget.dataset.url;
	    const paramString = url + '?' + params.toString();

	    const response = await fetch(paramString);
	    const form_section = await response.text();
	    new_element.innerHTML = form_section;
	    current_insert[0].parentNode.insertBefore(new_element, current_insert[0]);

	    // increment index
	    this.elmt_idx++;
	    // insert_dataset.nextIdx = parseInt(insert_dataset.nextIdx) + 1;
	}

    }

    async insertLocal(event) {
	const current_target = event.currentTarget;
	const insert_elmt = this.insertPointTarget;
	// console.log(this.baseIdValue);

	// new element
	var new_element = document.createElement('div');
	new_element.className = insert_elmt.className;
	new_element.dataset.controller = insert_elmt.dataset.controller;

	// fetch HTML
	const params = new URLSearchParams({
	    base_id: this.baseIdValue,
	    base_input_name: this.baseInputNameValue,
	    current_idx: this.elmt_idx++,
	});
	const url = current_target.dataset.url;
	const paramString = url + '?' + params.toString();
	const response = await fetch(paramString);

	const form_section = await response.text();
	new_element.innerHTML = form_section;

	insert_elmt.parentNode.insertBefore(new_element, insert_elmt);

	// hide button
	var add_class = current_target.dataset.addClass;
	if (add_class) {
	    current_target.classList.add(add_class);
	}
    }

    insertTemplate(event) {
	const template = this.templateTarget;
	var new_elmt = template.cloneNode('deep');
	new_elmt.classList.remove("d-none");
	var inner_html = new_elmt.innerHTML;
	inner_html = inner_html.replaceAll('xxx', this.elmt_idx++);
	new_elmt.innerHTML = inner_html;
	template.parentNode.insertBefore(new_elmt, template);

    }
}
