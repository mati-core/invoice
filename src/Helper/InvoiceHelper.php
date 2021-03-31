<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use App\Model\History\HistoryManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Address\CountryManager;
use MatiCore\Database\EntityManager;
use MatiCore\Database\EntityManagerException;
use Nette\Security\User;
use MatiCore\User\Entity\User as MUser;
use Nette\Utils\DateTime;

class InvoiceHelper
{

	/**
	 * @var array
	 */
	private $companyData;

	/**
	 * @var CompanyManager
	 */
	private $companyManager;

	/**
	 * @var EntityManager
	 */
	private $entityManager;

	/**
	 * @var InvoiceManager
	 */
	private $invoiceManager;

	/**
	 * @var CurrencyManager
	 */
	private $currencyManager;

	/**
	 * @var UnitManager
	 */
	private $unitManager;

	/**
	 * @var CountryManager
	 */
	private $countryManager;

	/**
	 * @var SignatureManager
	 */
	private $signatureManager;

	/**
	 * @var HistoryManager
	 */
	private $historyManager;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * InvoiceHelper constructor.
	 * @param array $companyData
	 * @param CompanyManager $companyManager
	 * @param EntityManager $entityManager
	 * @param InvoiceManager $invoiceManager
	 * @param CurrencyManager $currencyManager
	 * @param UnitManager $unitManager
	 * @param CountryManager $countryManager
	 * @param SignatureManager $signatureManager
	 * @param HistoryManager $historyManager
	 * @param User $user
	 */
	public function __construct(array $companyData, CompanyManager $companyManager, EntityManager $entityManager, InvoiceManager $invoiceManager, CurrencyManager $currencyManager, UnitManager $unitManager, CountryManager $countryManager, SignatureManager $signatureManager, HistoryManager $historyManager, User $user)
	{
		$this->companyData = $companyData;
		$this->companyManager = $companyManager;
		$this->entityManager = $entityManager;
		$this->invoiceManager = $invoiceManager;
		$this->currencyManager = $currencyManager;
		$this->unitManager = $unitManager;
		$this->countryManager = $countryManager;
		$this->signatureManager = $signatureManager;
		$this->historyManager = $historyManager;
		$this->user = $user;
	}

