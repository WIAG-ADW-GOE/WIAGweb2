import { Controller } from 'stimulus';

/**
 * disable or enable inputs and buttons
 * 2023-01-30 This controller was used to mark items that should be
 * deleted.
 * Now, deletion is done directly after a confirmation dialogue.
 */
export default class extends Controller {
    static targets = ['globalTrigger', 'disable'];

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
	var disable_root = this.hasDisableTarget ? this.disableTarget : this.element;
	var toggle_list = Array.from(disable_root.getElementsByTagName('input'));
	var toggle_list_btn = Array.from(disable_root.getElementsByTagName('button'));

	toggle_list = toggle_list.concat(toggle_list_btn);

	if (event.target.checked) {
	    for (let elmt of toggle_list) {
		if (elmt == event.target
		    || elmt.hasAttribute('hidden')
		    || elmt.dataset.bsToggle) {
		    continue;
		}
		// do not disable 'ID' fields.
		let id_part_list = elmt.id.split('_');
		if (id_part_list.length > 0 && id_part_list.slice(-1) == 'id') {
		    continue;
		}
		elmt.setAttribute('disabled', 'disabled');
	    }
	} else {
	    for (let elmt of toggle_list) {
		elmt.removeAttribute('disabled');
	    }
	    this.restore(toggle_list);
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

    /**
     * 2022-10-12 obsolete; used for a pair of radio buttons
     */
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
    restore(disabled_list) {
	for (let elmt of disabled_list) {
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
