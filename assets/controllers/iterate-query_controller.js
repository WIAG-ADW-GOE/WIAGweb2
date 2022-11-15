import { Controller } from 'stimulus';

export default class extends Controller {
    static targets = ['status', 'result'];
    static values = {
	url: String,
	chunkSize: String
    };

    connect() {
	// console.log('this is update-db');
    }


    async start() {
	var offset = 0;
	var total_count = 0;
	var new_count = 0;


	// debug
	// this.chunkSizeValue = 10;
	const max_offset = 10000;

	while(true) {
	    var url_params = new URLSearchParams({
	    'chunkSize': this.chunkSizeValue,
	    'offset': offset,
	    });
	    var url = this.urlValue + '?' + url_params.toString();
            const response = await fetch(url, {
		method: 'GET'
            });

            // this.dispatch('async:submitted', {
            //     response,
            // })

	    if (response.status == '200' || response.status == '240') {
		this.statusTarget.innerHTML = await response.text();
	    } else {
		this.statusTarget.innerHTML = "Serveranfrage fehlgeschlagen: " + response.status;
	    }
	    offset += parseInt(this.chunkSizeValue, 10);

	    // sum up number of new elements
	    new_count = this.accumulateByClassName(new_count, "new-count");

	    if (offset >= max_offset) {
		console.log('Abbruch: Obergrenze erreicht: ' + max_offset);
		break;
	    }

	    // e.g. application specific status 240
	    if (response.status > 200) {
		break;
	    }
	}

    }

    accumulateByClassName(total, class_name) {
	var elmt_list = this.statusTarget.getElementsByClassName(class_name);

	if (elmt_list.length > 0) {
	    let acc_elmt = elmt_list.item(0);
	    total += parseInt(acc_elmt.innerHTML);
	    acc_elmt.innerHTML = total;
	}

	return total;
    }


}
