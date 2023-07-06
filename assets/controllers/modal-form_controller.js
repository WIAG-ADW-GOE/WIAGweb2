import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['modal', 'modalBody'];
    static values = {
	openUrl: String,
	mergeItemUrl: String,
    };

    // set this to a Modal object, when the Modal is opened
    modal = null;
    // button used to open the modal
    openBtn = null;
    // form holding the selection options
    selectForm = null;

    // 2023-07-05 obsolete?!
    personID = null;
    // DOM ID of the item's form section
    elmtID = null;
    // index of the item's form section
    listIndex = null;

    connect() {
        // console.log('☕');
    };

    async openMergeQuery(event) {
	this.openBtn = event.currentTarget;
	return this.openModal(event);
    }

    // generic open modal function
    async openModal(event) {
	event.preventDefault();
        this.modal = new Modal(this.modalTarget);
        this.modal.show();

	// fetch uses the current URL if it is called with an empty URL

	// fill modal
	const response = await fetch(this.openUrlValue);
	var modalBody = await response.text();
	this.modalBodyTarget.innerHTML = modalBody;
    }

    async submitOnEnter(event) {
	if (event.type == 'keyup' && event.key == 'Enter') {
	    return await this.submitQuery(event);
	}
	return null;
    }

    /**
     * return a symbol (indicate that server request is processed)
     */
    waitSymbol() {
	// <span><i class="mt-2 ms-2 fa-solid fa-rotate fa-spin"></i></span>
	var span = document.createElement("span");
	var symbol = document.createElement("i");
	symbol.classList.add("mt-2", "ms-2", "fa-solid", "fa-rotate", "fa-spin");
	span.appendChild(symbol);
	return span;
    }

    /**
     * submit query and show choice list
     */
    async submitQuery(event, msg = null) {

	event.preventDefault();

	var body = new URLSearchParams(new FormData(event.target.form));
	// include name - value pairs of page navigation buttons
	const target_name = event.target.name;
	const target_value = event.target.value;
	if (event.target.tagName == "BUTTON" && target_name != "" && target_value != "") {
	    // console.log(btn.name, btn.value);
	    body.append(target_name, target_value);
	}

	// this works for input Elements (submit via ENTER), page navigation buttons and submit buttons
	const url = event.target.form.action;

	this.modalBodyTarget.replaceChildren(this.waitSymbol());

	const response = await fetch(url, {
	    method: "POST",
	    body: body,
	});


	var modalBody = await response.text();

	this.modalBodyTarget.innerHTML = modalBody;
	// read form
	const form_list = this.modalBodyTarget.getElementsByTagName("form");
	if (form_list.length > 1) {
	    this.selectForm = form_list.item(1);
	}

	if (msg !== null) {
	    var msg_elmt = this.modalBodyTarget.getElementsByClassName('wiag-form-warning').item(0);
	    msg_elmt.innerHTML = msg;
	}
	return null;
    }

    async submitForm(event) {
	event.preventDefault();

	if (this.selectForm === null) {
	    // TODO insert message
	    console.log("select form is missing");
	    return null; // modal stays open
	}
	else {
	    var select_form_data = new FormData(this.selectForm);
	}

	// issue a warning if no selection was made
	if (!select_form_data.has('selected')) {
	    // TODO insert message
	    console.log("no selection found");
	    return null; // modal stays open
	}

	const selected = select_form_data.get('selected')

	// this.openBtn was saved when the modal was created
	var body = new URLSearchParams(new FormData(this.openBtn.form));
	var url = this.openBtn.getAttribute("formaction");
	if (selected) {
	    var q_params = new URLSearchParams({ 'selected': selected });
	    url = url + "?" + q_params.toString();
	}
	const response = await fetch(url, {
	    method: "POST",
	    body: body,
	});

	this.modal.hide();

	// this is specific for a local merge operation
	// and can be made optional by a parameter if neccessary
	const wrap_element = document.createElement("div");
	wrap_element.innerHTML = await response.text();
	this.openBtn.form.replaceWith(wrap_element.firstElementChild);

    }

    /**
     * 2023-07-04 not in use: merge status is only known after successful saving
     * remove form element from DOM when it corresponds to idInSource
     */
    eatByIdInSource(idInSource, formList) {
	var target = null;
	for (target of formList.children) {
	    if (target.dataset.idInSource == idInSource) {
		break;
	    }
	}

	if (target !== null) {
	    target.remove();
	}
    }

    // 2023-07-05 open merge form in a new tab
    async submitForm_legacy(event) {
	const form_elmt = document.getElementById('merge_select_form');

	if (form_elmt === null) {
	    return await this.submitQuery(event);
	}

	var form_data = new FormData(form_elmt);

	// issue a warning if no selection was made
	if (!form_data.has('merge_select')) {
	    var msg = "Es ist kein Personeneintrag ausgewählt."
	    return await this.submitQuery(event, msg);
	}
	// console.log(form_data.get('merge_select'));

	// create new entry
	const url_comp_list = [
	    this.mergeItemUrlValue,
	    this.personID,
	    form_data.get('merge_select')
	];
	var url = url_comp_list.join('/');

	this.modal.hide();
	window.open(url, '_blank');

	return null;
    }


}
