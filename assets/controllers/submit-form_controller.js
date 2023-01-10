import { Controller } from 'stimulus';

export default class extends Controller {
    static target = ['button'];
    static values = {
	newEntry: String,
    };

    connect() {
	// console.log('this is submit-form');
	// console.log(this.element);
    }

    async submit(event) {
	event.preventDefault();

	// console.log('newEntry: ', this.newEntryValue);
	var form_element = this.element;
	var newEntry = this.hasNewEntryValue ? this.newEntryValue : 0;
	var get_params = new URLSearchParams({
	    newEntry: newEntry,
	    listOnly: true,
	});

	const btn = event.target;
	var formaction =
	    btn.getAttribute('formaction') ||
	    form_element.action;

	if (btn.dataset.confirm) {
	    console.log('confirm');
	    return null;
	}

	var body = new URLSearchParams(new FormData(form_element));

	// console.log(btn.name, btn.value);
	// e.g. for pagination
	if (btn.tagName == "BUTTON" && btn.name != "" && btn.value != "") {
	    // console.log(btn.name);
	    body.append(btn.name, btn.value);
	}

	var url = formaction + '?' + get_params.toString();
        const response = await fetch(url, {
            method: form_element.method,
            body: body
        });

	this.element.innerHTML = await response.text();
    }

    /**
     * submit form with only one selection element
     */
    onChange(event) {
	// console.log('on change');
	this.element.submit();
    }

}
