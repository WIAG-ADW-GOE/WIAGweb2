import { Controller } from 'stimulus';


export default class extends Controller {
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
	 * console.log(this.element.getAttribute('name')); // bishop_query_form
	 * state element: bishop_query_form_stateFctDioc
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
     * clear facets when a new search is prepared
     */
    clearFacet(event) {
	var targets = this.element.getElementsByClassName('facet-check');
	const eventTargetType = event.target.getAttribute('type');
	if (eventTargetType == 'text') {
	    for (let target of targets) {
		target.removeAttribute('checked');
	    }
	}
    }

}
