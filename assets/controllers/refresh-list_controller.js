import { Controller } from 'stimulus';

/**
 * browse result list
 * (not in use, because concept is not easily applicable to detail views)
 */
export default class extends Controller {
    static targets = ['hitcount', 'fbButtons', 'list'];

    static values = {
	count: Number,
	offset: Number,
	pageSize: Number,
	url: String,
    };

    connect() {
	console.log('this is refresh-list: ', this.countValue);

    }

    async update(event) {
	if (event.target.id == 'forward') {
	    console.log('forward');
	    this.offsetValue += this.pageSizeValue;
	    console.log(event.target);
	} else if (event.target.id == 'backward') {
	    console.log('backward');
	    this.offsetValue -= this.pageSizeValue;
	    console.log(event.target);
	}

	var params = new URLSearchParams(new FormData(this.element));
	params.append('offset', this.offsetValue);

	var response = await fetch(this.urlValue, {
		method: this.element.method,
		body: params,
	});

	this.listTarget.innerHTML = await response.text();
	this.setunsetDisabled(event)
	this.hitcountTarget.innerText = this.hitcountText();


    }

    setunsetDisabled(event) {
	for (let group of this.fbButtonsTargets) {
	    var buttons = group.getElementsByTagName('button');
	    if (this.countValue < this.offsetValue + this.pageSizeValue) {
		buttons[1].setAttribute('disabled', 'disabled');
	    } else {
		buttons[1].removeAttribute('disabled');
	    }

	    if (this.offsetValue < this.pageSizeValue) {
		buttons[0].setAttribute('disabled', 'disabled');
	    } else {
		buttons[0].removeAttribute('disabled');
	    }
	}
    }

    /**
     * return something like '61 - 80 von 217'
     */
    hitcountText() {
	console.log(this.pageSizeValue, ' ', this.offsetValue);
	return (this.offsetValue + 1) + ' - '
	    + Math.min(this.offsetValue + this.pageSizeValue, this.countValue)
	    + ' von ' + this.countValue;
    }

}
