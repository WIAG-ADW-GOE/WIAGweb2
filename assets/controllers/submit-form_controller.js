import { Controller } from 'stimulus';

export default class extends Controller {
    connect() {
	console.log('this is submit-form');
    }

    submit(event) {
	console.log(event.type);
    }
}
