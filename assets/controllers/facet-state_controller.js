import { Controller } from 'stimulus';


export default class extends Controller {
    static targets = ['facets'];

    connect() {
	// console.log('connect facet-state');
    }

    /**
     * update the value of a hidden form element according to the state of
     * a collapsable facet.
     * event: one of 'shown.bs.collapse', 'hidden.bs.collapse'
     * see https://getbootstrap.com/docs/5.1/components/collapse/
     */
    register(event) {
	/** make use of
	 * console.log(event.target.id); // restFctDioc
	 * console.log(this.element.getAttribute('name'));
	 * state element: form_stateFctDioc
	 */

	const formName = this.element.getAttribute('name');
	var targetId = event.target.id;
	const stateElementId = formName + '_' + targetId.replace('rest', 'state');
	var stateElement = document.getElementById(stateElementId);

	if (event.type == 'shown.bs.collapse') {
	    stateElement.setAttribute('value', 1);
	} else {
	    stateElement.setAttribute('value', 0);
	}
    }

    /**
     * clear and collapse facets when a new search is prepared
     */
    clearFacet(event) {
	console.log('facet-state#clearFacet');
	// exclude page browsing
	const target_name = event.target.getAttribute('name');
	if (target_name == 'pageNumber') {
	    return
	}
	// clear and collapse
	var targets = this.facetsTarget.getElementsByTagName('input');
	// console.log('targets: ', targets.length);
	const eventTargetType = event.target.getAttribute('type');
	if (eventTargetType == 'text') {
	    // console.log('clear!?');
	    for (let target of targets) {
		target.removeAttribute('checked');
		// set collapse status used by toggle-arrow_controller
		var targetId = target.getAttribute('id');
		if (targetId.includes('stateFct')) {
		    target.setAttribute('value', 0);
		}
	    }
	}
    }

}
