import { Controller } from 'stimulus';


export default class extends Controller {
    static values = {
	state: Number,
    };

    connect() {
	// console.log('connect toggle-arrow');
	// console.log(this.stateValue);
	if (this.stateValue == 1) {
	    this.element.click();
	}
    }

    toggle(event) {
	const dataValue = this.element.getAttribute('data-value');
	var html = this.element.innerHTML;
	if (dataValue == 'down') {
	    this.element.setAttribute('data-value', 'up');
	    var newHTML = html.replace('arrow-down', 'arrow-up');
	    this.element.innerHTML = newHTML;
	} else {
	    this.element.setAttribute('data-value', 'down');
	    var newHTML = html.replace('arrow-up', 'arrow-down');
	    this.element.innerHTML = newHTML;
	}
    }
}
