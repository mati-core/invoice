<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\Shop\Unit\UnitManagerAccessor;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use MatiCore\Invoice\Expense;
use MatiCore\Invoice\ExpenseCategory;
use MatiCore\Invoice\ExpenseHistory;
use MatiCore\Invoice\ExpenseInvoice;
use MatiCore\Invoice\ExpenseInvoiceItem;
use MatiCore\Invoice\ExpenseManagerAccessor;
use MatiCore\Supplier\SupplierManagerAccessor;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

#[PublicEndpoint]
class CmsInvoiceExpenseEndpoint extends BaseEndpoint
{
	protected string $pageRight = 'page__invoice';

	private Expense|null $expense;


	public function __construct(
		private EntityManager $entityManager,
		private CurrencyManagerAccessor $currencyManager,
		private UnitManagerAccessor $unitManager,
		private CountryManagerAccessor $countryManager,
		private ExpenseManagerAccessor $expenseManager,
		private SupplierManagerAccessor $supplierManager,
	) {
	}


	public function actionDetail(string $expenseId = null): void
	{
		$this->sendJson(
			[
				'adminAccess' => $this->checkAccess('page__expense__admin'),
				'expenseId' => $expenseId,
				'currencyList' => $this->currencyManager->get()->getActiveCurrencies(),
				'unitList' => $this->unitManager->get()->getUnits(),
				'countries' => $this->countryManager->get()->getCountriesActive(),
				'supplierList' => $this->supplierManager->get()->getAll(),
				'productCodes' => ExpenseManager::getProductCodes(),
			]
		);
	}


	public function actionShow(string $id): void
	{
		try {
			$this->expense = $this->expenseManager->get()->getExpenseById($id);
			$this->template->adminAccess = $this->checkAccess('page__expense__admin') ? 1 : 0;
			$this->template->expense = $this->expense;
			$this->template->historyList = $this->expenseManager->get()->getHistory($this->expense);
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Požadovaný výdaj neexistuje.', 'error');
			$this->redirect('default');
		}
	}


