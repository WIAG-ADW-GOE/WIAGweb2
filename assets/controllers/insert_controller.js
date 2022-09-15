import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'insertPoint', 'collapseButton'];
    static values = {
	url: String
    };

    connect() {
	// console.log('connect new-element');
    }

    async insert(event) {
	const id_sans_idx = event.currentTarget.dataset.idSansIdx;

	// find insert position
	var current_insert = this.insertPointTargets.filter((el) => {
	    return el.dataset.idSansIdx == id_sans_idx;
	});

	if (current_insert.length > 0) {
	    const insert_dataset = current_insert[0].dataset;
	    const insert_class_name = current_insert[0].className;


	    // build form id and name for the new elements
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

	    const response = await fetch(paramString);
	    const form_section = await response.text();
	    new_element.innerHTML = form_section;
	    current_insert[0].parentNode.insertBefore(new_element, current_insert[0]);

	    // increment index
	    insert_dataset.nextIdx = parseInt(insert_dataset.nextIdx) + 1;
	}

	// open entry
	// 2022-08-31: obsolete: show button only in the unfolded entry
	// const cps_button = this.collapseButtonTarget;
	// if (!cps_button.getAttribute("aria-expanded")) {
	//     cps_button.click();
	// }

    }



}
