import { Controller } from 'stimulus';

export default class extends Controller {
    connect() {
	// console.log('this is submit-form');
    }

    async submit() {
	var form_element = this.element.getElementsByTagName('form')[0];
	var get_params = new URLSearchParams({
	    list_only : 1,
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
}
