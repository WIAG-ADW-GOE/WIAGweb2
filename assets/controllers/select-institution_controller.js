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
     */
    toggle(event) {
	// console.log(this.carrierTargets[0].style.width)
	// console.log(this.carrierTargets[1].style.width);
	const show_idx = event.currentTarget.value;
	const hide_idx = 1 - show_idx;
	this.carrierTargets[show_idx].style.width = this.baseWidthValue;
	this.carrierTargets[hide_idx].style.width = '0em';
    }

    /**
     * clear content of the related element
     */
    clear(event) {
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

    select(event) {
	// const event_target = event.currentTarget;
	// const options = event_target.options;
	// const elmt_selected = Array.from(options).filter(elmt => elmt.selected)[0];
	// // console.log(elmt_selected);

	// const selected_idx = elmt_selected.dataset.index;
	// for (let elmt of this.carrierTargets) {
	//     if (elmt.dataset.index == selected_idx) {
	// 	elmt.style.width = this.baseWidthValue;
	//     } else {
	// 	elmt.style.width = '0em';
	//     }
	// }

	const options = event.currentTarget.options;
	const elmt_selected = Array.from(options).filter(elmt => elmt.selected)[0];
	const url = elmt_selected.dataset.url;
	// console.log(url);
	this.carrierTarget.dataset.autocompleteUrlValue = url;

    }
}
