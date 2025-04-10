import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['click'];

    connect() {
	// console.log('trigger connected')
    }

    click() {
	console.log('trigger#click');
	this.clickTarget.click();
    }

}
