import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['globalTrigger'];

    connect() {
	// console.log('input-state connected');
	// set display state (disabled or not) independent from user action
	// document.addEventListener('DOMContentLoaded', (event) => {
	//     // console.log('DOM fully loaded and parsed');
	//     if (this.hasGlobalTriggerTarget) {
	// 	let targetElmt = this.globalTriggerTarget
	// 	if (targetElmt.checked) {
	// 	    this.disableByElement(targetElmt);
	// 	}
	//     }
	// });
    }


    /**
     * disable or enable inputs and buttons with checkbuttons
     */
    toggle(event) {

	var toggle_list = Array.from(this.element.getElementsByTagName('input'));
	var toggle_list_btn = Array.from(this.element.getElementsByTagName('button'));
	toggle_list = toggle_list.concat(toggle_list_btn);

	if (event.target.checked) {
	    for (let elmt of toggle_list) {
		if (elmt == event.target
		    || elmt.hasAttribute('hidden')
		    || elmt.dataset.bsToggle) {
		    continue;
		}
		elmt.setAttribute('disabled', 'disabled');
	    }
	} else {
	    for (let elmt of toggle_list) {
		elmt.removeAttribute('disabled');
	    }
	    this.restore();
	}
    }

    /**
     * disable inputs and buttons e.g. with radio button
     */
    disable(event) {
	// relevant if entry is reloaded
	if (event.target.tagName == 'DIV') {
	    if (this.hasGlobalTriggerTarget && this.globalTriggerTarget.checked) {
		return this.disableByElement(this.globalTriggerTarget);
	    }
	} else {
	    return this.disableByElement(event.target);
	}
    }

    disableByElement(element) {
	var toggle_list = Array.from(this.element.getElementsByTagName('input'));
	var toggle_list_btn = Array.from(this.element.getElementsByTagName('button'));
	toggle_list = toggle_list.concat(toggle_list_btn);

	// exclude related radio button
	const target_id_root = element.id.split('_').slice(0, -1).join('_');

	for (let elmt of toggle_list) {
	    // exclude related radio button
	    var elmt_id_root = elmt.id.split('_').slice(0, -1).join('_');
	    if (elmt == element
		|| elmt.hasAttribute('hidden')
		|| elmt.dataset.bsToggle
		|| target_id_root == elmt_id_root) {
		continue;
	    }
	    elmt.setAttribute('disabled', 'disabled');
	}

    }

    shown() {
	// console.log('shown');
	// console.log(event.target.id);
    }

    enable(event) {
	var toggle_list = Array.from(this.element.getElementsByTagName('input'));
	var toggle_list_btn = Array.from(this.element.getElementsByTagName('button'));
	toggle_list = toggle_list.concat(toggle_list_btn);

	for (let elmt of toggle_list) {
	    elmt.removeAttribute('disabled');
	}

	// restore the state (enabled/disabled) of elements in the tree
	this.restore();
    }

    /**
     * restore the state (enabled/disabled) of elements in the tree
     */
    restore() {
	// get nested triggers
	var trigger_list = this.element.getElementsByClassName('form-check-input');

	for (let elmt of trigger_list) {
	    if (elmt.getAttribute('type') == 'checkbox') {
		let elmt_id_tail = elmt.id.split('_').slice(-1);
		// the content of tail is hardcoded, could be parametrised in `values`.
		if (elmt_id_tail.length > 0 && elmt_id_tail[0] == 'delete') {
		    // easiest way to restore 'status ante'
		    elmt.click();
		    elmt.click();
		}
	    }
	}
    }
}
