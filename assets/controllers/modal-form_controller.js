import { Controller } from 'stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['modal', 'modalBody'];
    static values = {
	queryUrl: String,
	mergeItemUrl: String,
    };

    modal = null;
    personID = null;
    // DOM ID of the item's form section
    elmtID = null;
    // index of the item's form section
    listIndex = null;

    connect() {
        // console.log('☕️');
    };

    async openModal(event) {
        this.modal = new Modal(this.modalTarget);
	this.elmtID = event.currentTarget.value;
	this.personID = event.currentTarget.dataset.personId;
	this.listIndex = event.currentTarget.dataset.listIndex;
        this.modal.show();

	// fetch uses the current URL if it is called with an empty URL

	// fill modal
	const response = await fetch(this.queryUrlValue);
	var modalBody = await response.text();
	this.modalBodyTarget.innerHTML = modalBody;
    }

    async submitQuery(event, msg = null) {
	event.preventDefault();
	var form_elmt = this.modalBodyTarget.getElementsByTagName('form').item(0);

	const response = await fetch(this.queryUrlValue, {
	    method: form_elmt.method,
	    body: new URLSearchParams(new FormData(form_elmt)),
	});

	var modalBody = await response.text();
	this.modalBodyTarget.innerHTML = modalBody;
	if (msg !== null) {
	    var msg_elmt = this.modalBodyTarget.getElementsByClassName('wiag-form-warning').item(0);
	    msg_elmt.innerHTML = msg;
	}

	return null;

    }

    async submitForm(event) {
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
	console.log(form_data.get('merge_select'));

	// new 2022-11-25; create new entry
	const url_comp_list = [
	    this.mergeItemUrlValue,
	    this.personID,
	    this.listIndex,
	    form_data.get('merge_select')
	];
	var url = url_comp_list.join('/');

	this.modal.hide();
	window.location.assign(url);

	return null;

	// legacy 2022-11-25

	var request_body = new URLSearchParams(form_data);
	await this.updateEntry(request_body);

	this.modal.hide();
    }

    /**
     * updateEntry(form_elmt)
     *
     * fetch data for the current item and the merge item; update form section for item
     */
    async updateEntry(request_body) {
	// find the form section to update
	const entry_elmt = document.getElementById(this.elmtID);

	// wrapper
	const wrap = document.createElement("div");
	// get new data from the server
	var url = this.mergeItemUrlValue + '/' + this.personID + '/' + this.listIndex;

	const response = await fetch(url, {
	    method: "POST",
	    body: request_body,
	});

	wrap.innerHTML = await response.text();
	entry_elmt.firstElementChild.innerHTML = wrap.firstElementChild.firstElementChild.innerHTML;
    }

    async mergeItem(event) {
	this.elmtID = event.currentTarget.value;
	this.personID = event.currentTarget.dataset.personId;
	this.listIndex = event.currentTarget.dataset.listIndex;

	const merge_select = event.currentTarget.dataset.mergeSelect;

	var request_body = new URLSearchParams({"merge_select": merge_select});

	await this.updateEntry(request_body);
    }

}
