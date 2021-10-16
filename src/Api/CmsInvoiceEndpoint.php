<?php

declare(strict_types=1);


namespace App\AdminModule\Presenters;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManager;
use Baraja\Shop\Unit\UnitManager;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use MatiCore\Company\CompanyManager;
use MatiCore\Invoice\BankMovement;
use MatiCore\Invoice\BankMovementCronLogAccessor;
use MatiCore\Invoice\BankMovementManagerAccessor;
use MatiCore\Invoice\BankMovementStatus;
use MatiCore\Invoice\ExportManagerAccessor;
use MatiCore\Invoice\InvoiceComment;
use MatiCore\Invoice\Invoice;
use MatiCore\Invoice\InvoiceException;
use MatiCore\Invoice\InvoiceHistory;
use MatiCore\Invoice\InvoiceManagerAccessor;
use Mpdf\MpdfException;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Utils\JsonException;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use Tracy\Debugger;

#[PublicEndpoint]
class CmsInvoiceEndpoint extends BaseEndpoint
{
	protected string $pageRight = 'page__invoice';

	private Invoice|null $editedInvoice;

	private int $returnButton = 0;


	public function __construct(
		private EntityManager $entityManager,
		private CompanyManager $companyManager,
		private InvoiceManagerAccessor $invoiceManager,
		private CurrencyManager $currencyManager,
		private BankMovementCronLogAccessor $bankMovementCronLog,
		private UnitManager $unitManager,
		private BankMovementManagerAccessor $bankMovementManager,
		private ExportManagerAccessor $exportManager,
	) {
	}


	/**
	 * @throws AbortException|CurrencyException
	 */
	public function actionShow(string $id, int $ret = 0): void
	{
		$this->returnButton = $ret;

		try {
			$this->editedInvoice = $this->invoiceManager->get()->getInvoiceById($id);
			$this->template->color = $this->invoiceManager->get()->getColorByInvoiceDocument($this->editedInvoice);
			$this->template->templateData = $this->invoiceManager->get()->getInvoiceTemplateData($this->editedInvoice);
			$this->template->invoice = $this->editedInvoice;
			$this->template->contacts = $this->invoiceManager->get()->getInvoiceEmails($this->editedInvoice);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->redirect('default');
		}

		$this->template->returnButton = $this->returnButton;
		$this->template->currency = $this->currencyManager->getDefaultCurrency();
	}


	public function actionDetail(?string $id = null): void
	{
		$this->template->invoiceId = $id;
		$this->template->currencyList = $this->currencyManager->getActiveCurrencies();
		$this->template->unitList = $this->unitManager->getUnits();
		$this->template->companyList = $this->companyManager->getCompanies();
	}


	public function actionDetailFix(string $id): void
	{
		$this->template->invoiceId = $id;
		$this->template->currencyList = $this->currencyManager->getActiveCurrencies();
		$this->template->unitList = $this->unitManager->getUnits();
	}


	/**
	 * @throws JsonException
	 */
	public function actionBankMovements(): void
	{
		if ($this->checkAccess('page__invoice__bank_movements') === false) {
			$this->template->setFile(__DIR__ . '/templates/Error/permissionDeny.latte');
			$this->template->missingPermissions = ['admin'];

			return;
		}

		$log = $this->bankMovementCronLog->get()->getLog();
		$this->template->lastUpdate = $log['date'] ?? null;
		$this->template->lastUpdateStatus = $log['status'] ?? null;
	}


	/**
	 * @throws AbortException
	 */
	public function actionDetailBankMovement(string $id): void
	{
		if ($this->checkAccess('page__invoice__bank_movements') === false) {
			$this->template->setFile(__DIR__ . '/templates/Error/permissionDeny.latte');
			$this->template->missingPermissions = ['admin'];

			return;
		}

		try {
			$bankMovement = $this->bankMovementManager->get()->getById($id);
			$this->template->bankMovement = $bankMovement;
			$this->template->invoice = $bankMovement->getInvoice();
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný bankovní pohyb nebyl nalezen.', 'error');
			$this->redirect('bankMovements');
		}
	}


	public function actionExport(string $id): void
	{
		try {
			$this->exportManager->get()->exportInvoiceToPdf(
				$this->invoiceManager->get()->getInvoiceById($id)
			);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Faktura nebyla nalezena.', 'error');
			$this->redirect('default');
		} catch (MpdfException $e) {
			$this->flashMessage('Při generování PDF nastala chyba: ' . $e->getMessage(), 'error');
			$this->redirect('default');
		}
	}


