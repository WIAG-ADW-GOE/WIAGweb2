import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
	//console.log('move-cursor');
    }

    // e.g. for blur-events
    moveStart(event) {
	event.target.setSelectionRange(0, 0);
    }
}
