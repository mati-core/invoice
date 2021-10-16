<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Unit\UnitManagerAccessor;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\Company;
use MatiCore\Company\CompanyManagerAccessor;
use Nette\Security\User;

class InvoiceHelper
{
	private array $companyData = [];


	public function __construct(
		private CompanyManagerAccessor $companyManager,
		private EntityManager $entityManager,
		private InvoiceManagerAccessor $invoiceManager,
		private CurrencyManagerAccessor $currencyManager,
		private UnitManagerAccessor $unitManager,
		private CountryManagerAccessor $countryManager,
		private SignatureManagerAccessor $signatureManager,
		private User $user
	) {
	}


	/**
	 * @return array
	 */
	public function getInvoiceById(string $id): array
	{
		try {
			$invoice = $this->invoiceManager->get()->getInvoiceById($id);
			$unit = $this->unitManager->get()->getDefaultUnit();

			$ret = [
				'id' => $invoice->getId(),
				'type' => $invoice->isRegular() ? 'invoice' : 'proforma',
				'number' => $invoice->getNumber(),
				'orderNumber' => $invoice->getOrderNumber() ?? '',
				'rentNumber' => $invoice->getRentNumber() ?? '',
				'contractNumber' => $invoice->getContractNumber() ?? '',
				'currency' => $invoice->getCurrency()->getCode(),
				'currencyData' => [
					'id' => $invoice->getCurrency()->getId(),
					'code' => $invoice->getCurrency()->getCode(),
					'symbol' => $invoice->getCurrency()->getSymbol(),
					'rate' => $invoice->getRate(),
					'rateDate' => $invoice->getRateDate()->format('d.m.Y'),
				],
				'payMethod' => $invoice->getPayMethod(),
				'date' => $invoice->getDate()->format('Y-m-d'),
				'dateTax' => $invoice->getTaxDate()->format('Y-m-d'),
				'dateDue' => $invoice->getDueDate()->format('Y-m-d'),
				'dateDueSelect' => $this->getDueDayCount($invoice->getTaxDate(), $invoice->getDueDate()),
				'priceDif' => 0.0,
				'priceDifFormated' => '0 Kč',
				'totalPriceWithoutTax' => 0.0,
				'totalPriceWithoutTaxFormated' => '0 Kč',
				'totalTax' => 0.0,
				'totalTaxFormated' => '0 Kč',
				'totalPrice' => 0.0,
				'totalPriceFormated' => '0 kč',
				'totalPriceRounded' => 0.0,
				'totalPriceRoundedFormated' => '0 Kč',
				'defaultUnit' => $unit->getId(),
				'defaultTax' => (float) $this->companyData['taxDefault'],
				'textBeforeItems' => $invoice->getTextBeforeItems() ?? '',
				'textAfterItems' => $invoice->getTextAfterItems() ?? '',
				'company' => [
					'id' => $invoice->getCompany() !== null ? $invoice->getCompany()->getId() : null,
					'name' => $invoice->getCompanyName(),
					'address' => $invoice->getCompanyAddress(),
					'city' => $invoice->getCompanyCity(),
					'zipCode' => $invoice->getCompanyPostalCode(),
					'country' => $invoice->getCompanyCountry()->getIsoCode(),
					'cin' => $invoice->getCompanyCin() ?? '',
					'tin' => $invoice->getCompanyTin() ?? '',
				],
				'customer' => [
					'id' => null,
					'name' => $invoice->getCustomerName(),
					'address' => $invoice->getCustomerAddress(),
					'city' => $invoice->getCustomerCity(),
					'zipCode' => $invoice->getCustomerPostalCode(),
					'country' => $invoice->getCustomerCountry()->getIsoCode(),
					'cin' => $invoice->getCustomerCin() ?? '',
					'tin' => $invoice->getCustomerTin() ?? '',
					'depositList' => [],
				],
				'items' => [],
				'deposit' => [],
				'taxList' => [],
				'taxEnabled' => $invoice->isTaxEnabled(),
			];

			foreach ($invoice->getItems() as $item) {
				$buyCurrency = [
					'id' => null,
					'symbol' => '???',
					'rate' => 30.0,
				];

				if ($item->getBuyCurrency() !== null) {
					$buyCurrency = [
						'id' => $item->getBuyCurrency()->getId(),
						'symbol' => $item->getBuyCurrency()->getSymbol(),
						'rate' => 30.0,
					];
				}

				$ret['items'][] =
					[
						'id' => $item->getId(),
						'countString' => (string) $item->getQuantity(),
						'count' => $item->getQuantity(),
						'unit' => $item->getUnit()->getId(),
						'description' => $item->getDescription(),
						'saleDescription' => $item->getSaleDescription(),
						'saleString' => (string) $item->getSale(),
						'sale' => $item->getSale(),
						'salePriceString' => (string) $item->getSalePrice(),
						'salePrice' => $item->getSalePrice(),
						'taxString' => (string) $item->getVat(),
						'tax' => $item->getVat(),
						'priceString' => (string) $item->getPricePerItem(),
						'price' => $item->getPricePerItem(),
						'buyPrice' => $item->getBuyPrice(),
						'buyCurrency' => $buyCurrency,
						'totalPriceString' => (string) $item->getPricePerItem() * $item->getQuantity(),
						'totalPrice' => $item->getPricePerItem() * $item->getQuantity(),
					];
			}

			foreach ($invoice->getDepositInvoices() as $depositInvoice) {
				$ret['deposit'][] = [
					'id' => $depositInvoice->getId(),
					'number' => $depositInvoice->getNumber(),
					'itemPrice' => $depositInvoice->getItemTotalPrice(),
					'itemPriceFormatted' => str_replace(
						'&nbsp;', ' ',
						Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())
					),
					'tax' => $depositInvoice->getTotalTax(),
					'taxFormatted' => str_replace(
						'&nbsp;', ' ',
						Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())
					),
					'price' => $depositInvoice->getTotalPrice(),
					'priceFormatted' => str_replace(
						'&nbsp;', ' ',
						Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())
					),
				];
			}

			return $ret;
		} catch (NoResultException | NonUniqueResultException) {
			return $this->getNewInvoice();
		}
	}


	/**
	 * @return array
	 * @throws InvoiceException
	 * @throws UnitException
	 * @throws CurrencyException
	 */
	public function getNewInvoice(): array
	{
		$date = new \DateTime;
		$dueDate = $date->modifyClone('+14 days');

		$currency = $this->currencyManager->get()->getDefaultCurrency();
		$unit = $this->unitManager->get()->getDefaultUnit();

		$currencyTemp = $this->currencyManager->get()->getCurrencyRateByDate($currency, $date);
		$currencyRate = $currencyTemp->getRate();
		$currencyDate = $currencyTemp->getLastUpdate()->format('d.m.Y');

		return [
			'id' => null,
			'type' => 'invoice',
			'number' => $this->invoiceManager->get()->getNextInvoiceNumber(),
			'orderNumber' => '',
			'rentNumber' => '',
			'contractNumber' => '',
			'currency' => 'CZK',
			'currencyData' => [
				'id' => $currency->getId(),
				'code' => $currency->getCode(),
				'symbol' => $currency->getSymbol(),
				'rate' => $currencyRate,
				'rateDate' => $currencyDate,
			],
			'payMethod' => 'bank',
			'date' => $date->format('Y-m-d'),
			'dateTax' => $date->format('Y-m-d'),
			'dateDue' => $dueDate->format('Y-m-d'),
			'dateDueSelect' => '14',
			'priceDif' => 0.0,
			'priceDifFormated' => '0 Kč',
			'totalPriceWithoutTax' => 0.0,
			'totalPriceWithoutTaxFormated' => '0 Kč',
			'totalTax' => 0.0,
			'totalTaxFormated' => '0 Kč',
			'totalPrice' => 0.0,
			'totalPriceFormated' => '0 kč',
			'totalPriceRounded' => 0.0,
			'totalPriceRoundedFormated' => '0 Kč',
			'defaultUnit' => $unit->getId(),
			'defaultTax' => (float) $this->companyData['taxDefault'],
			'textBeforeItems' => '',
			'textAfterItems' => '',
			'company' => [
				'id' => null,
				'name' => $this->companyData['name'],
				'address' => $this->companyData['address'],
				'city' => $this->companyData['city'],
				'zipCode' => $this->companyData['zipCode'],
				'country' => $this->companyData['country'],
				'cin' => $this->companyData['cin'],
				'tin' => $this->companyData['tin'],
			],
			'customer' => [
				'id' => null,
				'name' => '',
				'address' => '',
				'city' => '',
				'zipCode' => '',
				'country' => 'CZE',
				'cin' => '',
				'tin' => '',
				'depositList' => [],
			],
			'items' => [
				[
					'id' => null,
					'countString' => '1',
					'count' => 1,
					'unit' => $unit->getId(),
					'description' => '',
					'saleDescription' => 'Sleva',
					'saleString' => '0',
					'sale' => 0,
					'salePriceString' => 0,
					'salePrice' => 0,
					'taxString' => (string) $this->companyData['taxDefault'],
					'tax' => (float) $this->companyData['taxDefault'],
					'priceString' => '0',
					'price' => 0,
					'buyPrice' => null,
					'buyCurrency' => [
						'id' => null,
						'symbol' => '???',
						'rate' => 30.0,
					],
					'totalPriceString' => '0',
					'totalPrice' => 0,
				],
			],
			'deposit' => [],
			'taxList' => [],
			'taxEnabled' => $this->companyData['taxEnabled'],
		];
	}


	/**
	 * @return array|null
	 * @throws UnitException
	 */
	public function getFixInvoiceById(string $id): ?array
	{
		try {
			$invoice = $this->invoiceManager->get()->getInvoiceById($id);
			$unit = $this->unitManager->get()->getDefaultUnit();

			$date = $invoice->isFix() ? $invoice->getDate() : new \DateTime;
			$taxDate = $invoice->isFix() ? $invoice->getTaxDate() : new \DateTime;
			$dueDate = $invoice->isFix()
				? $invoice->getDueDate()
				: (new \DateTime($date))->modify('+30 days');

			$textBeforeItems = $invoice->isFix()
				? ($invoice->getTextBeforeItems() ?? '')
				: 'Opravný daňový doklad k daňovému dokladu č. ' . $invoice->getNumber();
			$textAfterItems = $invoice->isFix()
				? ($invoice->getTextBeforeItems() ?? '')
				: '';

			$invoiceId = $invoice->isFix() && $invoice->getSubInvoice() !== null
				? $invoice->getSubInvoice()->getId()
				: $invoice->getId();

			$ret = [
				'id' => $invoice->isFix() ? $invoice->getId() : null,
				'type' => 'fixInvoice',
				'invoiceId' => $invoiceId,
				'number' => '11' . $invoice->getNumber(),
				'orderNumber' => $invoice->getOrderNumber() ?? '',
				'rentNumber' => $invoice->getRentNumber() ?? '',
				'contractNumber' => $invoice->getContractNumber() ?? '',
				'currency' => $invoice->getCurrency()->getCode(),
				'currencyData' => [
					'id' => $invoice->getCurrency()->getId(),
					'code' => $invoice->getCurrency()->getCode(),
					'symbol' => $invoice->getCurrency()->getSymbol(),
					'rate' => $invoice->getRate(),
					'rateDate' => $invoice->getRateDate()->format('d.m.Y'),
				],
				'payMethod' => $invoice->getPayMethod(),
				'date' => $date->format('Y-m-d'),
				'dateTax' => $taxDate->format('Y-m-d'),
				'dateDue' => $dueDate->format('Y-m-d'),
				'dateDueSelect' => $this->getDueDayCount($taxDate, $dueDate),
				'priceDif' => 0.0,
				'priceDifFormated' => '0 Kč',
				'totalPriceWithoutTax' => 0.0,
				'totalPriceWithoutTaxFormated' => '0 Kč',
				'totalTax' => 0.0,
				'totalTaxFormated' => '0 Kč',
				'totalPrice' => 0.0,
				'totalPriceFormated' => '0 kč',
				'totalPriceRounded' => 0.0,
				'totalPriceRoundedFormated' => '0 Kč',
				'defaultUnit' => $unit->getId(),
				'defaultTax' => $this->companyData['taxDefault'],
				'textBeforeItems' => $textBeforeItems,
				'textAfterItems' => $textAfterItems,
				'company' => [
					'id' => $invoice->getCompany() !== null ? $invoice->getCompany()->getId() : null,
					'name' => $invoice->getCompanyName(),
					'address' => $invoice->getCompanyAddress(),
					'city' => $invoice->getCompanyCity(),
					'zipCode' => $invoice->getCompanyPostalCode(),
					'country' => $invoice->getCompanyCountry()->getIsoCode(),
					'cin' => $invoice->getCompanyCin() ?? '',
					'tin' => $invoice->getCompanyTin() ?? '',
				],
				'customer' => [
					'id' => null,
					'name' => $invoice->getCustomerName(),
					'address' => $invoice->getCustomerAddress(),
					'city' => $invoice->getCustomerCity(),
					'zipCode' => $invoice->getCustomerPostalCode(),
					'country' => $invoice->getCustomerCountry()->getIsoCode(),
					'cin' => $invoice->getCustomerCin() ?? '',
					'tin' => $invoice->getCustomerTin() ?? '',
					'depositList' => [],
				],
				'items' => [],
				'deposit' => [],
				'taxList' => [],
				'taxEnabled' => $invoice->isTaxEnabled(),
			];

			foreach ($invoice->getItems() as $item) {
				$buyCurrency = [
					'id' => null,
					'symbol' => '???',
					'rate' => 30.0,
				];

				if ($item->getBuyCurrency() !== null) {
					$buyCurrency = [
						'id' => $item->getBuyCurrency()->getId(),
						'symbol' => $item->getBuyCurrency()->getSymbol(),
						'rate' => 30.0,
					];
				}

				if ($invoice->isFix()) {
					$ret['items'][] =
						[
							'id' => $item->getId(),
							'countString' => (string) $item->getQuantity(),
							'count' => $item->getQuantity(),
							'unit' => $item->getUnit()->getId(),
							'description' => $item->getDescription(),
							'saleDescription' => $item->getSaleDescription(),
							'saleString' => (string) $item->getSale(),
							'sale' => $item->getSale(),
							'salePriceString' => (string) $item->getSalePrice(),
							'salePrice' => $item->getSalePrice(),
							'taxString' => (string) $item->getVat(),
							'tax' => $item->getVat(),
							'priceString' => (string) $item->getPricePerItem(),
							'price' => $item->getPricePerItem(),
							'buyPrice' => $item->getBuyPrice(),
							'buyCurrency' => $buyCurrency,
							'totalPriceString' => (string) $item->getPricePerItem() * $item->getQuantity(),
							'totalPrice' => $item->getPricePerItem() * $item->getQuantity(),
						];
				} else {
					$ret['items'][] =
						[
							'id' => $item->getId(),
							'countString' => (string) $item->getQuantity(),
							'count' => $item->getQuantity(),
							'unit' => $item->getUnit()->getId(),
							'description' => $item->getDescription(),
							'saleDescription' => $item->getSaleDescription(),
							'saleString' => (string) $item->getSale(),
							'sale' => $item->getSale(),
							'salePriceString' => (string) -$item->getSalePrice(),
							'salePrice' => -$item->getSalePrice(),
							'taxString' => (string) $item->getVat(),
							'tax' => $item->getVat(),
							'priceString' => (string) -$item->getPricePerItem(),
							'price' => -$item->getPricePerItem(),
							'buyPrice' => $item->getBuyPrice(),
							'buyCurrency' => $buyCurrency,
							'totalPriceString' => (string) -$item->getPricePerItem() * $item->getQuantity(),
							'totalPrice' => -$item->getPricePerItem() * $item->getQuantity(),
						];
				}
			}

			if ($invoice->isProforma() === false) {
				foreach ($invoice->getDepositInvoices() as $depositInvoice) {
					$ret['deposit'][] = [
						'id' => $depositInvoice->getId(),
						'number' => $depositInvoice->getNumber(),
						'itemPrice' => $depositInvoice->getItemTotalPrice(),
						'itemPriceFormatted' => str_replace(
							'&nbsp;', ' ',
							Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())
						),
						'tax' => $depositInvoice->getTotalTax(),
						'taxFormatted' => str_replace(
							'&nbsp;', ' ',
							Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())
						),
						'price' => $depositInvoice->getTotalPrice(),
						'priceFormatted' => str_replace(
							'&nbsp;', ' ',
							Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())
						),
					];
				}
			}

			return $ret;
		} catch (NoResultException | NonUniqueResultException) {
			return null;
		}
	}


	/**
	 * @param array $invoiceData
	 * @return array
	 * @throws InvoiceException
	 */
	public function reloadInvoiceNumber(array $invoiceData): array
	{
		$invoiceId = $invoiceData['id'];

		if ($invoiceId !== null && $invoiceId !== '') {
			return $invoiceData;
		}

		$type = $invoiceData['type'];
		$date = new \DateTime($invoiceData['date']);
		$dateTax = new \DateTime($invoiceData['dateTax']);

		if ($type === 'proforma') {
			$invoiceData['number'] = $this->invoiceManager->get()->getNextInvoiceNumber($date);
		} else {
			$invoiceData['number'] = $this->invoiceManager->get()->getNextInvoiceNumber($dateTax);
		}

		return $invoiceData;
	}


	/**
	 * @param array $invoiceData
	 * @return array
	 * @throws CurrencyException|UnitException|UserException
	 */
	public function saveInvoice(array $invoiceData, ?BaseUser $user = null): array
	{
		$invoiceId = $invoiceData['id'];
		$invoiceType = $invoiceData['type'];
		$invoiceNumber = $invoiceData['number'];
		$variableSymbol = str_replace('O', '99', $invoiceNumber);
		$customerId = $invoiceData['customer']['id'];
		$customerIc = $invoiceData['customer']['cin'];
		$totalPrice = (float) $invoiceData['totalPriceRounded'];
		$totalTax = (float) $invoiceData['totalTax'];

		try {
			$currency = $this->currencyManager->get()->getCurrencyById($invoiceData['currencyData']['id']);
		} catch (NoResultException | NonUniqueResultException) {
			$currency = $this->currencyManager->get()->getDefaultCurrency();
		}

		$currencyRate = (float) $invoiceData['currencyData']['rate'];
		$currencyDate = new \DateTime($invoiceData['currencyData']['rateDate']);

		if ($currency->getCode() === 'CZK') {
			$bankData = $this->companyData['bank']['CZK'];
			$bankAccount = $bankData['bankAccount'];
			$bankCode = $bankData['bankCode'];
			$bankName = $bankData['bankName'];
			$iban = $bankData['IBAN'];
			$swift = $bankData['SWIFT'];
		} else {
			$bankData = $this->companyData['bank']['default'];
			$bankAccount = $bankData['bankAccount'];
			$bankCode = $bankData['bankCode'];
			$bankName = $bankData['bankName'];
			$iban = $bankData['IBAN'];
			$swift = $bankData['SWIFT'];
		}

		if ($user === null) {
			$identity = $this->user->getIdentity();
			if ($identity instanceof StorageIdentity && $identity->getUser() !== null) {
				$user = $identity->getUser();
			}
		}

		$invoice = null;
		try {
			if ($invoiceId !== null) {
				$invoice = $this->invoiceManager->get()->getInvoiceById($invoiceId);
			}
		} catch (NoResultException | NonUniqueResultException) {
			$invoice = null;
		}

		if ($invoice === null) {
			if ($invoiceType === 'invoice') {
				$invoice = new Invoice($invoiceNumber, Invoice::TYPE_REGULAR);
			} elseif ($invoiceType === 'fixInvoice') {
				$invoice = new Invoice($invoiceNumber, Invoice::TYPE_FIX);
				$parentId = $invoiceData['invoiceId'];

				try {
					$parent = $this->invoiceManager->get()->getInvoiceById($parentId);
					if ($parent->isRegular()) {
						$invoice->setSubInvoice($parent);
						$parent->setFixInvoice($invoice);
					}
				} catch (NoResultException | NonUniqueResultException) {
				}
			} else {
				$invoice = new Invoice($invoiceNumber, Invoice::TYPE_PROFORMA);
			}

			$invoice->setCreatedByUserId($user);
			$invoice->setCreateDate(new \DateTime);
			$invoice->setEditedByUserId($user);
			$invoice->setEditDate(new \DateTime);

			if ($invoice->isFix()) {
				$changeDescription = 'Vytvoření opravného daňového dokladu';
			} elseif ($invoice->isProforma()) {
				$changeDescription = 'Vytvoření proformy';
			} else {
				$changeDescription = 'Vytvoření faktury';
			}
		} else {
			$invoice->setEditedByUserId($user);
			$invoice->setEditDate(new \DateTime);

			if ($invoice->isFix()) {
				$changeDescription = 'Úprava opravného daňového dokladu';
			} elseif ($invoice->isProforma()) {
				$changeDescription = 'Úprava proformy';
			} else {
				$changeDescription = 'Úprava faktury';
			}
		}

		//Nastaveni company z katalogu, pokud existuje
		if ($customerId !== null) {
			try {
				$company = $this->companyManager->get()->getCompanyById($customerId);
				$invoice->setCompany($company);
			} catch (NoResultException | NonUniqueResultException) {
				$customerId = null;
			}
		}

		if ($customerId === null) {
			try {
				$company = $this->companyManager->get()->getCompanyByCIN($customerIc);
				$invoice->setCompany($company);
			} catch (NoResultException | NonUniqueResultException) {

			}
		}

		//Nacteni zeme spolecnosti
		try {
			$country = $this->countryManager->getCountryByIsoCode($this->companyData['country']);
		} catch (NoResultException | NonUniqueResultException) {
			try {
				$country = $this->countryManager->getCountryByIsoCode('CZE');
			} catch (NoResultException | NonUniqueResultException) {
				$country = null;
			}
		}

		//Nacteni zeme zakaznika
		try {
			$customerCountry = $this->countryManager->getCountryByIsoCode($invoiceData['customer']['country']);
		} catch (NoResultException | NonUniqueResultException) {
			try {
				$customerCountry = $this->countryManager->getCountryByIsoCode('CZE');
			} catch (NoResultException | NonUniqueResultException) {
				$customerCountry = null;
			}
		}

		//Nastaveni spolecnosti
		$invoice->setCompanyName($invoiceData['company']['name']);
		$invoice->setCompanyAddress($invoiceData['company']['address']);
		$invoice->setCompanyCity($invoiceData['company']['city']);
		$invoice->setCompanyPostalCode($invoiceData['company']['zipCode']);
		if ($country !== null) {
			$invoice->setCompanyCountry($country);
		}
		$invoice->setCompanyCin($invoiceData['company']['cin']);
		$invoice->setCompanyTin(
			$invoiceData['company']['cin'] === '' || $invoiceData['company']['tin'] === null
				? null
				: $invoiceData['company']['tin']
		);
		$invoice->setCompanyLogo($this->companyData['logo']);

		//Nastaveni banky
		$invoice->setBankAccount($bankAccount);
		$invoice->setBankCode($bankCode);
		$invoice->setBankName($bankName);
		$invoice->setIban($iban);
		$invoice->setSwift($swift);
		$invoice->setVariableSymbol($variableSymbol);

		//Nastaveni meny
		$invoice->setCurrency($currency);
		$invoice->setRate($currencyRate);
		$invoice->setRateDate($currencyDate);

		//Nastaveni zakaznika
		$invoice->setCustomerName($invoiceData['customer']['name']);
		$invoice->setCustomerAddress($invoiceData['customer']['address']);
		$invoice->setCustomerCity($invoiceData['customer']['city']);
		$invoice->setCustomerPostalCode($invoiceData['customer']['zipCode']);
		if ($customerCountry !== null) {
			$invoice->setCustomerCountry($customerCountry);
		}
		$invoice->setCustomerCin($invoiceData['customer']['cin']);
		$invoice->setCustomerTin(
			$invoiceData['customer']['cin'] === '' || $invoiceData['customer']['tin'] === null
				? null
				: $invoiceData['customer']['tin']
		);

		//cisla
		$invoice->setOrderNumber($invoiceData['orderNumber'] === '' ? null : $invoiceData['orderNumber']);
		$invoice->setRentNumber($invoiceData['rentNumber'] === '' ? null : $invoiceData['rentNumber']);
		$invoice->setContractNumber($invoiceData['contractNumber'] === '' ? null : $invoiceData['contractNumber']);

		//Nastaveni celkove ceny
		$invoice->setTotalPrice($totalPrice);
		$invoice->setTotalTax($totalTax);

		//Zapnuti DPH
		$invoice->setTaxEnabled($invoiceData['taxEnabled']);

		//Data
		$invoice->setDate(new \DateTime($invoiceData['date']));
		$invoice->setDueDate(new \DateTime($invoiceData['dateDue']));
		if ($invoice->isProforma()) {
			$invoice->setTaxDate(new \DateTime($invoiceData['date']));
		} else {
			$invoice->setTaxDate(new \DateTime($invoiceData['dateTax']));
		}

		//platební metody
		$invoice->setPayMethod($invoiceData['payMethod']);

		//Podpis autora faktury
		$invoice->setSignImage($this->signatureManager->get()->getSignatureLink($invoice->getCreatedByUserId()));

		//Poznamky
		$invoice->setTextBeforeItems($invoiceData['textBeforeItems']);
		$invoice->setTextAfterItems($invoiceData['textAfterItems']);

		//Persistnuti faktury
		$this->entityManager->persist($invoice);
		$this->entityManager->flush();

		//Historie faktury
		$invoiceHistory = new InvoiceHistory($invoice, $changeDescription);
		$invoiceHistory->setUserId($user ?? null);

		$this->entityManager->persist($invoiceHistory);

		$invoice->addHistory($invoiceHistory);

		// Add items
		$this->clearInvoiceItems($invoice);
		$position = 0;
		foreach ($invoiceData['items'] as $itemData) {
			if (!$invoice->isFix() || (float) $itemData['count'] !== 0.0) {
				$unit = $this->getUnit($itemData['unit']);
				$position++;
				$item = new InvoiceItem(
					$invoice,
					$itemData['description'],
					(float) $itemData['count'],
					$unit,
					(float) $itemData['price']
				);

				$item->setBuyPrice($itemData['buyPrice'] ?? null);

				if (isset($itemData['buyCurrency']['id']) && $itemData['buyCurrency']['id'] !== null) {
					try {
						$buyCurrency = $this->currencyManager->getCurrencyById($itemData['buyCurrency']['id']);
						$item->setBuyCurrency($buyCurrency);
					} catch (NoResultException | NonUniqueResultException) {
						$item->setBuyCurrency(null);
					}
				} else {
					$item->setBuyCurrency(null);
				}

				$item->setVat((float) $itemData['tax']);
				$item->setPosition($position);
				$item->setSale($itemData['sale'] ?? 0);
				$item->setSaleDescription($itemData['saleDescription'] ?? 'Sleva');

				$this->entityManager->persist($item);
				$invoice->addItem($item);
			}
		}

		//DPH
		foreach ($invoice->getTaxList() as $invoiceTax) {
			$this->entityManager->remove($invoiceTax);
		}
		$invoice->clearTaxList();

		foreach ($invoice->getTaxTable() as $invoiceTax) {
			$this->entityManager->persist($invoiceTax);
			$invoice->addTax($invoiceTax);
		}

		//zalohy
		foreach ($invoice->getDepositInvoices() as $di) {
			$di->removeDepositingInvoice($invoice);
		}

		$invoice->clearDepositInvoices();

		foreach ($invoiceData['deposit'] as $depositInvoiceData) {
			try {
				$depositInvoice = $this->invoiceManager->get()->getInvoiceById($depositInvoiceData['id']);
				$invoice->addDepositInvoice($depositInvoice);
				$depositInvoice->addDepositingInvoice($invoice);

				if ($depositInvoice->isProforma() && $invoice->isRegular()) {
					$depositInvoice->setSubInvoice($invoice);
				}
			} catch (NoResultException | NonUniqueResultException) {
			}
		}

		$invoice->setStatus(Invoice::STATUS_CREATED);
		$invoice->setAcceptStatus1(Invoice::STATUS_WAITING);
		$invoice->setAcceptStatus2(Invoice::STATUS_WAITING);
		$invoice->setSubmitted(false);

		if ($invoice->isFix()) {
			$invoice->setPayDate(new \DateTime);
		} elseif ($invoice->isRegular() && $invoice->getTotalPrice() === 0.0) {
			$proforma = $invoice->getProforma();
			if ($proforma !== null) {
				$invoice->setPayDate($proforma->getPayDate());
			} else {
				$invoice->setPayDate(new \DateTime);
			}
		} else {
			$invoice->setPayDate(null);
		}

		//Finalni ulozeni do databaze
		$this->entityManager->flush();

		$invoiceData['id'] = $invoice->getId();

		return $invoiceData;
	}


	/**
	 * @param array $invoiceData
	 * @return array
	 */
	public function addDepositInvoice(array $invoiceData, string $depositNumber): array
	{
		try {
			$depositInvoice = $this->invoiceManager->get()->getInvoiceByCode($depositNumber);
			if ($invoiceData['customer']['cin'] === '' && $invoiceData['customer']['name'] === '') {
				$invoiceData['customer'] = [
					'id' => null,
					'name' => $depositInvoice->getCustomerName(),
					'address' => $depositInvoice->getCustomerAddress(),
					'city' => $depositInvoice->getCustomerCity(),
					'zipCode' => $depositInvoice->getCustomerPostalCode(),
					'country' => $depositInvoice->getCustomerCountry()->getIsoCode(),
					'cin' => $depositInvoice->getCustomerCin() ?? '',
					'tin' => $depositInvoice->getCustomerTin() ?? '',
				];
			} elseif ($invoiceData['customer']['cin'] !== $depositInvoice->getCustomerCin()) {
				throw new \InvalidArgumentException(
					'IČ odběratele na zálohové faktuře se neshoduje s IČ odběratele na upravované faktuře.'
				);
			}

			if ($invoiceData['currency'] !== $depositInvoice->getCurrency()->getCode()) {
				throw new \InvalidArgumentException('Měna na zálohové faktuře se neshoduje s měnou na upravované faktuře.');
			}

			foreach ($invoiceData['deposit'] as $deposit) {
				if ($deposit['id'] === $depositInvoice->getId()) {
					throw new \LogicException('Záloha byla již odečtena.');
				}
			}

			$invoiceData['deposit'][] = [
				'id' => $depositInvoice->getId(),
				'number' => $depositInvoice->getNumber(),
				'itemPrice' => $depositInvoice->getItemTotalPrice(),
				'itemPriceFormatted' => str_replace(
					'&nbsp;', ' ',
					Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())
				),
				'tax' => $depositInvoice->getTotalTax(),
				'taxFormatted' => str_replace(
					'&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())
				),
				'price' => $depositInvoice->getTotalPrice(),
				'priceFormatted' => str_replace(
					'&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())
				),
			];
		} catch (NoResultException | NonUniqueResultException) {
			throw new InvoiceException('Zálohová faktura s číslem: ' . $depositNumber . ' neexistuje.');
		}

		return $invoiceData;
	}


	/**
	 * @return array
	 */
	public function getDepositList(Company $company): array
	{
		/** @var Invoice[] $invoices */
		$invoices = $this->entityManager->getRepository(Invoice::class)
			->createQueryBuilder('proforma')
			->where('proforma.company = :companyId')
			->andWhere('proforma.type = :type')
			->andWhere('proforma.invoice IS NULL')
			->andWhere('proforma.payDate IS NOT NULL')
			->setParameter('companyId', $company->getId())
			->setParameter('type', Invoice::TYPE_PROFORMA)
			->getQuery()
			->getResult();

		$ret = [];
		foreach ($invoices as $invoice) {
			$ret[] = [
				'number' => $invoice->getNumber(),
				'price' => Number::formatPrice($invoice->getTotalPrice(), $invoice->getCurrency()),
			];
		}

		return $ret;
	}


	private function getDueDayCount(\DateTime $dateTax, \DateTime $dateDue): string
	{
		$diff = (int) $dateDue->diff($dateTax)->format('%a');
		$available = [
			0 => '0',
			7 => '7',
			10 => '10',
			14 => '14',
			30 => '30',
		];

		return $available[$diff] ?? '';
	}


	/**
	 * @throws EntityManagerException
	 */
	private function clearInvoiceItems(Invoice $invoice): void
	{
		foreach ($invoice->getItems() as $item) {
			$invoice->removeItem($item);
			$this->entityManager->remove($item);
		}
	}


	private function getUnit(string $unitId): Unit
	{
		static $cache;
		if ($cache === null) {
			$cache = $this->unitManager->get()->getUnits();
		}

		foreach ($cache as $unit) {
			if ($unit->getId() === $unitId) {
				return $unit;
			}
		}

		return $this->unitManager->get()->getDefaultUnit();
	}
}
