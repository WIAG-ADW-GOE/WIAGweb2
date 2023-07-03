import { Controller } from 'stimulus';

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
		this.#submitFormElement(form_loop);
	    }
	}
    }

    async #submitFormElement(element) {

	var body = new URLSearchParams(new FormData(element));

	var url = element.action;

        const response = await fetch(url, {
            method: "POST",
            body: body
        });

	const wrap_element = document.createElement("div");
	wrap_element.innerHTML = await response.text();
	const new_form = wrap_element.getElementsByTagName('form').item(0);
	element.firstElementChild.replaceWith(new_form.firstElementChild);

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
