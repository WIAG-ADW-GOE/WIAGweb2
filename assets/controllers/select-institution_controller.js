import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['carrier'];

    static values = {
	baseWidth: String,
    };

    connect() {
	// console.log('this is switch-institution');
    }

    /**
     * toggle carrierTargets
     * obsolete 2022-11-29
     */
    toggle_hide(event) {
	// console.log(this.carrierTargets[0].style.width)
	// console.log(this.carrierTargets[1].style.width);
	const show_idx = event.currentTarget.value;
	const hide_idx = 1 - show_idx;
	this.carrierTargets[show_idx].style.width = this.baseWidthValue;
	this.carrierTargets[hide_idx].style.width = '0em';
    }

    /**
     * clear content of the related element
     * obsolete 2022-11-29
     */
    clear_hide(event) {
	const event_target = event.currentTarget; // div
	// old version
	// var clear_idx = 0;
	// if (event.currentTarget == this.carrierTargets[clear_idx]) {
	//     clear_idx = 1 - clear_idx;
	// }
	// var clear_input = this.carrierTargets[clear_idx].getElementsByTagName('input')[0];
	// // console.log(clear_input);
	// clear_input.value = "";
	const current_index = event_target.dataset.index;
	console.log(current_index)
	// console.log(this.carrierTargets.length)
	for (let carrier of this.carrierTargets) {
	    if (carrier.dataset.index != current_index) {
		carrier.children.item(0).value = "";
	    }
	}

    }

    /**
     * set autocomplete URL for different institution types
     */
    select(event) {

	const options = event.currentTarget.options;
	const elmt_selected = Array.from(options).filter(elmt => elmt.selected)[0];
	const url = elmt_selected.dataset.url;
	// console.log(url);
	this.carrierTarget.dataset.autocompleteUrlValue = url;

    }
}
