import { Controller } from 'stimulus';

export default class extends Controller {
    connect() {
	console.log('this is clipboard');
    }

    copyTitle(event) {
	const title = this.element.getAttribute('title');
	this.copyToClipboard(title)
	setTimeout(() => {this.element.blur();}, 600);
    }

    copyCitation(event) {
	const text = document.getElementById('citation').innerHTML;
	this.copyToClipboard(text)
	setTimeout(() => {this.element.blur();}, 600);
    }

    copyToClipboard(text) {
	if (navigator.clipboard) {
	    navigator.clipboard.writeText(text);
	} else {
	    console.log('Clipboard API is not available. Check if you are in a secure context (HTTPS) or configurate your browser.');
	}
    }
}
