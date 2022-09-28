import { Controller } from 'stimulus';

export default class extends Controller {
    static values = {
	newEntry: String,
    };

    connect() {
	// console.log('this is submit-form');
	window.addEventListener("keydown", this.submitByKey);
    }

    async submit() {
	// console.log('newEntry: ', this.newEntryValue);
	var form_element = this.element.getElementsByTagName('form')[0];
	var get_params = new URLSearchParams({
	    newEntry: this.newEntryValue,
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

    submitByKey(event) {
	// console.log(event.keyCode);
	if (event.ctrlKey && event.code === "KeyS"){
	    const submit_elmt = document.getElementById('submit_edit_form')
            //alert('CTRL + S is pressed!');
	    submit_elmt.click();
            event.preventDefault();
       }

    }
}
