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

	    if (offset >= max_offset) {
		console.log('Abbruch: Obergrenze erreicht: ' + max_offset);
		break;
	    }

	    if (response.status > 200) {
		break;
	    }
	}
    }

}
