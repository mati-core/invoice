let app = new Vue({
	el: '#app',
	data: {
		invoice: {
			id: null,
			type: 'invoice',
			number: '20010000',
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
			date: '2021-01-01',
			dateTax: '2021-01-01',
			dateDue: '2021-01-14',
			dateDueSelect: '14',
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
			defaultTax: 0.0,
			textBeforeItems: '',
			textAfterItems: '',
			company: {
				id: null,
				name: '...',
				address: '...',
				city: '...',
				zipCode: '...',
				cin: '',
				tin: ''
			},
			customer: {
				id: null,
				name: '',
				address: '',
				city: '',
				zipCode: '',
				cin: '',
				tin: ''
			},
			items: [
				{
					id: null,
					countString: '1',
					count: 1,
					unit: '',
					description: '',
					saleDescription: 'Sleva',
					saleString: '0',
					sale: 0,
					salePrice: 0,
					salePriceString: 0,
					taxString: '21',
					tax: 21.0,
					priceString: '0',
					price: 0,
					buyPrice: null,
					buyCurrency: {
						id: null,
						symbol: '???',
						rate: 30.0
					},
					totalPriceString: '0',
					totalPrice: 0,
				}
			],
			deposit: [],
			taxList: [],
			taxEnabled: false,
			offers: [],
		},
		depositNumber: '',
		showAlert: false,
		alertText: 'test',
		alertType: 'alert-success',
		saveBtnDisabled: false,
	},
	methods: {
		setItemPosition: function (item, position) {
			this.array_move(this.invoice.items, item, position);
		},
		array_move: function (arr, old_index, new_index) {
			if (new_index >= arr.length) {
				let k = new_index - arr.length + 1;
				while (k--) {
					arr.push(undefined);
				}
			}
			arr.splice(new_index, 0, arr.splice(old_index, 1)[0]);
		},
		addItem: function () {
			this.invoice.items.push({
				id: null,
				countString: '1',
				count: 1,
				unit: this.invoice.defaultUnit,
				description: '',
				saleDescription: 'Sleva',
				saleString: '0',
				sale: 0,
				salePrice: 0,
				salePriceString: 0,
				taxString: this.invoice.defaultTax.toString().replace('.', ','),
				tax: this.invoice.defaultTax,
				priceString: '0',
				price: 0,
				buyPrice: null,
				buyCurrency: {
					id: null,
					symbol: '???',
					rate: 30.0
				},
				totalPriceString: '0',
				totalPrice: 0,
			});

			return false;
		},
		removeItem: function (id) {
			this.invoice.items.splice(id, 1);
			this.calculateTotalPrice();
		},
		loadCompanyByIc: function () {
			fetch('/api/v1/invoice/loadCompanyByCIN', {
				method: 'POST',
				body: JSON.stringify(
					{
						cin: this.invoice.customer.cin
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.invoice.customer = responseData.data.customer;
						this.invoice.currency = responseData.data.currency;
						this.invoice.date = responseData.data.date;
						this.invoice.dateTax = responseData.data.dateTax;
						this.invoice.dateDue = responseData.data.dateDue;
						this.invoice.dateDueSelect = responseData.data.dateDueSelect;
						this.changeCurrency();
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
		loadCompanyById: function (id) {
			fetch('/api/v1/invoice/loadCompanyById', {
				method: 'POST',
				body: JSON.stringify(
					{
						id: id
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.invoice.customer = responseData.data.customer;
						this.invoice.currency = responseData.data.currency;
						this.invoice.date = responseData.data.date;
						this.invoice.dateTax = responseData.data.dateTax;
						this.invoice.dateDue = responseData.data.dateDue;
						this.invoice.dateDueSelect = responseData.data.dateDueSelect;
						this.changeCurrency();
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
		updateDateDue: function () {
			let val = this.invoice.dateDueSelect;
			let invoiceDate = this.invoice.date;

			if (val !== '') {
				let date = new Date(Date.parse(invoiceDate + 'T00:00:00'));
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

			if(this.invoice.taxEnabled === false){
				this.invoice.dateTax = invoiceDate;
			}

			this.reloadInvoiceNumber();
		},
		setDate: function (e, item) {
			if (item !== undefined) {
				this.invoice.date = item.value;

				if(this.invoice.taxEnabled === false){
					this.setDateTax(e, item);
				}
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
		updateData: function (event) {
			this.calculateTotalPrice();
		},
		calculateTotalPrice: function () {
			let totalPriceWithoutTax = 0.0;
			let totalTax = 0.0;

			let taxValues = [];

			let rate = this.invoice.currencyData.rate;
			let currencySymbol = ' ' + this.invoice.currencyData.symbol;

			this.invoice.items.forEach(function (item, index, array) {
				item.count = parseFloat(item.countString.replace(',', '.'));
				item.tax = parseFloat(item.taxString.replace(',', '.'));
				item.price = parseFloat(item.priceString.replace(',', '.'));

				item.sale = Math.round(parseFloat(item.saleString.replace(',', '.')));
				item.saleString = item.sale.toString();


				let price = parseFloat(item.count) * parseFloat(item.price);
				let tax = parseFloat(item.tax);
				price = Math.round(price * 100) / 100;
				item.totalPrice = price;
				item.totalPriceString = price.toString().replace('.', ',');

				if (item.sale > 0) {
					item.salePrice = -((item.totalPrice / 100) * item.sale);
					item.salePriceString = item.salePrice.toString();
				} else {
					item.salePrice = 0;
					item.salePriceString = '0';
				}

				let exists = false;
				let priceCZE = (price + item.salePrice);
				taxValues.forEach(function (i, k, a) {
					if (i.tax === tax) {
						exists = true;
						i.valueWithoutTax = Math.round((i.valueWithoutTax + priceCZE) * 100) / 100;
						i.valueWithoutTaxFormatted = i.valueWithoutTax.toString().replace('.', ',') + currencySymbol;
						let v = (i.valueWithoutTax / 100) * tax;
						i.value = Math.round(v * 100) / 100;
						i.valueFormatted = i.value.toString().replace('.', ',') + currencySymbol;
					}
				});

				if (!exists) {
					let val = (priceCZE / 100) * tax;
					val = Math.round(val * 100) / 100;
					let valWithoutTax = Math.round(priceCZE * 100) / 100;
					let taxData = {
						tax: tax,
						value: val,
						valueFormatted: val.toString().replace('.', ',') + currencySymbol,
						valueWithoutTax: valWithoutTax,
						valueWithoutTaxFormatted: valWithoutTax.toString().replace('.', ',') + currencySymbol,
					};
					taxValues.push(taxData);
				}
			});

			this.invoice.taxList = taxValues;
			this.invoice.taxList.forEach(function (item, index, array) {
				totalPriceWithoutTax += (item.valueWithoutTax);
				totalTax += (item.value);
			});

			totalPriceWithoutTax = Math.round(totalPriceWithoutTax * 100) / 100;
			totalTax = Math.round(totalTax * 100) / 100;

			this.invoice.deposit.forEach(function (item, index, array) {
				totalPriceWithoutTax -= item.itemPrice;
				totalTax -= item.tax;
			});

			this.invoice.totalPriceWithoutTax = totalPriceWithoutTax;
			this.invoice.totalTax = totalTax;

			this.invoice.totalPriceWithoutTaxFormatted = this.invoice.totalPriceWithoutTax.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;
			this.invoice.totalTaxFormatted = this.invoice.totalTax.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;

			let totalPrice = this.invoice.totalPriceWithoutTax + this.invoice.totalTax;

			this.invoice.totalPrice = Math.round(totalPrice * 100) / 100;
			this.invoice.totalPriceFormatted = this.invoice.totalPrice.toString().replace('.', ',') + ' ' + this.invoice.currencyData.symbol;

			if (this.invoice.currency === 'CZK') {
				let totalPriceRounded = Math.round(this.invoice.totalPrice);

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
		changeCurrency: function () {
			fetch('/api/v1/invoice/loadCurrency', {
				method: 'POST',
				body: JSON.stringify(
					{
						isoCode: this.invoice.currency,
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
		reloadInvoiceNumber: function () {
			console.log('update number');
			fetch('/api/v1/invoice/reloadInvoiceNumber', {
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
		showBuyPrice: function (index) {
			let item = this.invoice.items[index];

			if (item.buyPrice > 0.0) {
				if (item.buyCurrency.id === null || this.invoice.currencyData.id === item.buyCurrency.id) {
					return this.roundPrice(item.buyPrice).toString() + ' ' + this.invoice.currencyData.symbol;
				} else {
					let price = item.buyPrice;

					if (this.invoice.currencyData.code === 'CZK') {
						price = price * 30.0;	//item.buyCurrency.rate; //FIX RATE 30.0
					} else {
						price = price / 26.0;	//item.buyCurrency.rate; //FIX RATE 26.0
					}

					return this.roundPrice(price).toString() + ' ' + this.invoice.currencyData.symbol;
				}
			}

			return '';
		},
		showBuyPriceTotal: function (index) {
			let item = this.invoice.items[index];

			if (item.buyPrice > 0.0) {
				if (item.buyCurrency.id === null || this.invoice.currencyData.id === item.buyCurrency.id) {
					return this.roundPrice(item.buyPrice).toString() + ' ' + this.invoice.currencyData.symbol;
				} else {
					let price = item.buyPrice;

					if (this.invoice.currencyData.code === 'CZK') {
						price = price * 30.0;	//item.buyCurrency.rate; //FIX RATE 30.0
					} else {
						price = price / 26.0;	//item.buyCurrency.rate; //FIX RATE 26.0
					}

					return this.roundPrice(price * item.count).toString() + ' ' + this.invoice.currencyData.symbol;
				}
			}

			return '';
		},
		roundPrice: function (price) {
			return Math.round(price * 100) / 100;
		},
		save: function () {
			this.saveBtnDisabled = true;
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
					this.saveBtnDisabled = false;
				})
				.catch(error => {
					console.log(error)
					this.saveBtnDisabled = false;
				});
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
		bText: function (text, prefix) {
			return prefix + text;
		},
	},
	created: function () {
		this.invoice.id = document.getElementById('app-data').getAttribute('data-invoiceId');
		fetch('/api/v1/invoice/loadInvoice', {
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