	/**
	 * @param string $id
	 * @return array
	 * @throws CurrencyException
	 * @throws EntityManagerException
	 * @throws InvoiceException
	 * @throws UnitException
	 */
	public function getInvoiceById(string $id): array
	{
		try {
			$invoice = $this->invoiceManager->getInvoiceById($id);
			$unit = $this->unitManager->getDefaultUnit();

			$ret = [
				'id' => $invoice->getId(),
				'type' => ($invoice instanceof Invoice ? 'invoice' : 'proforma'),
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
				'textBeforeItems' => $invoice->getTextBeforeItems() ?? '',
				'textAfterItems' => $invoice->getTextAfterItems() ?? '',
				'company' => [
					'id' => $invoice->getCompany() !== null ? $invoice->getCompany()->getId() : null,
					'name' => $invoice->getCompanyName(),
					'address' => $invoice->getCompanyAddress(),
					'city' => $invoice->getCompanyCity(),
					'zipCode' => $invoice->getCompanyPostalCode(),
					'country' => $invoice->getCompanyCountry()->getIsoCode(),
					'ic' => $invoice->getCompanyIc() ?? '',
					'dic' => $invoice->getCompanyDic() ?? '',
				],
				'customer' => [
					'id' => null,
					'name' => $invoice->getCustomerName(),
					'address' => $invoice->getCustomerAddress(),
					'city' => $invoice->getCustomerCity(),
					'zipCode' => $invoice->getCustomerPostalCode(),
					'country' => $invoice->getCustomerCountry()->getIsoCode(),
					'ic' => $invoice->getCustomerIc() ?? '',
					'dic' => $invoice->getCustomerDic() ?? '',
				],
				'items' => [],
				'deposit' => [],
				'taxList' => [],
				'offers' => $invoice->getOffers(),
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
					'itemPriceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())),
					'tax' => $depositInvoice->getTotalTax(),
					'taxFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())),
					'price' => $depositInvoice->getTotalPrice(),
					'priceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())),
				];
			}

			return $ret;
		} catch (NoResultException | NonUniqueResultException $e) {
			return $this->getNewInvoice();
		}
	}

	/**
	 * @param \DateTime $dateTax
	 * @param \DateTime $dateDue
	 * @return string
	 */
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
	 * @return array
	 * @throws CurrencyException
	 * @throws InvoiceException
	 * @throws UnitException
	 * @throws EntityManagerException
	 */
	public function getNewInvoice(): array
	{
		$date = DateTime::from('NOW');
		$dueDate = $date->modifyClone('+14 days');

		$currency = $this->currencyManager->getDefaultCurrency();
		$unit = $this->unitManager->getDefaultUnit();

		$currencyTemp = $this->currencyManager->getCurrencyRateByDate($currency, $date);
		$currencyRate = $currencyTemp->getRate();
		$currencyDate = $currencyTemp->getLastUpdate()->format('d.m.Y');

		return [
			'id' => null,
			'type' => 'invoice',
			'number' => $this->invoiceManager->getNextInvoiceNumber(),
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
			'textBeforeItems' => '',
			'textAfterItems' => '',
			'company' => [
				'id' => null,
				'name' => $this->companyData['name'],
				'address' => $this->companyData['address'],
				'city' => $this->companyData['city'],
				'zipCode' => $this->companyData['zipCode'],
				'country' => $this->companyData['country'],
				'ic' => $this->companyData['ic'],
				'dic' => $this->companyData['dic'],
			],
			'customer' => [
				'id' => null,
				'name' => '',
				'address' => '',
				'city' => '',
				'zipCode' => '',
				'country' => 'CZE',
				'ic' => '',
				'dic' => '',
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
					'taxString' => '21',
					'tax' => 21.0,
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
			'offers' => [],
		];
	}

	/**
	 * @param string $id
	 * @return array|null
	 * @throws EntityManagerException
	 * @throws UnitException
	 */
	public function getFixInvoiceById(string $id): ?array
	{
		try {
			$invoice = $this->invoiceManager->getInvoiceById($id);
			$unit = $this->unitManager->getDefaultUnit();

			$date = $invoice instanceof FixInvoice ? $invoice->getDate() : DateTime::from('NOW');
			$taxDate = $invoice instanceof FixInvoice ? $invoice->getTaxDate() : DateTime::from('NOW');
			$dueDate = $invoice instanceof FixInvoice ? $invoice->getDueDate() : DateTime::from($date)->modify('+30 days');

			$textBeforeItems = $invoice instanceof FixInvoice ? $invoice->getTextBeforeItems() ?? '' : 'Opravný daňový doklad k daňovému dokladu č. ' . $invoice->getNumber();
			$textAfterItems = $invoice instanceof FixInvoice ? $invoice->getTextBeforeItems() ?? '' : '';

			$invoiceId = $invoice instanceof FixInvoice && $invoice->getInvoice() !== null ? $invoice->getInvoice()->getId() : $invoice->getId();

			$ret = [
				'id' => $invoice instanceof FixInvoice ? $invoice->getId() : null,
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
				'textBeforeItems' => $textBeforeItems,
				'textAfterItems' => $textAfterItems,
				'company' => [
					'id' => $invoice->getCompany() !== null ? $invoice->getCompany()->getId() : null,
					'name' => $invoice->getCompanyName(),
					'address' => $invoice->getCompanyAddress(),
					'city' => $invoice->getCompanyCity(),
					'zipCode' => $invoice->getCompanyPostalCode(),
					'country' => $invoice->getCompanyCountry()->getIsoCode(),
					'ic' => $invoice->getCompanyIc() ?? '',
					'dic' => $invoice->getCompanyDic() ?? '',
				],
				'customer' => [
					'id' => null,
					'name' => $invoice->getCustomerName(),
					'address' => $invoice->getCustomerAddress(),
					'city' => $invoice->getCustomerCity(),
					'zipCode' => $invoice->getCustomerPostalCode(),
					'country' => $invoice->getCustomerCountry()->getIsoCode(),
					'ic' => $invoice->getCustomerIc() ?? '',
					'dic' => $invoice->getCustomerDic() ?? '',
				],
				'items' => [],
				'deposit' => [],
				'taxList' => [],
				'offers' => [],
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

				if ($invoice instanceof FixInvoice) {
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
						'itemPriceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())),
						'tax' => $depositInvoice->getTotalTax(),
						'taxFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())),
						'price' => $depositInvoice->getTotalPrice(),
						'priceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())),
					];
				}
			}

			return $ret;
		} catch (NoResultException | NonUniqueResultException $e) {
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
		$date = DateTime::from($invoiceData['date']);
		$dateTax = DateTime::from($invoiceData['dateTax']);

		if ($type === 'proforma') {
			$invoiceData['number'] = $this->invoiceManager->getNextInvoiceNumber($date);
		} else {
			$invoiceData['number'] = $this->invoiceManager->getNextInvoiceNumber($dateTax);
		}

		return $invoiceData;
	}

	/**
	 * @param array $invoiceData
	 * @param MUser|null $user
	 * @return array
	 * @throws CurrencyException
	 * @throws EntityManagerException
	 * @throws UnitException
	 */
	public function saveInvoice(array $invoiceData, ?MUser $user = null): array
	{
		$invoiceId = $invoiceData['id'];
		$invoiceType = $invoiceData['type'];
		$invoiceNumber = $invoiceData['number'];
		$variableSymbol = str_replace('O', '99', $invoiceNumber);
		$customerId = $invoiceData['customer']['id'];
		$customerIc = $invoiceData['customer']['ic'];
		$totalPrice = (float) $invoiceData['totalPriceRounded'];
		$totalTax = (float) $invoiceData['totalTax'];

		try {
			$currency = $this->currencyManager->getCurrencyById($invoiceData['currencyData']['id']);
		} catch (NoResultException | NonUniqueResultException $e) {
			$currency = $this->currencyManager->getDefaultCurrency();
		}

		$currencyRate = (float) $invoiceData['currencyData']['rate'];
		$currencyDate = DateTime::from($invoiceData['currencyData']['rateDate']);

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
			/** @var User $user */
			$user = $this->user->getIdentity();
		}

		// Načtení Invoice
		$invoice = null;
		try {
			if ($invoiceId !== null) {
				$invoice = $this->invoiceManager->getInvoiceById($invoiceId);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$invoice = null;
		}

		if ($invoice === null) {
			if ($invoiceType === 'invoice') {
				$invoice = new Invoice($invoiceNumber);
			} elseif ($invoiceType === 'fixInvoice') {
				$invoice = new FixInvoice($invoiceNumber);
				$parentId = $invoiceData['invoiceId'];

				try {
					$parent = $this->invoiceManager->getInvoiceById($parentId);
					if ($parent instanceof Invoice) {
						$invoice->setInvoice($parent);
						$parent->setFixInvoice($invoice);
					}
				} catch (NoResultException | NonUniqueResultException $e) {

				}
			} else {
				$invoice = new InvoiceProforma($invoiceNumber);
			}

			$invoice->setCreateUser($user);
			$invoice->setCreateDate(DateTime::from('NOW'));
			$invoice->setEditUser($user);
			$invoice->setEditDate(DateTime::from('NOW'));

			if ($invoice instanceof FixInvoice) {
				$changeDescription = 'Vytvoření opravného daňového dokladu';
			} elseif ($invoice instanceof InvoiceProforma) {
				$changeDescription = 'Vytvoření proformy';
			} else {
				$changeDescription = 'Vytvoření faktury';
			}
		} else {
			$invoice->setEditUser($user);
			$invoice->setEditDate(DateTime::from('NOW'));

			if ($invoice instanceof FixInvoice) {
				$changeDescription = 'Úprava opravného daňového dokladu';
			} elseif ($invoice instanceof InvoiceProforma) {
				$changeDescription = 'Úprava proformy';
			} else {
				$changeDescription = 'Úprava faktury';
			}
		}

		//Nastaveni company z katalogu, pokud existuje
		if ($customerId !== null) {
			try {
				$company = $this->companyManager->getCompanyById($customerId);
				$invoice->setCompany($company);
			} catch (NoResultException | NonUniqueResultException $e) {
				$customerId = null;
			}
		}

		if ($customerId === null) {
			try {
				$company = $this->companyManager->getCompanyByIco($customerIc);
				$invoice->setCompany($company);
			} catch (NoResultException | NonUniqueResultException $e) {

			}
		}

		//Nacteni zeme spolecnosti
		try {
			$country = $this->countryManager->getCountryByIsoCode($this->companyData['country']);
		} catch (NoResultException | NonUniqueResultException $e) {
			try {
				$country = $this->countryManager->getCountryByIsoCode('CZE');
			} catch (NoResultException | NonUniqueResultException $e) {
				$country = null;
			}
		}

		//Nacteni zeme zakaznika
		try {
			$customerCountry = $this->countryManager->getCountryByIsoCode($invoiceData['customer']['country']);
		} catch (NoResultException | NonUniqueResultException $e) {
			try {
				$customerCountry = $this->countryManager->getCountryByIsoCode('CZE');
			} catch (NoResultException | NonUniqueResultException $e) {
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
		$invoice->setCompanyIc($invoiceData['company']['ic']);
		$invoice->setCompanyDic($invoiceData['company']['ic'] === '' || $invoiceData['company']['dic'] === null ? null : $invoiceData['company']['dic']);
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
		$invoice->setCustomerIc($invoiceData['customer']['ic']);
		$invoice->setCustomerDic($invoiceData['customer']['ic'] === '' || $invoiceData['customer']['dic'] === null ? null : $invoiceData['customer']['dic']);

		//cisla
		$invoice->setOrderNumber($invoiceData['orderNumber'] === '' ? null : $invoiceData['orderNumber']);
		$invoice->setRentNumber($invoiceData['rentNumber'] === '' ? null : $invoiceData['rentNumber']);
		$invoice->setContractNumber($invoiceData['contractNumber'] === '' ? null : $invoiceData['contractNumber']);

		//Nastaveni celkove ceny
		$invoice->setTotalPrice($totalPrice);
		$invoice->setTotalTax($totalTax);

		//Data
		$invoice->setDate(DateTime::from($invoiceData['date']));
		$invoice->setDueDate(DateTime::from($invoiceData['dateDue']));
		if ($invoice instanceof InvoiceProforma) {
			$invoice->setTaxDate(DateTime::from($invoiceData['date']));
		} else {
			$invoice->setTaxDate(DateTime::from($invoiceData['dateTax']));
		}

		//platební metody
		$invoice->setPayMethod($invoiceData['payMethod']);

		//Podpis autora faktury
		$invoice->setSignImage($this->signatureManager->getSignatureLink($invoice->getCreateUser()));

		//Poznamky
		$invoice->setTextBeforeItems($invoiceData['textBeforeItems']);
		$invoice->setTextAfterItems($invoiceData['textAfterItems']);

		//Persistnuti faktury
		$this->entityManager->persist($invoice);
		$this->entityManager->flush($invoice);

		//Historie faktury
		$invoiceHistory = new InvoiceHistory($invoice, $changeDescription);
		$invoiceHistory->setUser($user ?? null);

		$this->entityManager->persist($invoiceHistory);

		$invoice->addHistory($invoiceHistory);

		//Přidaní položek
		$this->clearInvoiceItems($invoice);
		$position = 0;
		foreach ($invoiceData['items'] as $itemData) {
			if (!$invoice instanceof FixInvoice || (float) $itemData['count'] !== 0.0) {
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
					} catch (NoResultException | NonUniqueResultException $e) {
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
				$depositInvoice = $this->invoiceManager->getInvoiceById($depositInvoiceData['id']);
				$invoice->addDepositInvoice($depositInvoice);
				$depositInvoice->addDepositingInvoice($invoice);

				if ($depositInvoice instanceof InvoiceProforma && $invoice instanceof Invoice) {
					$depositInvoice->setInvoice($invoice);
				}
			} catch (NoResultException | NonUniqueResultException $e) {

			}
		}

		//nabidky
		$invoice->setOffers(null);
		foreach ($invoiceData['offers'] as $offerId) {
			$invoice->addOffer($offerId);
		}

		$invoice->setStatus(InvoiceStatus::CREATED);
		$invoice->setAcceptStatus1(InvoiceStatus::WAITING);
		$invoice->setAcceptStatus2(InvoiceStatus::WAITING);
		$invoice->setSubmitted(false);

		if ($invoice instanceof FixInvoice) {
			$invoice->setPayDate(DateTime::from('NOW'));
		} elseif ($invoice instanceof Invoice && $invoice->getTotalPrice() === 0.0) {
			$proforma = $invoice->getProforma();
			if ($proforma !== null) {
				$invoice->setPayDate($proforma->getPayDate());
			} else {
				$invoice->setPayDate(DateTime::from('NOW'));
			}
		} else {
			$invoice->setPayDate(null);
		}

		//Finalni ulozeni do databaze
		$this->entityManager->flush();

		$this->historyManager->addHistory(
			$changeDescription,
			$changeDescription . ' č.: ' . $invoice->getNumber(),
			'fas fa-edit', 'bg-warning'
		);

		$invoiceData['id'] = $invoice->getId();

		return $invoiceData;
	}

	/**
	 * @param InvoiceCore $invoice
	 * @throws EntityManagerException
	 */
	private function clearInvoiceItems(InvoiceCore $invoice): void
	{
		foreach ($invoice->getItems() as $item) {
			$invoice->removeItem($item);
			$this->entityManager->remove($item);
		}
	}

	/**
	 * @param $unitId
	 * @return Unit
	 * @throws EntityManagerException
	 * @throws UnitException
	 */
	private function getUnit($unitId): Unit
	{
		static $cache;

		if ($cache === null) {
			$cache = $this->unitManager->getUnits();
		}

		foreach ($cache as $unit) {
			if ($unit->getId() === $unitId) {
				return $unit;
			}
		}

		return $this->unitManager->getDefaultUnit();
	}

	/**
	 * @param array $invoiceData
	 * @param string $depositNumber
	 * @return array
	 * @throws InvoiceException
	 */
	public function addDepositInvoice(array $invoiceData, string $depositNumber): array
	{
		try {
			$depositInvoice = $this->invoiceManager->getInvoiceByCode($depositNumber);

			if ($invoiceData['customer']['ic'] === '' && $invoiceData['customer']['name'] === '') {
				$invoiceData['customer'] = [
					'id' => null,
					'name' => $depositInvoice->getCustomerName(),
					'address' => $depositInvoice->getCustomerAddress(),
					'city' => $depositInvoice->getCustomerCity(),
					'zipCode' => $depositInvoice->getCustomerPostalCode(),
					'country' => $depositInvoice->getCustomerCountry()->getIsoCode(),
					'ic' => $depositInvoice->getCustomerIc() ?? '',
					'dic' => $depositInvoice->getCustomerDic() ?? '',
				];
			} elseif ($invoiceData['customer']['ic'] !== $depositInvoice->getCustomerIc()) {
				throw new InvoiceException('IČ odběratele na zálohové faktuře se neshoduje s IČ odběratele na upravované faktuře.');
			}

			if ($invoiceData['currency'] !== $depositInvoice->getCurrency()->getCode()) {
				throw new InvoiceException('Měna na zálohové faktuře se neshoduje s měnou na upravované faktuře.');
			}

			foreach ($invoiceData['deposit'] as $deposit) {
				if ($deposit['id'] === $depositInvoice->getId()) {
					throw new InvoiceException('Záloha byla již odečtena.');
				}
			}

			$invoiceData['deposit'][] = [
				'id' => $depositInvoice->getId(),
				'number' => $depositInvoice->getNumber(),
				'itemPrice' => $depositInvoice->getItemTotalPrice(),
				'itemPriceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getItemTotalPrice(), $depositInvoice->getCurrency())),
				'tax' => $depositInvoice->getTotalTax(),
				'taxFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalTax(), $depositInvoice->getCurrency())),
				'price' => $depositInvoice->getTotalPrice(),
				'priceFormatted' => str_replace('&nbsp;', ' ', Number::formatPrice($depositInvoice->getTotalPrice(), $depositInvoice->getCurrency())),
			];
		} catch (NoResultException | NonUniqueResultException $e) {
			throw new InvoiceException('Zálohová faktura s číslem: ' . $depositNumber . ' neexistuje.');
		}

		return $invoiceData;
	}

}