import { Controller } from 'stimulus';

export default class extends Controller {
    static values = {
	formId: String,
    };

    connect() {
	// console.log('this is submit');
    }

    async submitForm(event) {
	var active = false;
	if (active) {
	    var formElement = this.element.getElementsByTagName('form')[0];
	    event.preventDefault();
	    // window.alert('this is submit#submitForm');
	    const url = formElement.action;
	    const params = new URLSearchParams({
		'list-only': 1,
            });
	    const fullurl = url+'?'+params.toString();
	    const form = new FormData(formElement);

	    const response = await fetch(fullurl, {
		method: 'POST',
		body: form
	    });

	    var newHTML = await response.text();

	    this.element.innerHTML = newHTML;
	}
    }


}
