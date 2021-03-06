<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Nette\Security\User;
use Nette\Utils\Strings;

class InvoiceDashboardBlockControl extends DashboardBlockControl
{
	protected string $blockName = 'invoice';

	/**
	 * @var array|null
	 */
	protected ?array $acceptRights;

	protected User $user;

	protected EntityManager $entityManager;

	protected CurrencyManagerAccessor $currencyManager;


	public function __construct(
		?array $acceptRights,
		User $user,
		EntityManager $entityManager,
		CurrencyManagerAccessor $currencyManager
	) {
		$this->acceptRights = $acceptRights;
		$this->user = $user;
		$this->entityManager = $entityManager;
		$this->currencyManager = $currencyManager;
	}


	public function getUser(): User
	{
		return $this->user;
	}


	public function render(): void
	{
		/**
		 * @var BaseAdminPresenter $presenter
		 * @phpstan-ignore-next-line
		 */
		$presenter = $this->getPresenter();

		$show = false;
		if ($presenter !== null) {
			/**
			 * @phpstan-ignore-next-line
			 */
			$show = $presenter->checkAccess('page__dashboard_invoice');
		}

		$template = $this->template;
		$template->setFile(__DIR__ . '/default.latte');
		$template->show = $show;
		$template->render();
	}


	public function createComponentInvoiceTable(string $name): MatiDataGrid
	{
		$currency = $this->currencyManager->get()->getDefaultCurrency();

		$grid = new MatiDataGrid($this, $name);

		$query = $this->entityManager->getRepository(Invoice::class)
			->createQueryBuilder('invoice')
			->where('invoice.deleted = :f')
			->setParameter('f', 0)
			->setMaxResults(5);

		if ($this->acceptRights === null) {
			$query->andWhere('invoice.submitted = :f')
				->setParameter('f', 0)
				->addOrderBy('invoice.number', 'DESC');
		} elseif ($this->getPresenter()->checkAccess('page__invoice__accept-B')) {
			$query->orderBy('invoice.number', 'ASC');
			$query->andWhere('invoice.acceptStatus1 = :status1')
				->setParameter('status1', Invoice::STATUS_ACCEPTED);
			$query->andWhere('invoice.acceptStatus2 = :status2')
				->setParameter('status2', Invoice::STATUS_WAITING);
		} elseif ($this->getPresenter()->checkAccess('page__invoice__accept-A')) {
			$query->orderBy('invoice.number', 'ASC');
			$query->andWhere('invoice.submitted = :submitted')
				->setParameter('submitted', true)
				->andWhere('invoice.acceptStatus1 = :status')
				->setParameter('status', Invoice::STATUS_WAITING);
		} else {
			$query->orderBy('invoice.acceptStatus1', 'DESC');
			$query->addOrderBy('invoice.acceptStatus2', 'DESC');
			$query->addOrderBy('invoice.number', 'DESC');
			$query->andWhere(
				'(invoice.submitted = :f OR invoice.acceptStatus1 = :status1 OR invoice.acceptStatus2 = :status2)'
			)
				->setParameter('f', 0)
				->setParameter('status1', Invoice::STATUS_DENIED)
				->setParameter('status2', Invoice::STATUS_DENIED);
		}

		$grid->setDataSource($query);

		$grid->setPagination(false);

		$grid->addColumnText('number', '????slo')
			->setRenderer(
				function (Invoice $invoice): string
				{
					$link = $this->getPresenter()->link('Invoice:show', ['id' => $invoice->getId(), 'ret' => 1]);

					return '<a href="' . $link . '">' . $invoice->getNumber() . '</a>'
						. '<br>'
						. '<small class="' . $invoice->getColor() . '">'
						. htmlspecialchars($invoice->getLabel())
						. '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(
				function (Invoice $invoice): string
				{
					if ($invoice->getCompany() !== null) {
						$link = $this->getPresenter()->link(
							'Company:detail', ['id' => $invoice->getCompany()->getId()]
						);

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

		$grid->addColumnText('taxDate', 'Da??. pln??n??')
			->setRenderer(
				static function (Invoice $invoiceCore): string
				{
					if ($invoiceCore->isProforma()) {
						return '<span class="text-info">Z??loha</span><br>&nbsp;';
					}

					return $invoiceCore->getTaxDate()->format('d.m.Y') . '<br><small>&nbsp;</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('dueDate', 'Splatnost')
			->setRenderer(
				static function (Invoice $invoiceCore): string
				{
					$ret = $invoiceCore->getDueDate()->format('d.m.Y');

					if ($invoiceCore->isPaid() && $invoiceCore->getPayDate() !== null) {
						$ret .= '<br><small class="text-success">' . $invoiceCore->getPayDate()->format(
								'd.m.Y'
							) . '</small>';
					} else {
						$ret .= '<br>';
						$diff = $invoiceCore->getPayDateDiff();
						if ($diff < -4) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' dn??</small>';
						} elseif ($diff < -1) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' dny</small>';
						} elseif ($diff < 0) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' den</small>';
						} elseif ($diff === 0) {
							$ret .= '<small class="text-success">Dnes</small>';
						} elseif ($diff > 4) {
							$ret .= '<small class="text-danger">' . $diff . ' dn?? po splatnosti</small>';
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

		$grid->addColumnText('price', '????stka')
			->setRenderer(
				static function (Invoice $invoiceCore) use ($currency): string
				{
					return '<b>' . Number::formatPrice(
							$invoiceCore->getTotalPrice(), $invoiceCore->getCurrency(), 2
						) . '</b>'
						. '<br>'
						. '<small>'
						. Number::formatPrice($invoiceCore->getTotalPrice() * $invoiceCore->getRate(), $currency, 2)
						. '</small>';
				}
			)
			->setAlign('right')
			->setTemplateEscaping(false);

		if ($this->acceptRights !== null) {
			$grid->addColumnText('accept', 'Schv??len??')
				->setRenderer(
					function (Invoice $invoiceCore): string
					{
						if ($invoiceCore->isSubmitted() === false) {
							return '<span class="text-warning">Editace</span>';
						}

						$ret = '';
						$link = $this->getPresenter()->link('Invoice:show', ['id' => $invoiceCore->getId()]);

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
					$link = $this->getPresenter()->link('Invoice:show', ['id' => $invoiceCore->getId(), 'ret' => 1]);

					return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
				}
			);

		return $grid;
	}
}
