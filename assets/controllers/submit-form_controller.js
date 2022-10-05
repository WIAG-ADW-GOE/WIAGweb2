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

    async submit() {
	// console.log('submit');
	// console.log('newEntry: ', this.newEntryValue);
	var form_element = this.element;
	var newEntry = this.hasNewEntryValue ? this.newEntryValue : 0;
	var get_params = new URLSearchParams({
	    newEntry: newEntry,
	    listOnly: true,
	});
	var url = form_element.action + '?' + get_params.toString();
        const response = await fetch(url, {
            method: form_element.method,
            body: new URLSearchParams(new FormData(form_element)),
        });

        // this.dispatch('async:submitted', {
        //     response,
        // })
	this.element.innerHTML = await response.text();
    }

    /**
     * submit form with only one selection element
     */
    onChange(event) {
	console.log('on change');
	this.element.submit();
    }
}
