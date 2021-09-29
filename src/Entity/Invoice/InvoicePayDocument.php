<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class InvoicePayDocument extends InvoiceCore
{
	/**
	 * @var bool
	 */
	protected bool $disableStatistics = true;

	/**
	 * @var InvoiceCore
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", mappedBy="payDocument")
	 */
	private InvoiceCore $invoice;


	/**
	 * @return InvoiceCore
	 */
	public function getInvoice(): InvoiceCore
	{
		return $this->invoice;
	}


	/**
	 * @param InvoiceCore $invoice
	 */
	public function setInvoice(InvoiceCore $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function getTotalPriceDiff(): float
	{
		if ($this->getCurrency()->getCode() !== 'CZK') {
			return 0.0;
		}

		$diff = round($this->getTotalPrice() - ($this->getItemTotalPrice() + $this->getTotalTax()), 2);

		foreach ($this->getDepositInvoices() as $invoice) {
			$diff += $invoice->getTotalPrice();
		}

		return $diff;
	}


	public function isPayDocument(): bool
	{
		return true;
	}

}