	public function actionGenerateInvoice(string $id): void
	{
		try {
			$proforma = $this->invoiceManager->get()->getInvoiceById($id);
			if ($proforma->isProforma() === false) {
				$this->flashMessage('Fakturu lze generovat pouze ze zálohé faktury.', 'error');
				$this->redirect('show', ['id' => $id]);
			}

			$invoice = $this->invoiceManager->get()->createInvoiceFromInvoiceProforma($proforma);
			$this->flashMessage('Faktura byla úspěšně vygenerována.', 'success');
			$this->redirect('detail', ['id' => $invoice->getId()]);
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Faktura nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}


	/**
	 * @throws AbortException
	 */
	public function actionInvoicedItems(string $id): void
	{
		try {
			$mainInvoice = $this->invoiceManager->get()->getInvoiceById($id);
			$company = $mainInvoice->getCompany();

			$this->template->invoice = $mainInvoice;
			$this->template->company = $company;
			$this->template->list = $company === null ? [] : $this->companyManager->getInvoicedItems($company);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Faktura nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}


	/**
	 * @throws AbortException
	 */
	public function handleSubmit(string $invoiceId): void
	{
		try {
			$invoice = $this->invoiceManager->get()->getInvoiceById($invoiceId);
			$invoice->setSubmitted(true);
			$invoice->setStatus(Invoice::STATUS_WAITING);

			$entities[] = $invoice;

			$sendEmail = false;

			if ($this->invoiceManager->get()->getAcceptSetting() === null) {
				$invoice->setAcceptStatus1(Invoice::STATUS_ACCEPTED);
				$invoice->setAcceptStatus2(Invoice::STATUS_ACCEPTED);
				$invoice->setStatus(Invoice::STATUS_ACCEPTED);
				$invoice->setClosed(true);

				$userId = $this->getUser()->getId();
				$history = new InvoiceHistory(
					$invoice,
					'<span class="text-success text-bold">Doklad odevzdán a schválen</span>'
				);
				$history->setUserId($userId);
				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;

				if ($invoice->isFix()) {
					$this->flashMessage('Opravný daňový doklad byl odevzdán a schválen.', 'success');
				} elseif ($invoice->isProforma()) {
					$this->flashMessage('Proforma byla odevzdána a schválena.', 'success');
				} else {
					$this->flashMessage('Faktura byla odevzdána a schválena.', 'success');
				}

				$sendEmail = true;
			} elseif ($this->checkAccess('page__invoice__accept-B')) {
				$invoice->setAcceptStatus1(Invoice::STATUS_ACCEPTED);
				$invoice->setAcceptStatus2(Invoice::STATUS_ACCEPTED);
				$invoice->setStatus(Invoice::STATUS_ACCEPTED);
				$invoice->setClosed(true);

				$userId = $this->getUser()->getId();
				$history = new InvoiceHistory(
					$invoice, '<span class="text-success text-bold">Doklad odevzdán a schválen</span>'
				);
				$history->setUserId($userId);
				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;

				if ($invoice->isFix()) {
					$this->flashMessage('Opravný daňový doklad byl odevzdán a schválen.', 'success');
				} elseif ($invoice->isProforma()) {
					$this->flashMessage('Proforma byla odevzdána a schválena.', 'success');
				} else {
					$this->flashMessage('Faktura byla odevzdána a schválena.', 'success');
				}

				$sendEmail = true;
			} elseif ($this->checkAccess('page__invoice__accept-A')) {
				$invoice->setAcceptStatus1(Invoice::STATUS_ACCEPTED);
				$invoice->setAcceptStatus2(Invoice::STATUS_WAITING);

				$userId = $this->getUser()->getId();
				$history = new InvoiceHistory(
					$invoice, '<span class="text-success text-bold">Doklad odevzdán a odeslán ke schválení.</span>'
				);
				$history->setUserId($userId);
				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;

				if ($invoice->isFix()) {
					$this->flashMessage('Opravný daňový doklad byl odevzdán a odeslán ke schválení.', 'info');
				} elseif ($invoice->isProforma()) {
					$this->flashMessage('Proforma byla odevzdána a odeslána ke schválení.', 'info');
				} else {
					$this->flashMessage('Faktura byla odevzdána a odeslána ke schválení.', 'info');
				}
			} else {
				$invoice->setAcceptStatus1(Invoice::STATUS_WAITING);
				$invoice->setAcceptStatus2(Invoice::STATUS_WAITING);

				$userId = $this->getUser()->getId();
				$history = new InvoiceHistory($invoice, 'Doklad odevzdán ke schválení.');
				$history->setUserId($userId);
				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;

				if ($invoice->isFix()) {
					$this->flashMessage('Opravný daňový doklad byl odevzdán ke schválení.', 'info');
				} elseif ($invoice->isProforma()) {
					$this->flashMessage('Proforma byla odevzdána ke schválení.', 'info');
				} else {
					$this->flashMessage('Faktura byla odevzdána ke schválení.', 'info');
				}
			}

			$this->entityManager->flush();

			if ($sendEmail === true) {
				$status = $this->invoiceManager->get()->sendEmailToCompany($invoice);
				if ($status['message'] ?? false) { // show?
					$this->flashMessage($status['message'], $status['type']);
				}
			}
			$this->redirect('default');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná faktura nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}


	/**
	 * @throws AbortException
	 * @throws EntityManagerException
	 */
	public function handleAccept(string $invoiceId, string $type): void
	{
		try {
			$invoice = $this->invoiceManager->get()->getInvoiceById($invoiceId);

			if ($type === 'A') {
				$invoice->setAcceptStatus1(Invoice::STATUS_ACCEPTED);
			} else {
				$invoice->setAcceptStatus2(Invoice::STATUS_ACCEPTED);
			}

			$sendEmail = false;
			if ($invoice->isReady()) {
				$invoice->setStatus(Invoice::STATUS_ACCEPTED);
				$invoice->setClosed(true);
				$sendEmail = true;
			}

			$userId = $this->getUser()->getId();
			$history = new InvoiceHistory($invoice, '<b class="text-success">Faktura schválena.</b>');
			$history->setUserId($userId);
			$this->entityManager->persist($history);

			$invoice->addHistory($history);
			$this->entityManager->flush();

			if ($sendEmail === true) {
				$status = $this->invoiceManager->get()->sendEmailToCompany($invoice);
				if ($status['message'] ?? false) { // show?
					$this->flashMessage($status['message'], $status['type']);
				}
			}

			$this->flashMessage('Faktura byla schválena.', 'info');
			if ($this->returnButton === 1) {
				$this->redirect('Homepage:default');
			} else {
				$this->redirect('Invoice:default');
			}
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Požadovaná faktura nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}


	/**
	 * @throws AbortException
	 */
	public function handleDelete(string $id): void
	{
		try {
			$invoice = $this->invoiceManager->get()->getInvoiceById($id);
			$this->invoiceManager->get()->removeInvoice($invoice);

			$this->flashMessage('Faktura byla stornována a odstraněna.', 'info');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná faktura nebyla nalezena.', 'error');
		} catch (\Exception $e) {
			Debugger::log($e);
			$this->flashMessage('Chyba: ' . $e->getMessage(), 'error');
		}

		$this->redirect('default');
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
				$this->editedInvoice->setPayDate($values->date);
				$this->editedInvoice->setStatus(Invoice::STATUS_PAID);

				$user = $this->getUser()->getId();
				$text = ($this->editedInvoice->isProforma() ? 'Proforma' : 'Faktura')
					. ' uhrazena dne '
					. $values->date->format('d.m.Y');
				$history = new InvoiceHistory($this->editedInvoice, $text);
				$history->setUserId($user);

				$this->entityManager->persist($history);
				$this->editedInvoice->addHistory($history);
				$this->entityManager->flush();

				if ($this->editedInvoice->isProforma()) {
					$pd = $this->invoiceManager->get()->createPayDocumentFromInvoice($this->editedInvoice);
					$this->flashMessage(
						'Proforma byla uhrazena a byl vygenerován doklad o zaplacení č.:' . $pd->getNumber(),
						'success'
					);
					$this->redirect('show', ['id' => $pd->getId()]);
				}

				$this->flashMessage('Faktura byla uhrazena.', 'success');
				$this->redirect('show', ['id' => $this->editedInvoice->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání nastala chyba.<br>' . $e->getMessage(), 'error');
				$this->redirect('show', ['id' => $this->editedInvoice->getId()]);
			}
		};

		return $form;
	}


	/**
	 * @throws DataGridException
	 */
	public function createComponentBankMovementTable(string $name): MatiDataGrid
	{
		$grid = new MatiDataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(BankMovement::class)
				->createQueryBuilder('bm')
				->orderBy('bm.date', 'DESC')
		);

		$grid->setRowCallback(
			static function (BankMovement $bm, Html $row): void
			{
				$status = $bm->getStatus();
				if ($status === BankMovement::STATUS_SUCCESS || $status === BankMovement::STATUS_DONE) {
					return;
				}

				if ($status === BankMovement::STATUS_NOT_PROCESSED) {
					$row->addClass('table-info');

					return;
				}

				if ($status === BankMovement::STATUS_IS_PAID || $status === BankMovement::STATUS_BAD_VARIABLE_SYMBOL) {
					$row->addClass('table-warning');

					return;
				}

				$row->addClass('table-danger');
			}
		);

		$grid->addColumnText('date', 'Datum')
			->setRenderer(
				static function (BankMovement $bm): string
				{
					return $bm->getDate()->format('d.m.Y')
						. '<br>'
						. '<small class="' . BankMovementStatus::getColor(
							$bm->getStatus()
						) . '">' . BankMovementStatus::getName($bm->getStatus()) . '</small>';
				}
			)
			->setTemplateEscaping(false)
			->setFitContent(true);

		$grid->addColumnText('variableSymbol', 'VS')
			->setRenderer(
				function (BankMovement $bm): string
				{
					if ($bm->getInvoice() === null) {
						return $bm->getVariableSymbol();
					}

					$link = $this->link(
						'Invoice:show', [
							'id' => $bm->getInvoice()->getId(),
						]
					);

					return '<a href="' . $link . '">' . $bm->getVariableSymbol() . '</a>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('customerBankAccount', 'Proti účet')
			->setRenderer(
				function (BankMovement $bm): string
				{
					if ($bm->getInvoice() && $bm->getInvoice()->getCompany()) {
						$link = $this->link('Company:detail', ['id' => $bm->getInvoice()->getCompany()->getId()]);
						$ret = '<a href="' . $link . '">' . $bm->getCustomerName() . '</a>';
					} else {
						$ret = $bm->getCustomerName();
					}

					return $ret . '<br><small>' . $bm->getCustomerBankAccount() . '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('bankAccount', 'Bankovní účet')
			->setRenderer(
				static function (BankMovement $bm): string
				{
					return $bm->getBankAccountName() . '<br><small>' . $bm->getBankAccount() . '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('price', 'Částka')
			->setRenderer(
				static function (BankMovement $bm): string
				{
					return Number::formatPrice($bm->getPrice(), $bm->getCurrency(), 2);
				}
			)
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(
				function (BankMovement $bm): string
				{
					$link = $this->link('detailBankMovement', ['id' => $bm->getId()]);

					return '<a href="' . $link . '" class="btn btn-xs btn-info"><i class="fas fa-search"></i>&nbsp; detail</a>';
				}
			)
			->setTemplateEscaping(false);

		$grid->setDefaultPerPage(20);

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');

		//VS
		$grid->addFilterText('variableSymbol', 'VS:');

		//Castka
		$grid->addFilterRange('price', 'Částka:');

		//Firma
		$grid->addFilterText('customerName', 'Firma:');

		//Stav
		$statusList = [
			'' => 'Vše',
			'not' => 'Nevyřešené',
			'ok' => 'Vyřešené',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(
				static function (QueryBuilder $qb, string $status): QueryBuilder
				{
					if ($status === 'not') {
						$qb->andWhere('bm.status != :status1')
							->setParameter('status1', BankMovement::STATUS_DONE);
						$qb->andWhere('bm.status != :status2')
							->setParameter('status2', BankMovement::STATUS_SUCCESS);
					} elseif ($status === 'ok') {
						$qb->andWhere('(bm.status = :status1 OR bm.status = :status2)')
							->setParameter('status1', BankMovement::STATUS_DONE)
							->setParameter('status2', BankMovement::STATUS_SUCCESS);
					}

					return $qb;
				}
			);

		$grid->setOuterFilterRendering();

		return $grid;
	}


	/**
	 * @throws CurrencyException
	 * @throws DataGridException
	 */
	public function createComponentInvoiceTable(string $name): MatiDataGrid
	{
		$currency = $this->currencyManager->getDefaultCurrency();

		$grid = new MatiDataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('invoice')
				->select('invoice, company, u1')
				->leftJoin('invoice.company', 'company')
				->join('invoice.createUser', 'u1')
				->leftJoin('invoice.depositingInvoices', 'deposit')
				->andWhere('invoice.deleted = FALSE')
				->andWhere('invoice.type IN (:types)')
				->setParameter('types', [
					Invoice::TYPE_REGULAR,
					Invoice::TYPE_PROFORMA,
				])
				->orderBy('invoice.number', 'DESC')
		);

		$grid->setRowCallback(
			static function (Invoice $invoice, Html $row): void
			{
				$status = $invoice->getStatus();
				if ($status === Invoice::STATUS_ACCEPTED) {
					$row->addClass('table-success');

					return;
				}
				if ($status === Invoice::STATUS_DENIED) {
					$row->addClass('table-danger');

					return;
				}
				if ($status === Invoice::STATUS_CREATED) {
					$row->addClass('table-warning');

					return;
				}
				if ($status === Invoice::STATUS_PAY_ALERT_THREE) {
					$row->addClass('table-danger');

					return;
				}
			}
		);

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(
				function (Invoice $invoice): string
				{
					$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);

					return '<a href="' . $link . '">' . $invoice->getNumber() . '</a>'
						. '<br>'
						. '<small class="' . $invoice->getColor() . '">'
						. htmlspecialchars($invoice->getLabel())
						. '</small>';;
				}
			)
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(
				function (Invoice $invoice): string
				{
					if ($invoice->getCompany() !== null) {
						$link = $this->link('Company:detail', ['id' => $invoice->getCompany()->getId()]);

						$ret = '<a href="' . $link . '">' . Strings::truncate($invoice->getCustomerName(), 60) . '</a>';
					} else {
						$ret = '<span class="text-blue">' . $invoice->getCustomerName() . '</span>';
					}

					return $ret
						. '<br>'
						. '<small>'
						. $invoice->getCustomerAddress() . ', '
						. $invoice->getCustomerCity() . ', '
						. $invoice->getCustomerPostalCode()
						. '</small>';

				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Vystaveno')
			->setRenderer(
				static function (Invoice $invoiceCore): string
				{
					return $invoiceCore->getDate()->format('d.m.Y') . '<br><small>' . $invoiceCore->getCreatedByUserId()
							->getName() . '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('taxDate', 'Daň. plnění')
			->setRenderer(
				function (Invoice $invoiceCore): string
				{
					if ($invoiceCore->isProforma()) {
						$invoice = $invoiceCore->getSubInvoice();
						if ($invoice !== null) {
							$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);
							$str = '<small><a href="' . $link . '" title="Faktura"><i class="fas fa-file-invoice"></i>&nbsp;' . $invoice->getNumber(
								) . '</a></small>';
						} else {
							$str = '&nbsp;';
						}

						return '<span class="text-info"><small>Záloha</small></span><br>' . $str;
					}

					$str = '<small>&nbsp;</small>';

					/** @var Invoice $fixInvoice */
					$fixInvoice = $invoiceCore->getFixInvoice();
					if ($fixInvoice !== null) {
						$link = $this->link('Invoice:show', ['id' => $fixInvoice->getId()]);
						$str = '<small><a href="' . $link . '" title="Dobropis" style="color: rgb(194, 0, 64);"><i class="fas fa-file-invoice"></i>&nbsp;' . $fixInvoice->getNumber(
							);
						if (
							$this->invoiceManager->get()->getAcceptSetting() !== null
							&& (
								$fixInvoice->getAcceptStatus1() !== Invoice::STATUS_ACCEPTED
								|| $fixInvoice->getAcceptStatus2() !== Invoice::STATUS_ACCEPTED
							)
						) {
							if ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_WAITING) {
								$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
							} elseif ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_DENIED) {
								$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
							} elseif ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_ACCEPTED) {
								$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
							}

							if ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_WAITING) {
								$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
							} elseif ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_DENIED) {
								$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
							} elseif ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_ACCEPTED) {
								$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
							}
						}
						$str .= '</a></small>';
					}

					return $invoiceCore->getTaxDate()->format('d.m.Y') . '<br>' . $str;
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('dueDate', 'Splatnost')
			->setRenderer(
				function (Invoice $invoiceCore): string
				{
					$ret = $invoiceCore->getDueDate()->format('d.m.Y');

					if ($invoiceCore->isPaid() && $invoiceCore->getPayDate() !== null) {
						$ret .= '<br><small class="text-success"><i class="fas fa-coins text-warning" title="Uhrazeno"></i>&nbsp;' . $invoiceCore->getPayDate(
							)->format('d.m.Y') . '</small>';

						if ($invoiceCore->isProforma()) {
							$payDocument = $invoiceCore->getParentInvoice();
							if ($payDocument !== null) {
								$link = $this->link('Invoice:show', ['id' => $payDocument->getId()]);
								$ret .= '&nbsp;<small><a href="' . $link . '" style="color: rgb(75, 0, 150);" title="Doklad k přijaté platbě"><i class="fas fa-file-invoice-dollar"></i></a></small>';
							}
						}
					} else {
						$ret .= '<br>';
						$diff = $invoiceCore->getPayDateDiff();
						if ($diff < -4) {
							$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' dní</small>';
						} elseif ($diff < -1) {
							$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' dny</small>';
						} elseif ($diff < 0) {
							$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' den</small>';
						} elseif ($diff === 0) {
							$ret .= '<small class="text-success">Dnes</small>';
						} elseif ($diff > 4) {
							$ret .= '<small class="text-danger">' . $diff . ' dní po splatnosti</small>';
						} elseif ($diff > 1) {
							$ret .= '<small class="text-danger">' . $diff . ' dny po splatnosti</small>';
						} else {
							$ret .= '<small class="text-danger">' . $diff . ' den po splatnosti</small>';
						}
					}

					return $ret;
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('price', 'Částka')
			->setRenderer(
				static function (Invoice $invoiceCore) use ($currency): string
				{
					$totalPrice = $invoiceCore->getTotalPrice();
					if ($invoiceCore->isRegular()) {
						$fixInvoice = $invoiceCore->getFixInvoice();
						if ($fixInvoice !== null) {
							$totalPrice += $fixInvoice->getTotalPrice();
						}
					}

					if ($totalPrice < 0) {
						return '<b class="text-danger">' . Number::formatPrice(
								$totalPrice, $invoiceCore->getCurrency(), 2
							) . '</b>'
							. '<br>'
							. '<small>'
							. Number::formatPrice($totalPrice * $invoiceCore->getRate(), $currency, 2)
							. '</small>';
					}

					return '<b>' . Number::formatPrice($totalPrice, $invoiceCore->getCurrency(), 2) . '</b>'
						. '<br>'
						. '<small>'
						. Number::formatPrice($totalPrice * $invoiceCore->getRate(), $currency, 2)
						. '</small>';
				}
			)
			->setAlign('right')
			->setFitContent()
			->setTemplateEscaping(false);

		if ($this->invoiceManager->get()->getAcceptSetting() !== null) {
			$grid->addColumnText('accept', 'Schválení')
				->setRenderer(
					function (Invoice $invoiceCore): string
					{
						if ($invoiceCore->isSubmitted() === false) {
							return '<span class="text-warning">Editace</span>';
						}

						$ret = '';
						$link = $this->link('Invoice:show', ['id' => $invoiceCore->getId()]);

						if ($invoiceCore->getAcceptStatus1() === 'denied') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
						} elseif ($invoiceCore->getAcceptStatus1() === 'waiting') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
						} elseif ($invoiceCore->getAcceptStatus1() === 'accepted') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
						}

						$ret .= '&nbsp;';

						if ($invoiceCore->getAcceptStatus2() === 'denied') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
						} elseif ($invoiceCore->getAcceptStatus2() === 'waiting') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
						} elseif ($invoiceCore->getAcceptStatus2() === 'accepted') {
							$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
						}

						return $ret;
					}
				)
				->setAlign('center')
				->setTemplateEscaping(false);
		}

		$grid->addAction('detail', 'Detail')
			->setRenderer(
				function (Invoice $invoiceCore)
				{
					$link = $this->link('Invoice:show', ['id' => $invoiceCore->getId()]);

					return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
				}
			);

		$grid->addAction('delete', 'Delete')
			->setRenderer(
				function (Invoice $invoiceCore)
				{
					if ($this->checkAccess('page__invoice__forceRemove') === false) {
						return '';
					}

					$link = $this->link('delete!', ['id' => $invoiceCore->getId()]);

					return '<btn-delete redirect="' . $link . '"></btn-delete>';
				}
			);

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');

		//Cislo faktury
		$grid->addFilterText('number', 'Číslo:');

		//Castka
		$grid->addFilterRange('totalPrice', 'Částka:');

		//Fakturoval
		$invoiceUserList = [
			'' => 'Vše',
		];

		$invoiceUsers = $this->entityManager->getRepository(BaseUser::class)
				->createQueryBuilder('u')
				->join(Invoice::class, 'invoice', Join::WITH, 'u.id = invoice.createUser')
				->groupBy('u.id')
				->orderBy('u.lastName', 'ASC')
				->addOrderBy('u.firstName', 'ASC')
				->getQuery()
				->getResult();

		foreach ($invoiceUsers as $user) {
			$invoiceUserList[$user->getId()] = $user->getName();
		}
		$grid->addFilterSelect('createUser', 'Fakturace:', $invoiceUserList);

		//Firma
		$grid->addFilterText('customerName', 'Firma:');

		//Stav
		$statusList = [
			'' => 'Vše',
			'paid' => 'Uhrazené',
			'unpaid' => 'Neuhrazené',
			'overDate' => 'Po splatnosti',
			'edit' => 'Rozpracované',
			'accepted' => 'Schválené',
			'notAccepted' => 'Neschválené',
			'denied' => 'Zamítnuté',
			'proforma' => 'Zálohová faktura',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(
				static function (QueryBuilder $qb, string $status): QueryBuilder
				{
					if ($status === 'unpaid') {
						$qb->andWhere('invoice.acceptStatus1 = :status1')
							->setParameter('status1', Invoice::STATUS_ACCEPTED)
							->andWhere('invoice.acceptStatus2 = :status2')
							->setParameter('status2', Invoice::STATUS_ACCEPTED);
						$qb->andWhere('invoice.payDate IS NULL');
					} elseif ($status === 'paid') {
						$qb->andWhere('invoice.payDate IS NOT NULL');
					} elseif ($status === 'overDate') {
						$qb->andWhere('invoice.acceptStatus1 = :status1')
							->setParameter('status1', Invoice::STATUS_ACCEPTED)
							->andWhere('invoice.acceptStatus2 = :status2')
							->setParameter('status2', Invoice::STATUS_ACCEPTED);
						$qb->andWhere('invoice.payDate IS NULL');
						$qb->andWhere('invoice.dueDate < :now')
							->setParameter('now', (new \DateTime)->format('Y-m-d'));
					} elseif ($status === 'edit') {
						$qb->andWhere('invoice.submitted = :f')
							->setParameter('f', false);
					} elseif ($status === 'accepted') {
						$qb->andWhere('invoice.acceptStatus1 = :status1')
							->setParameter('status1', Invoice::STATUS_ACCEPTED)
							->andWhere('invoice.acceptStatus2 = :status2')
							->setParameter('status2', Invoice::STATUS_ACCEPTED);
					} elseif ($status === 'notAccepted') {
						$qb->andWhere('(invoice.acceptStatus1 = :status OR invoice.acceptStatus2 = :status)')
							->setParameter('status', Invoice::STATUS_WAITING);
					} elseif ($status === 'denied') {
						$qb->andWhere('(invoice.acceptStatus1 = :status OR invoice.acceptStatus2 = :status)')
							->setParameter('status', Invoice::STATUS_DENIED);
					} elseif ($status === 'proforma') {
						$qb->andWhere('invoice.type = :typeProforma')
							->setParameter('typeProforma', Invoice::TYPE_PROFORMA);
					}

					return $qb;
				}
			);

		$grid->setOuterFilterRendering();

		//Exporty
		if ($this->checkAccess('page__invoice__export')) {
			$grid->addGroupAction('Tisk')
				->onSelect[] = [$this, 'printInvoices'];

			$grid->addGroupAction('Přehled (PDF)')
				->onSelect[] = [$this, 'invoiceSummary'];
		}

		return $grid;
	}


	/**
	 * @param array $ids
	 * @throws AbortException
	 */
	public function printInvoices(array $ids): void
	{
		$session = $this->getSession('exportInvoices');
		$session->exportedIds = $ids;

		$this->redirect('exportInvoices!');
	}


	/**
	 * @throws AbortException
	 * @throws CurrencyException
	 * @throws MpdfException
	 */
	public function handleExportInvoices(): void
	{
		$session = $this->getSession('exportInvoices');
		$ids = $session->exportedIds;
		$session->exportedIds = [];

		if (count($ids) > 0) {
			$invoices = $this->entityManager->getRepository(Invoice::class)
					->createQueryBuilder('i')
					->where('i.id IN (:ids)')
					->setParameter('ids', $ids)
					->orderBy('i.number', 'ASC')
					->getQuery()
					->getResult();

			$this->exportManager->get()->exportInvoicesToPDF($invoices);
		}

		$this->flashMessage('Není co exportovat.', 'error');
		$this->redirect('default');
	}


	/**
	 * @param array $ids
	 * @throws AbortException
	 */
	public function invoiceSummary(array $ids): void
	{
		$session = $this->getSession('summaryInvoices');
		$session->exportedIds = $ids;

		$this->redirect('exportSummary!');
	}


	/**
	 * @throws AbortException
	 * @throws CurrencyException
	 * @throws MpdfException
	 */
	public function handleExportSummary(): void
	{
		$session = $this->getSession('summaryInvoices');
		$ids = $session->exportedIds;
		$session->exportedIds = [];

		if (count($ids) > 0) {
			$invoices = $this->entityManager->getRepository(Invoice::class)
					->createQueryBuilder('i')
					->where('i.id IN (:ids)')
					->setParameter('ids', $ids)
					->orderBy('i.number', 'ASC')
					->getQuery()
					->getResult();

			$this->exportManager->get()->exportInvoiceSummaryToPDF($invoices);
		}

		$this->flashMessage('Není co exportovat.', 'error');
		$this->redirect('default');
	}


	public function createComponentInvoiceDenyFormA(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('description', 'Důvod zamítnutí');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			if ($this->editedInvoice !== null) {
				/** @var BaseUser $user */
				$user = $this->getUser()->getIdentity()->getUser();

				$this->editedInvoice->setAcceptStatus1(Invoice::STATUS_DENIED);
				$this->editedInvoice->setAcceptStatus1Description($values->description ?? '');
				$this->editedInvoice->setAcceptStatusFirstUserId($user);

				$this->editedInvoice->setAcceptStatus2(Invoice::STATUS_DENIED);

				$this->editedInvoice->setStatus(Invoice::STATUS_DENIED);

				$history = new InvoiceHistory(
					$this->editedInvoice, '<b class="text-danger">Doklad zamítnut</b><br>' . str_replace(
						"\n", '<br>', $values->description ?? ''
					)
				);
				$history->setUserId($user);

				$this->entityManager->persist($history);
				$this->editedInvoice->addHistory($history);
				$this->entityManager->flush();

				$this->flashMessage('Doklad byl zamítnut.', 'info');
			}

			if ($this->returnButton === 1) {
				$this->redirect('Homepage:default');
			} else {
				$this->redirect('Invoice:default');
			}
		};

		return $form;
	}


	public function createComponentInvoiceDenyFormB(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('description', 'Důvod zamítnutí');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			if ($this->editedInvoice !== null) {
				/** @var BaseUser $user */
				$user = $this->getUser()->getIdentity()->getUser();

				$this->editedInvoice->setAcceptStatus2(Invoice::STATUS_DENIED);
				$this->editedInvoice->setAcceptStatus2Description($values->description ?? '');
				$this->editedInvoice->setAcceptStatusSecondUserId($user);

				$this->editedInvoice->setStatus(Invoice::STATUS_DENIED);

				$history = new InvoiceHistory(
					$this->editedInvoice, '<b class="text-danger">Doklad zamítnut</b><br>' . str_replace(
						"\n", '<br>', $values->description ?? ''
					)
				);
				$history->setUserId($user);

				$this->entityManager->persist($history);
				$this->editedInvoice->addHistory($history);
				$this->entityManager->flush();

				$this->flashMessage('Doklad byl zamítnut.', 'info');
			}

			if ($this->returnButton === 1) {
				$this->redirect('Homepage:default');
			} else {
				$this->redirect('Invoice:default');
			}
		};

		return $form;
	}


	/**
	 * @throws AbortException
	 */
	public function handleResolveBankMovement(string $id): void
	{
		try {
			$bm = $this->bankMovementManager->get()->getById($id);
			$bm->setStatus(BankMovement::STATUS_DONE);
			$this->entityManager->flush();
			$this->flashMessage(
				'Stav bankovního pohybu byl změněn na ' . BankMovementStatus::getName(BankMovement::STATUS_DONE) . '.',
				'success'
			);
			$this->redirect(
				'Invoice:detailBankMovement', [
					'id' => $id,
				]
			);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný bankovní pohyb neexistuje.', 'error');
			$this->redirect('Invoice:bankMovements');
		} catch (EntityManagerException $e) {
			$this->flashMessage('Chyba při ukládání do databáze.', 'error');
			$this->redirect(
				'Invoice:detailBankMovement', [
					'id' => $id,
				]
			);
		}
	}


	public function createComponentEmailForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addEmail('email', 'Email');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			$email = $values->email === '' ? null : $values->email;

			if ($email === null) {
				$status = $this->invoiceManager->get()->sendEmailToCompany($this->editedInvoice);

				$show = $status['message'] ?? false;

				if ($show) {
					$this->flashMessage($status['message'], $status['type']);
				}
			} elseif ($this->invoiceManager->get()->sendEmail($this->editedInvoice, [$email])) {
				$this->flashMessage('Doklad byl odeslán.', 'info');
			} else {
				$this->flashMessage('Doklad se nepodařilo odeslat.', 'warning');
			}

			$this->redirect('Invoice:show', ['id' => $this->editedInvoice->getId(), 'ret' => $this->returnButton]);
		};

		return $form;
	}


	/**
	 * @return array
	 * @throws CurrencyException
	 */
	public function getStatistics(): array
	{
		$data['monthName'] = Date::getCzechMonthName(new \DateTime);

		//Mesic
		$dateStart = new \DateTime(date('Y-m-01 00:00:00'));
		$dateStop = $dateStart->modifyClone('+1 month');

		$invoices = $this->invoiceManager->get()->getInvoicesBetweenDates($dateStart, $dateStop);

		$price = 0.0;
		$tax = 0.0;
		foreach ($invoices as $invoice) {
			if ($invoice->isProforma()) {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
			} elseif ($invoice->isRegular()) {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
				$tax += $invoice->getTotalTaxCZK();

				$fixInvoice = $invoice->getFixInvoice();
				if ($fixInvoice !== null) {
					$price += $fixInvoice->getTotalPrice() * $fixInvoice->getRate();
					$tax += $fixInvoice->getTotalTaxCZK();
				}
			} else {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
				$tax += $invoice->getTotalTaxCZK();
			}
		}

		$data['pricePerMonth'] = $price;
		$data['taxPerMonth'] = $tax;

		// year
		$dateStart = new \DateTime(date('Y') . '-01-01 00:00:00');
		$dateStop = $dateStart->modifyClone('+1 year');

		$invoices = $this->invoiceManager->get()->getInvoicesBetweenDates($dateStart, $dateStop);

		$price = 0.0;
		$tax = 0.0;
		foreach ($invoices as $invoice) {
			if ($invoice->isProforma()) {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
			} elseif ($invoice->isRegular()) {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
				$tax += $invoice->getTotalTaxCZK();

				$fixInvoice = $invoice->getFixInvoice();
				if ($fixInvoice !== null) {
					$price += $fixInvoice->getTotalPrice() * $fixInvoice->getRate();
					$tax += $fixInvoice->getTotalTaxCZK();
				}
			} else {
				$price += $invoice->getTotalPrice() * $invoice->getRate();
				$tax += $invoice->getTotalTaxCZK();
			}
		}

		$data['pricePerYear'] = $price;
		$data['taxPerYear'] = $tax;

		//Nezaplaceno
		$invoices = $this->invoiceManager->get()->getInvoicesUnpaid();

		$unpaidPrice = 0.0;
		$unpaidCount = count($invoices);

		$overDatePrice = 0.0;
		$overDateCount = 0;

		$dateNow = new \DateTime;

		foreach ($invoices as $invoice) {
			$invoiceTotalPrice = $invoice->getTotalPrice() * $invoice->getRate();
			if ($invoice->isRegular()) {
				$fixInvoice = $invoice->getFixInvoice();
				if ($fixInvoice !== null) {
					$invoiceTotalPrice += $fixInvoice->getTotalPrice() * $fixInvoice->getRate();
				}
			}

			$unpaidPrice += $invoiceTotalPrice;

			if ($invoice->getDueDate() < $dateNow) {
				$overDatePrice += $invoiceTotalPrice;
				$overDateCount++;
			}
		}

		$data['unpaidPrice'] = $unpaidPrice;
		$data['unpaidCount'] = $unpaidCount;

		$data['overDatePrice'] = $overDatePrice;
		$data['overDateCount'] = $overDateCount;

		$data['currency'] = $this->currencyManager->getDefaultCurrency();

		return $data;
	}


	public function createComponentCommentForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('description', 'Text');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			if ($values->description !== '' && $values->description !== null) {
				$comment = new InvoiceComment($this->editedInvoice, $values->description);

				/** @var BaseUser $user */
				$user = $this->user->getIdentity()->getUser();
				if ($user !== null) {
					$comment->setUserId($user);
				}

				$this->entityManager->persist($comment);
				$this->editedInvoice->addComments($comment);
				$this->entityManager->flush();
			}

			$form->reset();

			$this->redrawControl('invoiceComments');
		};

