import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'form'];

    connect() {
	// console.log('this is submit-form');
	// console.log(this.element);
    }

    async submit(event) {
	console.log('submit-form#submit');

	const btn = event.target;

	event.preventDefault(); // avoid to submit the form twice

	var body = new URLSearchParams(new FormData(this.element));

	// console.log(btn.name, btn.value);
	// e.g. for pagination
	if (btn.tagName == "BUTTON" && btn.name != "" && btn.value != "") {
	    // console.log(btn.name, btn.value);
	    body.append(btn.name, btn.value);
	}

	var url = btn.getAttribute('formaction') ?? this.element.action;

        const response = await fetch(url, {
            method: this.element.method,
            body: body
        });

	this.element.innerHTML = await response.text();
    }

    async submitList(event) {
	const form_list = this.formTargets;
	for (const form_loop of form_list) {
	    const isEdited_id = form_loop.id + "_item_formIsEdited"
	    const isEdited_elem = document.getElementById(isEdited_id);
	    if (isEdited_elem.checked) {
		this.#submitListElement(form_loop);
	    }
	}
    }

    /**
     * submit a form via a submit button and replace it's content with the result
     */
    async submitSingle(event) {
	event.preventDefault();

	var body = new URLSearchParams(new FormData(event.target.form));
	var url = event.target.getAttribute("formaction");

        const response = await fetch(url, {
            method: "POST",
            body: body
        });

	const wrap_element = document.createElement("div");
	wrap_element.innerHTML = await response.text();
	event.target.form.replaceWith(wrap_element.firstElementChild);

    }

    async #submitListElement(element) {

	var body = new URLSearchParams(new FormData(element));

	var url = element.action;

        const response = await fetch(url, {
            method: "POST",
            body: body
        });

	const wrap_element = document.createElement("div");
	wrap_element.innerHTML = await response.text();
	element.replaceWith(wrap_element.firstElementChild);
    }

    /**
     * delete a form element, e.g. a person form
     */
    async deleteLocal(event) {
	event.preventDefault();
	const btn = event.currentTarget;

	const form = btn.form; // cool
	const url = btn.getAttribute('formaction');
	var body = new URLSearchParams(new FormData(form));

        const response = await fetch(url, {
            method: "POST",
            body: body
        });

	form.remove();

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
