import { Controller } from 'stimulus';

export default class extends Controller {
    static target = ['button'];

    connect() {
	// console.log('this is submit-form');
	// console.log(this.element);
    }

    async submit(event) {
	console.log('submit-form#submit');
	event.preventDefault();


	const btn = event.target;

	var body = new URLSearchParams(new FormData(this.element));

	// console.log(btn.name, btn.value);
	// e.g. for pagination
	if (btn.tagName == "BUTTON" && btn.name != "" && btn.value != "") {
	    // console.log(btn.name, btn.value);
	    body.append(btn.name, btn.value);
	}

        const response = await fetch(this.element.action, {
            method: this.element.method,
            body: body
        });

	this.element.innerHTML = await response.text();
    }

    /**
     * submit form when an input field changes
     */
    onChange(event) {
	// console.log('on change');
	event.preventDefault();
	this.element.submit();
    }

}
