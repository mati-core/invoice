<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Country\CountryManager;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Unit\UnitManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Supplier\SupplierManagerAccessor;
use Nette\Security\User;
use Tracy\Debugger;

class ExpenseManager
{
	public function __construct(
		private EntityManagerInterface $entityManager,
		private UnitManager $unitManager,
		private ExpenseManagerAccessor $expenseManager,
		private CurrencyManagerAccessor $currencyManager,
		private User $user,
		private CountryManager $countryManager,
		private SupplierManagerAccessor $supplierManager
	) {
	}


	/**
	 * @return array<string, string>
	 */
	public static function getProductCodes(): array
	{
		return [
			'8431 20 00' => 'Části vozíků vidlicových stohovacích apod., se zařízením zdvihacím, manipulačním',
			'8427 10 10' => 'Samohybné vozíky poháněné elektrickým motorem s výškou zdvihu 1m nebo vyšším.',
			'8427 10 90' => 'Samohybné vozíky poháněné elektrickým motorem ostatní.',
			'8427 20 11' => 'Vozíky vidlicové zdvihací terénní apod.stohovací,zdvih 1m a víc, ne s el. motorem',
			'8427 20 19' => 'Vozíky zdvihací samohybné ostatní, zdvih 1m a víc, ne s el. motorem',
			'8427 20 90' => 'Vozíky zdvihací samohybné ostatní, zdvih do 1m, ne s el. motorem',
			'8427 90 00' => 'Vozíky ostatní se zařízením zdvihacím, manipulačním, ne s el. motorem',
		];
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getExpenseById(string $id): Expense
	{
		return $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->where('e.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return ExpenseHistory[]
	 */
	public function getHistory(Expense $expense): array
	{
		return $this->entityManager->getRepository(ExpenseHistory::class)
			->createQueryBuilder('eh')
			->where('eh.expense = :id')
			->setParameter('id', $expense->getId())
			->orderBy('eh.date', 'DESC')
			->getQuery()
			->getResult();
	}


	public function getNextNumber(): string
	{
		$date = date('Y') . '-' . date('m') . '-01';
		try {
			$count = $this->entityManager->getRepository(Expense::class)
					->createQueryBuilder('e')
					->select('count(e)')
					->where('e.createDate > :date')
					->setParameter('date', $date)
					->getQuery()
					->getSingleScalarResult() ?? 0;
		} catch (NoResultException | NonUniqueResultException $e) {
			Debugger::log($e);
			$count = 0;
		}

		$count++;
		$countString = (string) $count;
		while (strlen($countString) < 4) {
			$countString = '0' . $countString;
		}

		return 'PF' . date('y') . date('m') . $countString;
	}


	/**
	 * @return array
	 */
	public function getNewExpense(): array
	{
		try {
			$defaultUnit = $this->unitManager->getDefaultUnit()->getId();
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
			'deliveryType' => Expense::DELIVERY_TYPE_ROAD,
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
			'payMethod' => Expense::PAY_METHOD_BANK,
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
	 * @return array
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getExpenseStructureById(string $id): array
	{
		try {
			$defaultUnit = $this->unitManager->getDefaultUnit()->getId();
		} catch (UnitException $e) {
			Debugger::log($e);
			$defaultUnit = '';
		}

		$expense = $this->expenseManager->get()->getExpenseById($id);

		$items = [];
		$invoiceNumber = '';
		$variableSymbol = '';
		$datePrint = '';

		$deliveryType = Expense::DELIVERY_TYPE_ROAD;
		$weight = '';
		$productCode = '';

		$supplierName = '';
		$supplierAddress = '';
		$supplierCity = '';
		$supplierZip = '';
		$supplierCountry = 'CZE';
		$supplierCin = '';
		$supplierTin = '';

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
			$supplierCin = $expense->getSupplierCin() ?? '';
			$supplierTin = $expense->getSupplierTin() ?? '';

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
				'cin' => $supplierCin,
				'tin' => $supplierTin,
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
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function saveExpense(array $expenseData): array
	{
		$date = new \DateTime($expenseData['date']);
		$isNew = false;
		$userId = $this->user->getId();

		try {
			if ($expenseData['currencyData']['id'] === null) {
				$currency = $this->currencyManager->get()->getMainCurrency();
			} else {
				$currency = $this->currencyManager->get()->getCurrency($expenseData['currencyData']['id']);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			throw new \InvalidArgumentException('Požadovaná měna nebyla nalezena.');
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
			$expense = new Expense(
				$this->expenseManager->get()->getNextNumber(),
				$expenseData['description'],
				$currency,
				(float) $expenseData['price'],
				$date
			);
			$expenseInvoice = new ExpenseInvoice(
				$expense,
				$expenseData['customer']['name']
			);
			$expense->setCategory($expenseData['category']);
			$expense->setCreateUser($userId);

			$this->entityManager->persist($expense);
			$this->entityManager->persist($expenseInvoice);
			$isNew = true;
			$expenseData['id'] = $expense->getId();
		} else {
			$expense = new Expense(
				$this->expenseManager->get()->getNextNumber(),
				$expenseData['description'],
				$currency,
				(float) $expenseData['price'],
				$date
			);

			if ($expenseData['type'] === 'tax') {
				$expense->setCategory(ExpenseCategory::DPH);
			} elseif ($expenseData['type'] === 'state') {
				$expense->setCategory(ExpenseCategory::ODVOD_STATU);
			} elseif ($expenseData['type'] === 'wage') {
				$expense->setCategory(ExpenseCategory::PROVOZNI_NAKLADY_MZDY);
			} elseif ($expenseData['type'] === 'default') {
				$expense->setCategory($expenseData['category']);
			}

			$expense->setCreateUser($userId);
			$this->entityManager->persist($expense);
			$isNew = true;
			$expenseData['id'] = $expense->getId();
		}

		if ($isNew === false) {
			$expense->setCategory($expenseData['category']);
			$expense->setDescription($expenseData['description']);
			$expense->setCurrency($currency);
		}

		// price
		$expense->setRate($expenseData['currencyData']['rate']);
		$expense->setTotalPrice($expenseData['price']);
		$expense->setTotalTax(0.0);

		// automatic paid
		if (in_array($expenseData['payMethod'], [Expense::PAY_METHOD_CARD, Expense::PAY_METHOD_CASH], true)) {
			$expenseData['datePay'] = $expenseData['date'];
		}

		// dates
		$payDateRaw = $expenseData['datePay'];
		if ($payDateRaw !== '' && $payDateRaw !== null) {
			$payDate = new \DateTime($payDateRaw);
			$expense->setPaid(true);
			$expense->setPayDate($payDate);
			$expense->setPayMethod($expenseData['payMethod']);
		} else {
			$expense->setPaid(false);
			$expense->setPayDate(null);
		}

		$dueDateRaw = $expenseData['dateDue'];
		if ($dueDateRaw !== '' && $dueDateRaw !== null) {
			$dueDate = new \DateTime($dueDateRaw);
			$expense->setDueDate($dueDate);
		} else {
			$now = new \DateTime;
			$now->modify('+3 days');

			$expense->setDueDate($now);
		}

		// visibility
		$expense->setHidden($expenseData['hidden']);

		// note
		$expense->setNote($expenseData['note'] === '' ? null : $expenseData['note']);

		if ($expense instanceof ExpenseInvoice) {
			$expense->setTotalTax($expenseData['tax'] ?? 0.0);
			$expense->setSupplierInvoiceNumber(
				$expenseData['invoiceNumber'] === ''
					? null
					: $expenseData['invoiceNumber']
			);
			$expense->setVariableSymbol($expenseData['variableSymbol'] === '' ? null : $expenseData['variableSymbol']);
			$expense->setDeliveryType((int) $expenseData['deliveryType']);
			$expense->setWeight((float) str_replace(',', '.', $expenseData['weight']));
			$expense->setProductCode($expenseData['productCode'] === '' ? null : $expenseData['productCode']);

			try {
				$country = $this->countryManager->getByCode($expenseData['customer']['country']);
			} catch (NoResultException | NonUniqueResultException) {
				$country = null;
			}

			$expense->setSupplierName($expenseData['customer']['name']);
			$expense->setSupplierStreet(
				$expenseData['customer']['address'] === ''
					? null
					: $expenseData['customer']['address']
			);
			$expense->setSupplierCity(
				$expenseData['customer']['city'] === ''
					? null
					: $expenseData['customer']['city']
			);
			$expense->setSupplierZipCode(
				$expenseData['customer']['zipCode'] === ''
					? null
					: $expenseData['customer']['zipCode']
			);
			$expense->setSupplierCountry($country);
			$expense->setSupplierCin($expenseData['customer']['cin'] === '' ? null : $expenseData['customer']['cin']);
			$expense->setSupplierTin($expenseData['customer']['tin'] === '' ? null : $expenseData['customer']['tin']);
			$expense->setSupplierBankAccount(null);
			$expense->setSupplierIBAN(null);
			$expense->setSupplierSWIFT(null);

			//DATUM
			$expense->setDatePrint(
				$expenseData['datePrint'] === '' || $expenseData['datePrint'] === null
					? null
					: new \DateTime($expenseData['datePrint'])
			);

			foreach ($expense->getItems() as $item) {
				$this->entityManager->remove($item);
			}
			$expense->resetItems();

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
			$history->setUserId($userId);
			$this->entityManager->persist($history);
		} else {
			$history = new ExpenseHistory($expense, 'Úprava nákladu');
			$history->setUserId($userId);
			$this->entityManager->persist($history);
		}
		$this->entityManager->flush();

		return $expenseData;
	}


	/**
	 * @return array{name: string, address: string, city: string, zipCode: string, country: string, cin: string, tin:
	 *     string}
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getSupplierData(string $id): array
	{
		$supplier = $this->supplierManager->get()->getSupplierById($id);

		return [
			'name' => $supplier->getName(),
			'address' => $supplier->getAddress()->getStreet() ?? '',
			'city' => $supplier->getAddress()->getCity() ?? '',
			'zipCode' => $supplier->getAddress()->getZip() ?? '',
			'country' => $supplier->getAddress()->getCountry() !== null
				? $supplier->getAddress()->getCountry()->getIsoCode()
				: 'CZE',
			'cin' => $supplier->getAddress()->getCin() ?? '',
			'tin' => $supplier->getAddress()->getTin() ?? '',
		];
	}
}
