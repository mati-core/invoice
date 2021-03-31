<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use MatiCore\Cms\Dashboard\DashboardBlockControl;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\CurrencyManagerAccessor;
use MatiCore\DataGrid\MatiDataGrid;
use Nette\Security\User;
use Nette\Utils\Strings;
use Ublaboo\DataGrid\Exception\DataGridException;

/**
 * Class InvoiceDashboardBlock
 * @package App\Model
 */
class InvoiceDashboardBlockControl extends DashboardBlockControl
{

	/**
	 * @var string
	 */
	protected string $blockName = 'invoice';

	/**
	 * @var User
	 */
	protected User $user;

	/**
	 * @var EntityManager
	 */
	protected EntityManager $entityManager;

	/**
	 * @var CurrencyManagerAccessor
	 */
	protected CurrencyManagerAccessor $currencyManager;

	/**
	 * InvoiceDashboardBlockControl constructor.
	 * @param User $user
	 * @param EntityManager $entityManager
	 * @param CurrencyManagerAccessor $currencyManager
	 */
	public function __construct(User $user, EntityManager $entityManager, CurrencyManagerAccessor $currencyManager)
	{
		$this->user = $user;
		$this->entityManager = $entityManager;
		$this->currencyManager = $currencyManager;
	}

	/**
	 * @return User
	 */
	public function getUser(): User
	{
		return $this->user;
	}

	public function render(): void
	{
		$presenter = $this->getPresenter();

		$show = false;
		if ($presenter !== null) {
			$show = $presenter->checkAccess('page__dashboard_invoice');
		}

		$template = $this->template;
		$template->setFile(__DIR__ . '/default.latte');
		$template->show = $show;
		$template->render();
	}

	/**
	 * @param string $name
	 * @return MatiDataGrid
	 * @throws DataGridException
	 * @throws CurrencyException
	 */
	public function createComponentInvoiceTable(string $name): MatiDataGrid
	{
		$currency = $this->currencyManager->get()->getDefaultCurrency();

		$grid = new MatiDataGrid($this, $name);

		$query = $this->entityManager->getRepository(InvoiceCore::class)
			->createQueryBuilder('invoice')
			->select('invoice')
			->where('invoice.deleted = :f')
			->setParameter('f', 0)
			->setMaxResults(5);

		if ($this->getPresenter()->checkAccess('page__invoice__accept-B')) {
			$query->orderBy('invoice.number', 'ASC');
			$query->andWhere('invoice.acceptStatus1 = :status1')
				->setParameter('status1', InvoiceStatus::ACCEPTED);
			$query->andWhere('invoice.acceptStatus2 = :status2')
				->setParameter('status2', InvoiceStatus::WAITING);
		} elseif ($this->getPresenter()->checkAccess('page__invoice__accept-A')) {
			$query->orderBy('invoice.number', 'ASC');
			$query->andWhere('invoice.submitted = :submitted')
				->setParameter('submitted', true)
				->andWhere('invoice.acceptStatus1 = :status')
				->setParameter('status', InvoiceStatus::WAITING);
		} else {
			$query->orderBy('invoice.acceptStatus1', 'DESC');
			$query->addOrderBy('invoice.acceptStatus2', 'DESC');
			$query->addOrderBy('invoice.number', 'DESC');
			$query->andWhere('(invoice.submitted = :f OR invoice.acceptStatus1 = :status1 OR invoice.acceptStatus2 = :status2)')
				->setParameter('f', 0)
				->setParameter('status1', InvoiceStatus::DENIED)
				->setParameter('status2', InvoiceStatus::DENIED);
		}

		$grid->setDataSource($query);

		$grid->setPagination(false);

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(function (InvoiceCore $invoice): string {
				$link = $this->getPresenter()->link('Invoice:show', ['id' => $invoice->getId(), 'ret' => 1]);

				return '<a href="' . $link . '">' . $invoice->getNumber() . '</a>'
					. '<br>'
					. '<small class="'
					. InvoiceStatus::getColorByStatus($invoice->getStatus())
					. '">'
					. InvoiceStatus::getNameByStatus($invoice->getStatus())
					. '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(function (InvoiceCore $invoice): string {
				if ($invoice->getCompany() !== null) {
					$link = $this->getPresenter()->link('Company:detail', ['id' => $invoice->getCompany()->getId()]);

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

			})
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Vystaveno')
			->setRenderer(static function (InvoiceCore $invoiceCore): string {
				return $invoiceCore->getDate()->format('d.m.Y') . '<br><small>' . $invoiceCore->getCreateUser()->getName() . '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('taxDate', 'Daň. plnění')
			->setRenderer(static function (InvoiceCore $invoiceCore): string {
				if ($invoiceCore->isProforma()) {
					return '<span class="text-info">Záloha</span><br>&nbsp;';
				}

				return $invoiceCore->getTaxDate()->format('d.m.Y') . '<br><small>&nbsp;</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('dueDate', 'Splatnost')
			->setRenderer(static function (InvoiceCore $invoiceCore): string {
				$ret = $invoiceCore->getDueDate()->format('d.m.Y');

				if ($invoiceCore->isPaid() && $invoiceCore->getPayDate() !== null) {
					$ret .= '<br><small class="text-success">' . $invoiceCore->getPayDate()->format('d.m.Y') . '</small>';
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
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('price', 'Částka')
			->setRenderer(static function (InvoiceCore $invoiceCore) use ($currency): string {
				return '<b>' . Number::formatPrice($invoiceCore->getTotalPrice(), $invoiceCore->getCurrency(), 2) . '</b>'
					. '<br>'
					. '<small>'
					. Number::formatPrice($invoiceCore->getTotalPrice() * $invoiceCore->getRate(), $currency, 2)
					. '</small>';
			})
			->setAlign('right')
			->setTemplateEscaping(false);

		$grid->addColumnText('accept', 'Schválení')
			->setRenderer(function (InvoiceCore $invoiceCore): string {
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
			})
			->setAlign('center')
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(function (InvoiceCore $invoiceCore) {
				$link = $this->getPresenter()->link('Invoice:show', ['id' => $invoiceCore->getId(), 'ret' => 1]);

				return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
			});

		return $grid;
	}
}