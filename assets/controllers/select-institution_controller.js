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
	var clear_idx = 0;
	if (event.currentTarget == this.carrierTargets[clear_idx]) {
	    clear_idx = 1 - clear_idx;
	}
	var clear_input = this.carrierTargets[clear_idx].getElementsByTagName('input')[0];
	// console.log(clear_input);
	clear_input.value = "";
    }
}
