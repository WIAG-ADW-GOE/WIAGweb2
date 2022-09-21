import { Controller } from 'stimulus';

export default class extends Controller {
    connect() {
	//console.log('move-cursor');
    }

    moveStart (event) {
	event.target.setSelectionRange(0, 0);
    }
}