		return $form;
	}


	public function insertLinks(?string $txt): ?string
	{
		if ($txt === null) {
			return null;
		}

		//TODO implement link parsing in future!

		return $txt;
	}


	public function createComponentAddContactForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('email', 'E-mail');
		$form->addSubmit('submit', 'Přidat');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			if (Validators::isEmail($values->email) && $this->editedInvoice->isReady() === false) {
				$this->editedInvoice->addEmail(trim($values->email));
				$user = $this->getUser()->getIdentity()->getUser();
				if (!$user instanceof BaseUser) {
					$user = null;
				}

				$history = new InvoiceHistory($this->editedInvoice, 'Přidán email: ' . $values->email);
				$history->setUserId($user);

				$this->entityManager->persist($history);
				$this->editedInvoice->addHistory($history);

				$this->entityManager->flush();

				$this->template->contacts = $this->invoiceManager->get()->getInvoiceEmails($this->editedInvoice);
			}

			$form->reset();
			$this->redrawControl('contact-list');
		};

		return $form;
	}


	/**
	 * @throws \Exception
	 */
	public function handleDeleteContact(string $contact): void
	{
		$list = [];
		foreach ($this->editedInvoice->getEmailList() as $email) {
			if ($email !== $contact) {
				$list[] = $email;
			}
		}

		$user = $this->getUser()->getIdentity()->getUser();
		if (!$user instanceof BaseUser) {
			$user = null;
		}

		$history = new InvoiceHistory($this->editedInvoice, 'Odebrán email: ' . $contact);
		$history->setUserId($user);

		$this->entityManager->persist($history);
		$this->editedInvoice->addHistory($history);
		$this->editedInvoice->setEmails(implode(';', $list));
		$this->entityManager->flush();

		$this->template->contacts = $this->invoiceManager->get()->getInvoiceEmails($this->editedInvoice);

		$this->redrawControl('contact-list');
	}


	public function isCompanyContact(string $contact): bool
	{
		if ($this->editedInvoice !== null && $this->editedInvoice->getCompany()) {
			foreach ($this->editedInvoice->getCompany()->getContacts() as $companyContact) {
				if (trim($companyContact->getEmail() ?? '') === trim($contact)) {
					return true;
				}
			}

			return false;
		}

		return true;
	}


	public function isSettingAccept(): bool
	{
		return $this->invoiceManager->get()->getAcceptSetting() !== null;
	}


	public function createComponentExportInvoicesForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addDate('dateStart', 'dateStart');
		$form->addDate('dateStop', 'dateStop');

		$form->addSubmit('submit', 'Export');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			/** @var \DateTime $dateStart */
			$dateStart = $values->dateStart;
			if (!$dateStart instanceof \DateTime) {
				$dateStart = new \DateTime;
			}

			/** @var \DateTime $dateStop */
			$dateStop = $values->dateStop;
			if (!$dateStop instanceof \DateTime) {
				$dateStop = new \DateTime;
			}

			$invoices = $this->invoiceManager->get()->getInvoicesBetweenDates($dateStart, $dateStop);

			$this->exportManager->get()->exportInvoicesToPDF($invoices);

			die;
		};

		return $form;
	}


	public function createComponentExportInvoiceListForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addDate('dateStart', 'dateStart');
		$form->addDate('dateStop', 'dateStop');

		$form->addSubmit('submit', 'Export');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			/** @var \DateTime $dateStart */
			$dateStart = $values->dateStart;
			if (!$dateStart instanceof \DateTime) {
				$dateStart = new \DateTime;
			}

			/** @var \DateTime $dateStop */
			$dateStop = $values->dateStop;
			if (!$dateStop instanceof \DateTime) {
				$dateStop = new \DateTime;
			}

			$invoices = $this->invoiceManager->get()->getInvoicesBetweenDates($dateStart, $dateStop);
			$this->exportManager->get()->exportInvoiceSummaryToPDF($invoices);
			die;
		};

		return $form;
	}
}
