<?php

declare(strict_types=1);


namespace MatiCore\Expense;


use Baraja\Doctrine\EntityManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Address\CountryManager;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\CurrencyManager;
use MatiCore\Invoice\Expense;
use MatiCore\Invoice\ExpenseCategory;
use MatiCore\Invoice\ExpenseDeliveryType;
use MatiCore\Invoice\ExpenseException;
use MatiCore\Invoice\ExpenseHistory;
use MatiCore\Invoice\ExpenseInvoice;
use MatiCore\Invoice\ExpenseInvoiceItem;
use MatiCore\Invoice\ExpenseManagerAccessor;
use MatiCore\Invoice\ExpensePayMethod;
use MatiCore\Supplier\SupplierManagerAccessor;
use MatiCore\Unit\UnitException;
use MatiCore\Unit\UnitManager;
use MatiCore\User\BaseUser;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Class ExpenseHelper
 * @package MatiCore\Expense
 */
class ExpenseHelper
{

	/**
	 * @var EntityManager
	 */
	private EntityManager $entityManager;

	/**
	 * @var CurrencyManager
	 */
	private CurrencyManager $currencyManager;

	/**
	 * @var UnitManager
	 */
	private UnitManager $unitManager;

	/**
	 * @var ExpenseManagerAccessor
	 */
	private ExpenseManagerAccessor $expenseManager;

	/**
	 * @var CountryManager
	 */
	private CountryManager $countryManager;

	/**
	 * @var SupplierManagerAccessor
	 */
	private SupplierManagerAccessor $supplierManager;

	/**
	 * @var User
	 */
	private User $user;

	/**
	 * ExpenseHelper constructor.
	 * @param EntityManager $entityManager
	 * @param UnitManager $unitManager
	 * @param ExpenseManagerAccessor $expenseManager
	 * @param CurrencyManager $currencyManager
	 * @param User $user
	 * @param CountryManager $countryManager
	 * @param SupplierManagerAccessor $supplierManager
	 */
	public function __construct(
		EntityManager $entityManager,
		UnitManager $unitManager,
		ExpenseManagerAccessor $expenseManager,
		CurrencyManager $currencyManager,
		User $user,
		CountryManager $countryManager,
		SupplierManagerAccessor $supplierManager
	)
	{
		$this->entityManager = $entityManager;
		$this->unitManager = $unitManager;
		$this->expenseManager = $expenseManager;
		$this->currencyManager = $currencyManager;
		$this->countryManager = $countryManager;
		$this->supplierManager = $supplierManager;
		$this->user = $user;
	}

	/**
	 * @return array
	 */
	public function getNewExpense(): array
	{
		try {
			$unit = $this->unitManager->getDefaultUnit();
			$defaultUnit = $unit->getId();
		} catch (UnitException $e) {
			Debugger::log($e);
			$defaultUnit = '';
		}

		return [
			'id' => null,
			'type' => 'invoice',
			'category' => 'nakup-nahradni-dily',
			'description' => '',
			'number' => '',
			'invoiceNumber' => '',
			'variableSymbol' => '',
			'variableSymbolError' => 0,
			'deliveryType' => ExpenseDeliveryType::ROAD,
			'weight' => '',
			'productCode' => '',
			'customer' => [
				'name' => '',
				'address' => '',
				'city' => '',
				'zipCode' => '',
				'country' => 'CZE',
				'ic' => '',
				'dic' => '',
			],
			'currency' => 'CZK',
			'currencyData' => [
				'id' => null,
				'code' => 'CZK',
				'symbol' => 'Kč',
				'rate' => 1,
				'rateString' => '1',
				'rateReal' => 1,
				'rateRealString' => '1',
				'rateDate' => '???',
			],
			'priceNoVat' => 0.0,
			'priceNoVatFormatted' => '0',
			'price' => 0.0,
			'priceFormatted' => '0',
			'tax' => 0.0,
			'taxFormatted' => '0',
			'payMethod' => ExpensePayMethod::BANK,
			'date' => date('Y-m-d'),
			'dateError' => false,
			'dateData' => [
				'year' => date('Y'),
				'month' => date('n'),
			],
			'datePrint' => '',
			'datePrintError' => false,
			'dateDue' => '',
			'dateDueError' => false,
			'datePay' => '',
			'datePayError' => false,
			'note' => '',
			'defaultUnit' => $defaultUnit,
			'hidden' => false,
			'items' => [],
			'itemTotalPrice' => 0.0,
		];
	}

