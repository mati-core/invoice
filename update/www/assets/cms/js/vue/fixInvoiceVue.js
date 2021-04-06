let app = new Vue({
	el: '#app',
	data: {
		invoice: {
			id: null,
			type: 'fixInvoice',
			invoiceId: '',
			number: '',
			orderNumber: '',
			rentNumber: '',
			contractNumber: '',
			currency: 'CZK',
			currencyData: {
				id: null,
				code: 'CZK',
				symbol: 'Kč',
				rate: 1,
				rateDate: '???',
			},
			payMethod: 'bank',
			date: '2020-01-01',
			dateTax: '2020-01-01',
			dateDue: '2020-01-14',
			dateDueSelect: '30',
			priceDif: 0.0,
			priceDifFormatted: '0 Kč',
			totalPriceWithoutTax: 0.0,
			totalPriceWithoutTaxFormatted: '0 Kč',
			totalTax: 0.0,
			totalTaxFormatted: '0 Kč',
			totalPrice: 0.0,
			totalPriceFormatted: '0 kč',
			totalPriceRounded: 0.0,
			totalPriceRoundedFormatted: '0 Kč',
			defaultUnit: '',
			textBeforeItems: '',
			textAfterItems: '',
			company: {
				id: null,
				name: '...',
				address: '...',
				city: '...',
				zipCode: '...',
				ic: '...',
				dic: '...'
			},
			customer: {
				id: null,
				name: '',
				address: '',
				city: '',
				zipCode: '',
				ic: '',
				dic: ''
			},
			items: [],
			deposit: [],
			taxList: [],
		},
		depositNumber: '',
		showAlert: false,
		alertText: 'test',
		alertType: 'alert-success',
	},
	methods: {
		updateDateDue: function () {
			let val = this.invoice.dateDueSelect;
			let invoiceDateTax = this.invoice.dateTax;

			if (val !== '') {
				let date = new Date(Date.parse(invoiceDateTax + 'T00:00:00'));
				let increase = parseInt(this.invoice.dateDueSelect);
				date.setTime(date.getTime() + (increase * 24 * 60 * 60 * 1000));
				let m = date.getMonth() + 1;
				if (m < 10) {
					m = '0' + m;
				}
				let d = date.getDate();
				if (d < 10) {
					d = '0' + d;
				}
				this.invoice.dateDue = date.getFullYear() + '-' + m + '-' + d;
			}
		},
		setDate: function(e, item){
			if (item !== undefined) {
				this.invoice.date = item.value;
			}
		},
		setDateDue: function (e, item) {
			if (item !== undefined) {
				this.invoice.dateDue = item.value;
			}
		},
		setDateTax: function (e, item) {
			if (item !== undefined) {
				this.invoice.dateTax = item.value;
				this.updateDateDue();
				this.changeCurrency();
			}
		},
		changeCurrency: function () {
			fetch('/api/v1/invoice/loadCurrency', {
				method: 'POST',
				body: JSON.stringify(
					{
						code: this.invoice.currency,
						invoiceData: this.invoice,
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.invoice.currencyData = responseData.data.currency;
						this.calculateTotalPrice();
					} else {
						let msg = responseData.data.msg;
						if (msg === undefined || msg === '') {
							msg = 'Při komunikaci nastala chyba.';
						}

						this.flashMsg(msg, 'alert-danger');
					}
				})
				.catch(error => {
					console.log(error)
				});
		},
		updateData: function (event) {
			this.calculateTotalPrice();
		},
		calculateTotalPrice: function () {
			let totalPriceWithoutTax = 0.0;
			let totalTax = 0.0;

			let taxValues = [];

			let rate = this.invoice.currencyData.rate;

			this.invoice.items.forEach(function (item, index, array) {
				item.count = parseFloat(item.countString.replace(',', '.'));

				let price = parseFloat(item.count) * parseFloat(item.price);
				let tax = parseFloat(item.tax);
				price = Math.round(price * 100) / 100;
				item.totalPrice = price;
				item.totalPriceString = price.toString().replace('.', ',');

				if(item.sale > 0){
					item.salePrice = -((item.totalPrice / 100) * item.sale);
					item.salePriceString = item.salePrice.toString();
				}else{
					item.salePrice = 0;
					item.salePriceString = '0';
				}

				let exists = false;
				let priceCZE = (price + item.salePrice) * rate;
				taxValues.forEach(function (i, k, a) {
					if (i.tax === tax) {
						exists = true;
						i.valueWithoutTax = Math.round((i.valueWithoutTax + priceCZE) * 100) / 100;
						i.valueWithoutTaxFormatted = i.valueWithoutTax.toString().replace('.', ',') + ' Kč';
						let v = (i.valueWithoutTax / 100) * tax;
						i.value = Math.round(v * 100) / 100;
						i.valueFormatted = i.value.toString().replace('.', ',') + ' Kč';
					}
				});

				if (!exists) {
					let val = (priceCZE / 100) * tax;
					val = Math.round(val * 100) / 100;
					let valWithoutTax = Math.round(priceCZE * 100) / 100;
					let taxData = {
						tax: tax,
						value: val,
						valueFormatted: val.toString().replace('.', ',') + ' Kč',
						valueWithoutTax: valWithoutTax,
						valueWithoutTaxFormatted: valWithoutTax.toString().replace('.', ',') + ' Kč',
					};
					taxValues.push(taxData);
				}
			});

			this.invoice.taxList = taxValues;
			this.invoice.taxList.forEach(function (item, index, array) {
				totalPriceWithoutTax += (item.valueWithoutTax / rate);
				totalTax += (item.value / rate);
			});

			totalPriceWithoutTax = Math.round(totalPriceWithoutTax * 100) / 100;
			totalTax = Math.round(totalTax * 100) / 100;

			this.invoice.deposit.forEach(function (item, index, array) {
				totalPriceWithoutTax += item.itemPrice;
				totalTax += item.tax;
			});

			this.invoice.totalPriceWithoutTax =totalPriceWithoutTax;
			this.invoice.totalTax = totalTax;

			this.invoice.totalPriceWithoutTaxFormatted = this.invoice.totalPriceWithoutTax.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;
			this.invoice.totalTaxFormatted = this.invoice.totalTax.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;

			let totalPrice = this.invoice.totalPriceWithoutTax + this.invoice.totalTax;

			this.invoice.totalPrice = Math.round(totalPrice * 100) / 100;
			this.invoice.totalPriceFormatted = this.invoice.totalPrice.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;

			if (this.invoice.currency === 'CZK') {
				let tempTotalPrice = this.invoice.totalPrice;
				tempTotalPrice = -tempTotalPrice;
				tempTotalPrice = Math.round(tempTotalPrice);
				tempTotalPrice = -tempTotalPrice;

				let totalPriceRounded = tempTotalPrice;

				this.invoice.priceDif = Math.round((totalPriceRounded - this.invoice.totalPrice) * 100) / 100;
				this.invoice.priceDifFormatted = this.invoice.priceDif.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;

				this.invoice.totalPriceRounded = totalPriceRounded;
				this.invoice.totalPriceRoundedFormatted = this.invoice.totalPriceRounded.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;
			} else {
				let totalPriceRounded = this.invoice.totalPrice;

				this.invoice.priceDif = 0;
				this.invoice.priceDifFormatted = '0 ' + this.invoice.currencyData.symbol;

				this.invoice.totalPriceRounded = totalPriceRounded;
				this.invoice.totalPriceRoundedFormatted = this.invoice.totalPriceRounded.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;
			}
		},
		save: function () {
			fetch('/api/v1/invoice/save', {
				method: 'POST',
				body: JSON.stringify(
					{
						invoiceData: this.invoice
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.invoice = responseData.data.invoice;
						this.calculateTotalPrice();

						let redirect = responseData.data.redirect;
						if (redirect !== undefined) {
							window.location.href = redirect;
						}
					} else {
						let msg = responseData.data.msg;
						if (msg === undefined || msg === '') {
							msg = 'Při komunikaci nastala chyba.';
						}

						this.flashMsg(msg, 'alert-danger');
					}
				})
				.catch(error => {
					console.log(error)
				});
		},
		addDeposit: function () {
			fetch('/api/v1/invoice/depositInvoice', {
				method: 'POST',
				body: JSON.stringify(
					{
						invoiceData: this.invoice,
						depositNumber: this.depositNumber,
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.invoice = responseData.data.invoice;
						this.calculateTotalPrice();
						this.depositNumber = '';
					} else {
						let msg = responseData.data.msg;
						if (msg === undefined || msg === '') {
							msg = 'Při komunikaci nastala chyba.';
						}

						this.flashMsg(msg, 'alert-danger');
					}
				})
				.catch(error => {
					console.log(error)
				});

			$('#depositModal').modal('hide');
		},
		removeDeposit: function (id) {
			this.invoice.deposit.splice(id, 1);
			this.calculateTotalPrice();

			return false;
		},
		flashMsg: function (msg, type) {
			console.log(msg);
			console.log(type);
			this.alertText = msg;
			this.alertType = type;
			this.showAlert = true;
		},
		hideAlert: function () {
			this.showAlert = false;
		},
		bText: function(text, prefix){
			return prefix + text;
		},
	},
	created: function () {
		this.invoice.id = document.getElementById('app-data').getAttribute('data-invoiceId');
		fetch('/api/v1/invoice/loadFixInvoice', {
			method: 'POST',
			body: JSON.stringify(
				{
					id: this.invoice.id
				}
			)
		})
			.then(response => response.json())
			.then(responseData => {
				this.invoice = responseData.data.invoice;
				this.calculateTotalPrice();
			})
			.catch(error => {
				console.log(error)
			});
	}
});