	public function createComponentExpenseTable(string $name): MatiDataGrid
	{
		$currency = $this->currencyManager->get()->getDefaultCurrency();
		$grid = new MatiDataGrid($this, $name);

		if ($this->checkAccess('page__expense__admin')) {
			$rows = $this->entityManager
				->getRepository(Expense::class)
				->createQueryBuilder('e')
				->where('e.deleted = :f')
				->setParameter('f', false)
				->orderBy('e.number', 'DESC');
		} else {
			$rows = $this->entityManager
				->getRepository(Expense::class)
				->createQueryBuilder('e')
				->where('e.hidden = :f')
				->andWhere('e.deleted = :f')
				->setParameter('f', false)
				->orderBy('e.number', 'DESC');
		}

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(
				function (Expense $expense): string
				{
					$link = $this->link('show', ['id' => $expense->getId()]);

					return '<a href="' . $link . '">' . $expense->getNumber() . '</a>'
						. '<br><small>' . (ExpenseCategory::LIST[$expense->getCategory()] ?? '?') . '</small>';
				}
			)
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('supplier', 'Název')
			->setRenderer(
				static function (Expense $e): string
				{
					if ($e instanceof ExpenseInvoice) {
						$ret = $e->getSupplierName();
					} else {
						$ret = '&nbsp;';
					}

					return '<span class="text-info">' . $ret . '</span><br><small>' . $e->getDescription() . '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Zadáno')
			->setRenderer(
				static function (Expense $e): string
				{
					return $e->getCreateDate()->format('d.m.Y') . '<br><small>' . ($e->getCreateUser(
						) === null ? '-' : $e->getCreateUser()->getName()) . '</small>';
				}
			)
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('vs', 'Faktura')
			->setRenderer(
				static function (Expense $e): string
				{
					if ($e instanceof ExpenseInvoice) {
						return $e->getSupplierInvoiceNumber() . '<br><small>VS: ' . $e->getVariableSymbol(
							) . '</small>';
					}

					return '&nbsp;<br><small>&nbsp;</small>';
				}
			)
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('pay', 'Splatnost')
			->setRenderer(
				static function (Expense $e): string
				{
					if ($e->getDueDate() === null) {
						$ret = '-';
					} else {
						$ret = $e->getDueDate()->format('d.m.Y');
					}

					$ret .= '<br><small>';

					if ($e->isPaid() === true) {
						$ret .= '<span class="text-success">Uhrazeno</span>';
					} else {
						$ret .= '<span class="text-warning">čeká</span>';
					}

					return $ret . '</small>';
				}
			)
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('price', 'Částka')
			->setRenderer(
				static function (Expense $e) use ($currency): string
				{
					$totalPrice = $e->getTotalPrice();

					return '<b>' . Number::formatPrice($totalPrice, $e->getCurrency(), 2) . '</b>'
						. '<br>'
						. '<small>'
						. Number::formatPrice($totalPrice * $e->getRate(), $currency, 2)
						. '</small>';
				}
			)
			->setAlign('right')
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addAction('show', 'Zobrazit')
			->setRenderer(
				function (Expense $e): string
				{
					$link = $this->link('Expense:show', ['id' => $e->getId()]);

					return '<a href="' . $link . '" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>';
				}
			);

		if ($this->checkAccess('page__invoice__accepted_delete')) {
			$grid->addAction('delete', 'Delete')
				->setRenderer(
					function (Expense $expense)
					{
						$link = $this->link('delete!', ['id' => $expense->getId()]);

						return '<btn-delete redirect="' . $link . '"></btn-delete>';
					}
				);
		}

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');
		$grid->addFilterDateRange('dueDate', 'Datum splatnosti:');

		//Cislo nakladu
		$grid->addFilterText('number', 'Číslo:');


		//Castka
		$grid->addFilterRange('totalPrice', 'Částka:');

		//Dodavatel
		$grid->addFilterText('supplierName', 'Dodavatel:')
			->setCondition(
				static function (QueryBuilder $qb, string $txt): QueryBuilder
				{
					$qb->innerJoin(ExpenseInvoice::class, 'eia', 'WITH', 'eia.id = e.id');
					$qb->andWhere('eia.supplierName LIKE :supplierName')
						->setParameter('supplierName', '%' . $txt . '%');

					return $qb;
				}
			);

		//Cislo orig faktury
		$grid->addFilterText('invoiceNumber', 'Číslo faktury:')
			->setCondition(
				static function (QueryBuilder $qb, string $txt): QueryBuilder
				{
					$qb->innerJoin(ExpenseInvoice::class, 'eib', 'WITH', 'eib.id = e.id');
					$qb->andWhere('eib.supplierInvoiceNumber LIKE :invoiceNumber')
						->setParameter('invoiceNumber', '%' . $txt . '%');

					return $qb;
				}
			);

		//vs
		$grid->addFilterText('vs', 'Variabilní symbol:')
			->setCondition(
				static function (QueryBuilder $qb, string $txt): QueryBuilder
				{
					$qb->innerJoin(ExpenseInvoice::class, 'eic', 'WITH', 'eic.id = e.id');
					$qb->andWhere('eic.variableSymbol LIKE :vs')
						->setParameter('vs', '%' . $txt . '%');

					return $qb;
				}
			);

		//Popis
		$grid->addFilterText('description', 'Popis:');

		//Stav
		$statusList = [
			'' => 'Vše',
			'paid' => 'Uhrazené',
			'unpaid' => 'Neuhrazené',
			'overDate' => 'Po splatnosti',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(
				static function (QueryBuilder $qb, string $status): QueryBuilder
				{
					if ($status === 'unpaid') {
						$qb->andWhere('e.paid = :f')
							->setParameter('f', false);
					} elseif ($status === 'paid') {
						$qb->andWhere('e.paid = :t')
							->setParameter('t', true);
					} elseif ($status === 'overDate') {
						$qb->andWhere('e.paid = :f')
							->setParameter('f', false);
						$qb->andWhere('e.dueDate < :now')
							->setParameter('now', (new \DateTime)->format('Y-m-d'));
					}

					return $qb;
				}
			);

		//Category
		if ($this->checkAccess('page__expense__admin')) {
			$categoryList = ExpenseCategory::LIST;
		} else {
			$categoryList = ExpenseCategory::LIST;
		}
		$categoryList = array_merge(['' => 'Vše'], $categoryList);
		$grid->addFilterSelect('category', 'Kategorie:', $categoryList, 'category');

		//polozka
		$grid->addFilterText('itemName', 'Položka:')
			->setCondition(
				static function (QueryBuilder $qb, string $txt): QueryBuilder
				{
					$qb->innerJoin(ExpenseInvoice::class, 'eid', 'WITH', 'eid.id = e.id');
					$qb->join(ExpenseInvoiceItem::class, 'item', 'WITH', 'eid.id = item.expense');
					$qb->andWhere('item.description LIKE :itemDescription')
						->setParameter('itemDescription', '%' . $txt . '%');

					return $qb;
				}
			);

		$grid->setOuterFilterRendering();

		return $grid;
	}


	/**
	 * @return array
	 */
	public function getStatistics(): array
	{
		$dateStart = new \DateTime(date('Y') . '-' . date('m') . '-01 00:00:00');
		$dateStop = $dateStart->modifyClone('+1 month');

		$expenseDataMonth = $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->select('SUM(e.totalPrice * e.rate) as totalPrice, SUM(e.totalTax) as totalTax')
			->where('e.date >= :dateStart')
			->andWhere('e.date < :dateStop')
			->andWhere('e.deleted = :f')
			->setParameter('f', false)
			->setParameter('dateStart', $dateStart->format('Y-m-d'))
			->setParameter('dateStop', $dateStop->format('Y-m-d'))
			->getQuery()
			->getScalarResult();

		$dateStart = new \DateTime(date('Y') . '-01-01 00:00:00');
		$dateStop = $dateStart->modifyClone('+1 year');

		$expenseDataYear = $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->select('SUM(e.totalPrice * e.rate) as totalPrice, SUM(e.totalTax) as totalTax')
			->where('e.date >= :dateStart')
			->andWhere('e.date < :dateStop')
			->andWhere('e.deleted = :f')
			->setParameter('f', false)
			->setParameter('dateStart', $dateStart->format('Y-m-d'))
			->setParameter('dateStop', $dateStop->format('Y-m-d'))
			->getQuery()
			->getScalarResult();

		$expenseUnpaid = $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->select('SUM(e.totalPrice * e.rate) as totalPrice, COUNT(e) as count')
			->where('e.paid = :f')
			->andWhere('e.deleted = :f')
			->setParameter('f', false)
			->getQuery()
			->getScalarResult();

		$expenseOverDate = $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->select('SUM(e.totalPrice * e.rate) as totalPrice, COUNT(e) as count')
			->where('e.paid = :f')
			->andWhere('e.deleted = :f')
			->andWhere('e.dueDate < :date')
			->setParameter('f', false)
			->setParameter('date', (new \DateTime)->format('Y-m-d'))
			->getQuery()
			->getScalarResult();

		return [
			'monthName' => Date::getCzechMonthName(new \DateTime),
			'pricePerMonth' => $expenseDataMonth[0]['totalPrice'] ?? 0.0,
			'pricePerYear' => $expenseDataYear[0]['totalPrice'] ?? 0.0,
			'taxPerMonth' => $expenseDataMonth[0]['totalTax'] ?? 0.0,
			'taxPerYear' => $expenseDataYear[0]['totalTax'] ?? 0.0,
			'unpaidPrice' => $expenseUnpaid[0]['totalPrice'] ?? 0.0,
			'unpaidCount' => $expenseUnpaid[0]['count'] ?? 0.0,
			'overDatePrice' => $expenseOverDate[0]['totalPrice'] ?? 0.0,
			'overDateCount' => $expenseOverDate[0]['count'] ?? 0.0,
			'currency' => $this->currencyManager->get()->getDefaultCurrency(),
		];
	}


	public function createComponentPayForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addDate('date', 'Datum úhrady')
			->setDefaultValue(date('Y-m-d'))
			->setRequired('Zadejte datum úhrady.');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$this->expense->setPaid(true);
				$this->expense->setPayDate($values->date);

				$user = $this->getUser()->getId();
				$text = 'Uhrazeno dne ' . $values->date->format('d.m.Y');
				$history = new ExpenseHistory($this->expense, $text);
				$history->setUserId($user);

				$this->entityManager->persist($history);
				$this->entityManager->flush();

				$this->flashMessage('Faktura byla uhrazena.', 'success');
				$this->redirect('show', ['id' => $this->expense->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání nastala chyba.<br>' . $e->getMessage(), 'error');
				$this->redirect('show', ['id' => $this->expense->getId()]);
			}
		};

		return $form;
	}


	public function handleDelete(string $id): void
	{
		try {
			$expense = $this->expenseManager->get()->getExpenseById($id);
			$expense->setDeleted(true);
			$this->entityManager->flush();
			$this->flashMessage('Náklad byl odstraněn.');
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Požadovaný náklad nebyla nalezena.', 'error');
		}

		$this->redirect('default');
	}
}
