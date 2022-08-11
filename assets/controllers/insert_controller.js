import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'insertPoint'];

    connect() {
	// console.log('connect new-element');
    }

    async insert(event) {
	const obj_type = event.currentTarget.dataset.objType;
	console.log(obj_type);

	// find insert position
	var current_insert = this.insertPointTargets.filter((el) => {
	    return el.dataset.objType == obj_type;
	});

	if (current_insert.length > 0) {
	    const insert_dataset = current_insert[0].dataset;
	    const insert_class_name = current_insert[0].className;

	    // build id and name for the new elements
	    console.log(insert_dataset.nextIdx);
	    const base_id = insert_dataset.idSansIdx + '_' + insert_dataset.nextIdx;
	    const base_input_name = insert_dataset.inputNameSansIdx + '[' + insert_dataset.nextIdx + ']';

	    // fetch HTML
	    const params = new URLSearchParams({
		base_id: base_id,
		base_input_name: base_input_name
	    });

	    var new_element = document.createElement('div');
	    new_element.className = insert_class_name;
	    const url = event.currentTarget.dataset.url;
	    const paramString = url + '?' + params.toString();

	    console.log(paramString);
	    const response = await fetch(paramString);
	    const form_section = await response.text();
	    new_element.innerHTML = form_section;
	    current_insert[0].parentNode.insertBefore(new_element, current_insert[0]);

	    // increment index
	    insert_dataset.nextIdx = parseInt(insert_dataset.nextIdx) + 1;
	}
    }



}