	/**
	 * @param string $id
	 * @return array
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getExpenseById(string $id): array
	{
		try {
			$unit = $this->unitManager->getDefaultUnit();
			$defaultUnit = $unit->getId();
		} catch (UnitException $e) {
			Debugger::log($e);
			$defaultUnit = '';
		}

		$expense = $this->expenseManager->get()->getExpenseById($id);

		$items = [];
		$invoiceNumber = '';
		$variableSymbol = '';
		$datePrint = '';

		$deliveryType = ExpenseDeliveryType::ROAD;
		$weight = '';
		$productCode = '';

		$supplierName = '';
		$supplierAddress = '';
		$supplierCity = '';
		$supplierZip = '';
		$supplierCountry = 'CZE';
		$supplierIC = '';
		$supplierDIC = '';

		if ($expense instanceof ExpenseInvoice) {
			$invoiceNumber = $expense->getSupplierInvoiceNumber() ?? '';
			$variableSymbol = $expense->getVariableSymbol() ?? '';

			$deliveryType = $expense->getDeliveryType();
			$weight = str_replace('.', ',', (string) $expense->getWeight());
			$productCode = $expense->getProductCode() ?? '';

			$datePrint = $expense->getDatePrint() === null ? '' : $expense->getDatePrint()->format('Y-m-d');

			$supplierName = $expense->getSupplierName();
			$supplierAddress = $expense->getSupplierStreet() ?? '';
			$supplierCity = $expense->getSupplierCity() ?? '';
			$supplierZip = $expense->getSupplierZipCode() ?? '';
			$supplierCountry = $expense->getSupplierCountry()->getIsoCode();
			$supplierIC = $expense->getSupplierIc() ?? '';
			$supplierDIC = $expense->getSupplierDic() ?? '';

			foreach ($expense->getItems() as $item) {
				$items[] = [
					'count' => $item->getQuantity(),
					'countString' => (string) $item->getQuantity(),
					'description' => $item->getDescription(),
					'id' => $item->getId(),
					'price' => $item->getPricePerItem(),
					'priceString' => (string) $item->getPricePerItem(),
					'tax' => $item->getVat(),
					'taxString' => (string) $item->getVat(),
					'totalPrice' => $item->getTotalPrice(),
					'totalPriceString' => (string) $item->getTotalPrice(),
					'unit' => $item->getUnit()->getId(),
				];
			}
		}

		return [
			'id' => $expense->getId(),
			'type' => $this->getType($expense),
			'category' => $expense->getCategory(),
			'description' => $expense->getDescription(),
			'number' => $expense->getNumber(),
			'invoiceNumber' => $invoiceNumber,
			'variableSymbol' => $variableSymbol,
			'variableSymbolError' => 0,
			'deliveryType' => $deliveryType,
			'weight' => $weight,
			'productCode' => $productCode,
			'customer' => [
				'name' => $supplierName,
				'address' => $supplierAddress,
				'city' => $supplierCity,
				'zipCode' => $supplierZip,
				'country' => $supplierCountry,
				'ic' => $supplierIC,
				'dic' => $supplierDIC,
			],
			'currency' => $expense->getCurrency()->getCode(),
			'currencyData' => [
				'id' => $expense->getCurrency()->getId(),
				'code' => $expense->getCurrency()->getCode(),
				'symbol' => $expense->getCurrency()->getSymbol(),
				'rate' => $expense->getRate(),
				'rateString' => (string) $expense->getRate(),
				'rateReal' => $expense->getCurrency()->getRate(),
				'rateRealString' => (string) $expense->getCurrency()->getRate(),
				'rateDate' => '???',
			],
			'priceNoVat' => round($expense->getTotalPrice() - $expense->getTotalTax(), 2),
			'priceNoVatFormatted' => (string) round($expense->getTotalPrice() - $expense->getTotalTax(), 2),
			'price' => $expense->getTotalPrice(),
			'priceFormatted' => (string) $expense->getTotalPrice(),
			'tax' => $expense->getTotalTax(),
			'taxFormatted' => (string) $expense->getTotalTax(),
			'payMethod' => $expense->getPayMethod(),
			'date' => $expense->getDate()->format('Y-m-d'),
			'dateError' => false,
			'dateData' => [
				'year' => $expense->getDate()->format('Y'),
				'month' => $expense->getDate()->format('n'),
			],
			'datePrint' => $datePrint,
			'datePrintError' => false,
			'dateDue' => $expense->getDueDate() === null ? '' : $expense->getDueDate()->format('Y-m-d'),
			'dateDueError' => false,
			'datePay' => $expense->getPayDate() === null ? '' : $expense->getPayDate()->format('Y-m-d'),
			'datePayError' => false,
			'note' => $expense->getNote(),
			'defaultUnit' => $defaultUnit,
			'hidden' => $expense->isHidden(),
			'items' => $items,
			'itemTotalPrice' => 0.0,
		];
	}

	public function getType(Expense $expense): string
	{
		if ($expense instanceof ExpenseInvoice) {
			return 'invoice';
		}

		if ($expense->getCategory() === ExpenseCategory::DPH) {
			return 'tax';
		}

		if ($expense->getCategory() === ExpenseCategory::ODVOD_STATU) {
			return 'state';
		}

		if ($expense->getCategory() === ExpenseCategory::PROVOZNI_NAKLADY_MZDY) {
			return 'wage';
		}

		return 'default';
	}

	/**
	 * @param array $expenseData
	 * @return array
	 * @throws CurrencyException
	 * @throws ExpenseException
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function saveExpense(array $expenseData): array
	{
		$date = DateTime::from($expenseData['date']);

		$isNew = false;

		/** @var BaseUser|null $user */
		$user = $this->user->getIdentity();

