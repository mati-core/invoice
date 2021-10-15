<?php

declare(strict_types=1);

namespace MatiCore\Company;


use MatiCore\Invoice\InvoiceManagerAccessor;
use Nette\Application\UI\Control;

class CompanyInvoiceStatisticsControl extends Control
{
	private InvoiceManagerAccessor $invoiceManager;


	public function __construct(InvoiceManagerAccessor $invoiceManager)
	{
		$this->invoiceManager = $invoiceManager;
	}


	public function getData(Company $company): array
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

		$now = new \DateTimeImmutable();
		foreach ($invoices as $invoice) {
			if (!$invoice->isPayDocument()) {
				$invoicesPrice += $invoice->getTotalPrice();
				$invoicesCount++;
				if ($invoice->isPaid()) {
					$invoicesPaidPrice += $invoice->getTotalPrice();
					$invoicesPaidCount++;
				} else {
					$invoicesActivePrice += $invoice->getTotalPrice();
					$invoicesActiveCount++;
					if ($now > $invoice->getDueDate()) {
						$invoicesOverduePrice += $invoice->getTotalPrice();
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

		return [
			'invoicesPrice' => $invoicesPrice,
			'invoicesCount' => $invoicesCount,
			'invoicesPaidPrice' => $invoicesPaidPrice,
			'invoicesPaidCount' => $invoicesPaidCount,
			'invoicesPaidPercent' => round($invoicesPaidPercent),
			'invoicesActivePrice' => $invoicesActivePrice,
			'invoicesActiveCount' => $invoicesActiveCount,
			'invoicesActivePercent' => round($invoicesActivePercent),
			'invoicesOverduePrice' => $invoicesOverduePrice,
			'invoicesOverdueCount' => $invoicesOverdueCount,
			'invoicesOverduePercent' => round($invoicesOverduePercent),
		];
	}
}
