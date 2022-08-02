import { Controller } from 'stimulus';

// export default class extends Controller {
//     static values = {
// 	state: Number, // 0
//     };

//     connect() {
// 	// console.log('connect toggle-arrow');
// 	// console.log(this.stateValue);
// 	if (this.stateValue == 1) {
// 	    this.element.click();
// 	}
//     }

//     toggle(event) {
// 	const dataValue = this.element.getAttribute('data-value');
// 	var html = this.element.innerHTML;
// 	if (dataValue == 'down') {
// 	    this.element.setAttribute('data-value', 'up');
// 	    var newHTML = html.replace('arrow-down', 'arrow-up');
// 	    this.element.innerHTML = newHTML;
// 	} else {
// 	    this.element.setAttribute('data-value', 'down');
// 	    var newHTML = html.replace('arrow-up', 'arrow-down');
// 	    this.element.innerHTML = newHTML;
// 	}
//     }
// }

export default class extends Controller {
    static targets = ['button'];
    static values = {
	state: Number, // 0
	imgClosed: String,
	imgOpen: String
    };

    connect() {
	// console.log('connect toggle-arrow');
	console.log(this.stateValue);
	if (this.stateValue == 1) {
	    this.element.click();
	}
    }

    toggle(event) {
	const dataValue = this.element.getAttribute('data-value');
	var html = this.element.innerHTML;
	if (dataValue == 'down') {
	    this.element.setAttribute('data-value', 'up');
	    var newHTML = html.replace(this.imgClosedValue, this.imgOpenValue);
	    this.element.innerHTML = newHTML;
	} else {
	    this.element.setAttribute('data-value', 'down');
	    var newHTML = html.replace(this.imgOpenValue, this.imgClosedValue);
	    this.element.innerHTML = newHTML;
	}
    }

        // controller is attached to an element above the button
    // event: shown.bs.collapse
    shown(event) {
	var html = this.buttonTarget.innerHTML;
	var newHTML = html.replace(this.imgClosedValue, this.imgOpenValue);
	this.buttonTarget.innerHTML = newHTML;
    }


    // controller is attached to an element above the button
    // event: hidden.bs.collapse
    hidden(event) {
	var html = this.buttonTarget.innerHTML;
	var newHTML = html.replace(this.imgOpenValue, this.imgClosedValue);
	this.buttonTarget.innerHTML = newHTML;
    }

}
