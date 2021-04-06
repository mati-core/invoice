let app = new Vue({
	el: '#app',
	data: {
		adminAccess: false,
		types: [
			{
				id: 'invoice',
				name: 'Přijatá faktura',
			},
			{
				id: 'default',
				name: 'Obecný výdaj',
			}
		],
		expense: {
			id: null,
			type: 'invoice',
			category: '',
			description: '',
			number: '20010000',
			invoiceNumber: '',
			variableSymbol: '',
			variableSymbolError: 0,
			deliveryType: 3,
			weight: '',
			productCode: '',
			customer: {
				name: '',
				address: '',
				city: '',
				zipCode: '',
				country: '',
				cin: '',
				tin: ''
			},
			currency: 'CZK',
			currencyData: {
				id: null,
				code: 'CZK',
				symbol: 'Kč',
				rate: 1,
				rateString: '1',
				rateReal: 1,
				rateRealString: '1',
				rateDate: '???',
			},
			payMethod: 'bank',
			date: '2020-01-01',
			dateError: false,
			dateData: {
				'year': 2020,
				'month': 1,
			},
			priceNoVat: 0.0,
			priceNoVatFormatted: '0',
			price: 0.0,
			priceFormatted: '0',
			tax: 0.0,
			taxFormatted: '0',
			datePrint: '',
			datePrintError: false,
			dateDue: '2020-01-14',
			dateDueError: false,
			datePay: '',
			datePayError: false,
			note: '',
			defaultUnit: '',
			hidden: false,
			items: [],
			itemTotalPrice: 0.0,
		},
		showAlert: false,
		alertText: 'test',
		alertType: 'alert-success',
	},
	methods: {
		setItemPosition: function (item, position) {
			this.array_move(this.expense.items, item, position);
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
			this.expense.items.push({
				id: null,
				countString: '1',
				count: 1,
				unit: this.expense.defaultUnit,
				description: '',
				taxString: '21',
				tax: 21.0,
				priceString: '0',
				price: 0,
				totalPriceString: '0',
				totalPrice: 0,
			});
		},
		changeCurrency: function () {
			fetch('/api/v1/expense/loadCurrency', {
				method: 'POST',
				body: JSON.stringify(
					{
						code: this.expense.currency,
						expenseData: this.expense,
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						let currency = responseData.data.currency;
						this.expense.currencyData.id = currency.id;
						this.expense.currencyData.code = currency.code;
						this.expense.currencyData.symbol = currency.symbol;
						this.expense.currencyData.rateReal = currency.rateReal;
						this.expense.currencyData.rateRealString = currency.rateRealString;
						this.expense.currencyData.rateDate = currency.rateDate;
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
		loadCompanyByIc: function () {
			fetch('/api/v1/expense/loadCompanyByCin', {
				method: 'POST',
				body: JSON.stringify(
					{
						cin: this.expense.customer.cin
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.expense.customer = responseData.data.customer;
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
		save: function () {
			fetch('/api/v1/expense/save', {
				method: 'POST',
				body: JSON.stringify(
					{
						expenseData: this.expense
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					if (responseData.state === 'ok') {
						this.expense = responseData.data.expense;

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
		checkVariableSymbol: function () {
			let vs = this.expense.variableSymbol;

			if (vs === '') {
				this.expense.variableSymbolError = 0;
			} else if (/^\d{1,10}$/.test(vs)) {
				this.expense.variableSymbolError = 1;
			} else {
				this.expense.variableSymbolError = 2;
			}
		},
		updateData: function () {
			this.expense.price = parseFloat(this.expense.priceFormatted.replace(',', '.'));
			this.expense.tax = parseFloat(this.expense.taxFormatted.replace(',', '.'));

			let totalItemPrice = 0.0;
			this.expense.items.forEach(function (item, index, array) {
				item.count = parseFloat(item.countString.replace(',', '.'));
				item.tax = parseFloat(item.taxString.replace(',', '.'));
				item.price = parseFloat(item.priceString.replace(',', '.'));

				let price = parseFloat(item.count) * parseFloat(item.price);
				price = Math.round(price * 100) / 100;
				item.totalPrice = price;
				item.totalPriceString = price.toString().replace('.', ',');

				totalItemPrice += item.totalPrice;
			});

			this.expense.itemTotalPrice = totalItemPrice;
		},
		updatePrice: function () {
			this.expense.price = parseFloat(this.expense.priceFormatted.replace(',', '.'));
		},
		removeItem: function (id) {
			this.expense.items.splice(id, 1);
			this.updateData();
		},
		changeDUP: function () {
			this.expense.dateError = !/^2[0-1][0-9]{2}-(1[0-2]|0[1-9])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/.test(this.expense.date);

			let d = new Date(Date.parse(this.expense.date));
			this.expense.dateData.year = d.getFullYear();
			this.expense.dateData.month = d.getMonth() + 1;
			this.changeCurrency();
		},
		changeDate: function () {
			this.expense.date = this.expense.dateData.year + '-' + (this.expense.dateData.month < 10 ? '0' : '') + this.expense.dateData.month + '-01';
			this.expense.dateError = !/^2[0-1][0-9]{2}-(1[0-2]|0[1-9])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/.test(this.expense.date);
			this.changeCurrency();
		},
		updateRate: function () {
			this.expense.currencyData.rate = parseFloat(this.expense.currencyData.rateString.replace(',', '.'));
		},
		validatePrintDate: function () {
			this.expense.datePrintError = !/^2[0-1][0-9]{2}-(1[0-2]|0[1-9])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/.test(this.expense.datePrint);
		},
		validateDueDate: function () {
			this.expense.dateDueError = !/^2[0-1][0-9]{2}-(1[0-2]|0[1-9])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/.test(this.expense.dateDue);
		},
		validatePayDate: function () {
			this.expense.datePayError = !(this.expense.datePay === '' || /^2[0-1][0-9]{2}-(1[0-2]|0[1-9])-(0[1-9]|1[0-9]|2[0-9]|3[0-1])$/.test(this.expense.datePay));
		},
		loadSupplier: function (id) {
			fetch('/api/v1/expense/loadSupplier', {
				method: 'POST',
				body: JSON.stringify(
					{
						id: id
					}
				)
			})
				.then(response => response.json())
				.then(responseData => {
					this.expense.customer = responseData.data.customer;
				})
				.catch(error => {
					console.log(error)
				});
		},
		setCNBRate: function () {
			this.expense.currencyData.rate = this.expense.currencyData.rateReal;
			this.expense.currencyData.rateString = this.expense.currencyData.rateRealString;
		},
		calculatePriceWithVat: function () {
			this.expense.priceNoVat = parseFloat(this.expense.priceNoVatFormatted.replace(',', '.'));
			this.expense.price = Math.round(this.expense.priceNoVat * 1.21 * 100) / 100;
			this.expense.priceFormatted = this.expense.price.toString().replace('.', ',');
			this.expense.tax = Math.round(this.expense.priceNoVat * 0.21 * 100) / 100;
			this.expense.taxFormatted = this.expense.tax.toString().replace('.', ',');
		},
		setProductCode: function (code) {
			this.expense.productCode = code;
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
		this.expense.id = document.getElementById('app-data').getAttribute('data-expenseId');
		this.adminAccess = parseInt(document.getElementById('app-data').getAttribute('data-admin')) === 1;
		fetch('/api/v1/expense/loadExpense', {
			method: 'POST',
			body: JSON.stringify(
				{
					id: this.expense.id
				}
			)
		})
			.then(response => response.json())
			.then(responseData => {
				this.expense = responseData.data.expense;
				if (this.expense.id === null) {
					this.expense.hidden = this.adminAccess;
				}
			})
			.catch(error => {
				console.log(error)
			});
	}
});