		try {
			if ($expenseData['currencyData']['id'] === null) {
				$currency = $this->currencyManager->getDefaultCurrency();
			} else {
				$currency = $this->currencyManager->getCurrencyById($expenseData['currencyData']['id']);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			Debugger::log($e);
			throw new ExpenseException('Požadovaná měna nebyla nalezena.');
		}

		if ($expenseData['id'] !== null) {
			try {
				$expense = $this->expenseManager->get()->getExpenseById($expenseData['id']);
			} catch (NoResultException | NonUniqueResultException $e) {
				Debugger::log($e);
				throw new ExpenseException('Požadovaný náklad nebyl nalezen.');
			}

			$expense->setDate($date);
		} elseif ($expenseData['type'] === 'invoice') {
			$number = $this->expenseManager->get()->getNextNumber();

			$expense = new ExpenseInvoice($number, $expenseData['description'], $currency, (float) $expenseData['price'], $date, $expenseData['customer']['name']);
			$expense->setCategory($expenseData['category']);

			$expense->setCreateUser($user);

			$this->entityManager->persist($expense);
			$isNew = true;

			$expenseData['id'] = $expense->getId();
		} else {
			$number = $this->expenseManager->get()->getNextNumber();

			$expense = new Expense($number, $expenseData['description'], $currency, (float) $expenseData['price'], $date);

			if ($expenseData['type'] === 'tax') {
				$expense->setCategory(ExpenseCategory::DPH);
			} elseif ($expenseData['type'] === 'state') {
				$expense->setCategory(ExpenseCategory::ODVOD_STATU);
			} elseif ($expenseData['type'] === 'wage') {
				$expense->setCategory(ExpenseCategory::PROVOZNI_NAKLADY_MZDY);
			} elseif ($expenseData['type'] === 'default') {
				$expense->setCategory($expenseData['category']);
			}

			$expense->setCreateUser($user);

			$this->entityManager->persist($expense);
			$isNew = true;

			$expenseData['id'] = $expense->getId();
		}

		if ($isNew === false) {
			$expense->setCategory($expenseData['category']);
			$expense->setDescription($expenseData['description']);
			$expense->setCurrency($currency);
		}

		//Cena
		$expense->setRate($expenseData['currencyData']['rate']);
		$expense->setTotalPrice($expenseData['price']);
		$expense->setTotalTax(0.0);

		//AUTOMATICKA UHRADA
		if (in_array($expenseData['payMethod'], [ExpensePayMethod::CARD, ExpensePayMethod::CASH], true)) {
			$expenseData['datePay'] = $expenseData['date'];
		}

		//Datumy
		$payDateRaw = $expenseData['datePay'];
		if ($payDateRaw !== '' && $payDateRaw !== null) {
			$payDate = DateTime::from($payDateRaw);
			$expense->setPaid(true);
			$expense->setPayDate($payDate);
			$expense->setPayMethod($expenseData['payMethod']);
		} else {
			$expense->setPaid(false);
			$expense->setPayDate(null);
		}

		$dueDateRaw = $expenseData['dateDue'];
		if ($dueDateRaw !== '' && $dueDateRaw !== null) {
			$dueDate = DateTime::from($dueDateRaw);
			$expense->setDueDate($dueDate);
		} else {
			$now = DateTime::from('NOW');
			$now->modify('+3 days');

			$expense->setDueDate($now);
		}

		//Viditelnost
		$expense->setHidden($expenseData['hidden']);

		//Poznamka
		$expense->setNote($expenseData['note'] === '' ? null : $expenseData['note']);

		if ($expense instanceof ExpenseInvoice) {
			//DPH
			$expense->setTotalTax($expenseData['tax'] ?? 0.0);

			//DOKLAD
			$expense->setSupplierInvoiceNumber($expenseData['invoiceNumber'] === '' ? null : $expenseData['invoiceNumber']);
			$expense->setVariableSymbol($expenseData['variableSymbol'] === '' ? null : $expenseData['variableSymbol']);
			$expense->setDeliveryType((int) $expenseData['deliveryType']);
			$expense->setWeight((float) str_replace(',', '.', $expenseData['weight']));
			$expense->setProductCode($expenseData['productCode'] === '' ? null : $expenseData['productCode']);

			//DODAVATEL
			try {
				$country = $this->countryManager->getCountryByIsoCode($expenseData['customer']['country']);
			} catch (NoResultException | NonUniqueResultException) {
				$country = null;
			}

			$expense->setSupplierName($expenseData['customer']['name']);
			$expense->setSupplierStreet($expenseData['customer']['address'] === '' ? null : $expenseData['customer']['address']);
			$expense->setSupplierCity($expenseData['customer']['city'] === '' ? null : $expenseData['customer']['city']);
			$expense->setSupplierZipCode($expenseData['customer']['zipCode'] === '' ? null : $expenseData['customer']['zipCode']);
			$expense->setSupplierCountry($country);
			$expense->setSupplierIc($expenseData['customer']['ic'] === '' ? null : $expenseData['customer']['ic']);
			$expense->setSupplierDic($expenseData['customer']['dic'] === '' ? null : $expenseData['customer']['dic']);
			$expense->setSupplierBankAccount(null);
			$expense->setSupplierIBAN(null);
			$expense->setSupplierSWIFT(null);

			//DATUM
			$expense->setDatePrint($expenseData['datePrint'] === '' || $expenseData['datePrint'] === null ? null : DateTime::from($expenseData['datePrint']));

			//POLOZKY
			foreach ($expense->getItems() as $item) {
				$this->entityManager->remove($item);
			}

			$expense->setItems(new ArrayCollection);

			$position = 0;
			foreach ($expenseData['items'] as $itemData) {
				$position++;
				$unit = $this->unitManager->getById($itemData['unit']);
				$item = new ExpenseInvoiceItem(
					$expense,
					$itemData['description'],
					(float) $itemData['count'],
					$unit,
					(float) $itemData['tax'],
					(float) $itemData['price'],
					$position
				);

				$this->entityManager->persist($item);
				$expense->addItem($item);
			}
		}

		if ($isNew) {
			$history = new ExpenseHistory($expense, 'Vložení nákládu do systému.');
			$history->setUser($user);
			$this->entityManager->persist($history);
		} else {
			$history = new ExpenseHistory($expense, 'Úprava nákladu');
			$history->setUser($user);
			$this->entityManager->persist($history);
		}

		$this->entityManager->flush();

		return $expenseData;
	}

	/**
	 * @param string $id
	 * @return array
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getSupplierData(string $id): array
	{
		$supplier = $this->supplierManager->get()->getSupplierById($id);

		return [
			'name' => $supplier->getName(),
			'address' => $supplier->getAddress()->getStreet() ?? '',
			'city' => $supplier->getAddress()->getCity() ?? '',
			'zipCode' => $supplier->getAddress()->getZipCode() ?? '',
			'country' => $supplier->getAddress()->getCountry() !== null
				? $supplier->getAddress()->getCountry()->getIsoCode()
				: 'CZE',
			'ic' => $supplier->getAddress()->getIn() ?? '',
			'dic' => $supplier->getAddress()->getTin() ?? '',
		];
	}

}