<?php

declare(strict_types=1);

namespace MatiCore\Company;


use MatiCore\Currency\Number;
use MatiCore\Invoice\InvoiceManagerAccessor;
use MatiCore\Invoice\InvoicePayDocument;
use Nette\Application\UI\Control;
use Nette\Utils\DateTime;

/**
 * Class CompanyInvoiceStatisticsControl
 * @package MatiCore\Company
 */
class CompanyInvoiceStatisticsControl extends Control
{

	/**
	 * @var InvoiceManagerAccessor
	 */
	private InvoiceManagerAccessor $invoiceManager;

	/**
	 * CompanyInvoiceStatisticsControl constructor.
	 * @param InvoiceManagerAccessor $invoiceManager
	 */
	public function __construct(InvoiceManagerAccessor $invoiceManager)
	{
		$this->invoiceManager = $invoiceManager;
	}

	/**
	 * @param Company $company
	 */
	public function render(Company $company): void
	{
		$invoices = $this->invoiceManager->get()->getInvoicesByCompany($company);

		$invoicesPrice = 0.0;
		$invoicesCount = 0;
		$invoicesPaidPrice = 0.0;
		$invoicesPaidCount = 0;
		$invoicesPaidPercent = 100;
		$invoicesActivePrice = 0.0;
		$invoicesActiveCount = 0;
		$invoicesActivePercent = 100;
		$invoicesOverduePrice = 0.0;
		$invoicesOverdueCount = 0;
		$invoicesOverduePercent = 100;

		$now = DateTime::from('NOW');

		foreach ($invoices as $invoice) {
			if (!$invoice instanceof InvoicePayDocument) {
				$invoicesPrice += $invoice->getTotalPrice() * $invoice->getRate();
				$invoicesCount++;

				if ($invoice->isPaid()) {
					$invoicesPaidPrice += $invoice->getTotalPrice() * $invoice->getRate();
					$invoicesPaidCount++;
				} else {
					$invoicesActivePrice += $invoice->getTotalPrice() * $invoice->getRate();
					$invoicesActiveCount++;

					if ($now > $invoice->getDueDate()) {
						$invoicesOverduePrice += $invoice->getTotalPrice() * $invoice->getRate();
						$invoicesOverdueCount++;
					}
				}
			}
		}

		if ($invoicesCount > 0) {
			$invoicesPaidPercent = (100 / $invoicesCount) * $invoicesPaidCount;
			$invoicesActivePercent = (100 / $invoicesCount) * $invoicesActiveCount;
			$invoicesOverduePercent = (100 / $invoicesCount) * $invoicesOverdueCount;
		}

		$this->template->invoiceData = [
			'invoicesPrice' => Number::formatPrice($invoicesPrice, $company->getCurrency()),
			'invoicesCount' => $invoicesCount,
			'invoicesPaidPrice' => Number::formatPrice($invoicesPaidPrice, $company->getCurrency()),
			'invoicesPaidCount' => $invoicesPaidCount,
			'invoicesPaidPercent' => round($invoicesPaidPercent),
			'invoicesActivePrice' => Number::formatPrice($invoicesActivePrice, $company->getCurrency()),
			'invoicesActiveCount' => $invoicesActiveCount,
			'invoicesActivePercent' => round($invoicesActivePercent),
			'invoicesOverduePrice' => Number::formatPrice($invoicesOverduePrice, $company->getCurrency()),
			'invoicesOverdueCount' => $invoicesOverdueCount,
			'invoicesOverduePercent' => round($invoicesOverduePercent),
		];

		$this->template->setFile(__DIR__ . '/default.latte');
		$this->template->render();
	}

}