import { Controller } from 'stimulus';
import { useDispatch } from 'stimulus-use';
import Swal from 'sweetalert2';

export default class extends Controller {
    static values = {
	title: String,
	text: String,
	icon: String,
	confirmButtonText: String,
	buttonId: String,
    };

    connect() {
	// console.log('submit-confirm');
	useDispatch(this);
    }

    confirm(event) {
	event.preventDefault();
	console.log('button color');
	Swal.fire({
	    title: this.titleValue || null,
	    text: this.textValue || null,
	    icon: this.iconValue || 'warning',
	    showCancelButton: true,
	    confirmButtonColor: '#d33',
	    cancelButtonColor: '#3085d6',
	    cancelButtonText: this.cancelButtonTextValue || 'Abbrechen',
	    confirmButtonText: this.confirmButtonTextValue || 'Ja',
	    showLoaderOnConfirm: false,
	    preConfirm: () => {
		this.submitForm(event);
	    }
	});
    }

    submitForm(event) {
	// Confirm is attached to a sibbling of the button/link that triggers the action.
	// Thus recursive loops for confirmation are avoided and ENTER
	// does not bypass the confirmation.
	var btn = document.getElementById(this.buttonIdValue);
	btn.click();
    }

}
