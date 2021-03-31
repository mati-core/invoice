<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class InvoicePayDocument
 * @package MatiCore\Invoice
 * @ORM\Entity()
 */
class InvoicePayDocument extends InvoiceCore
{

	/**
	 * @var InvoiceCore
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", mappedBy="payDocument")
	 */
	private InvoiceCore $invoice;

	/**
	 * @var bool
	 */
	protected bool $disableStatistics = true;

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

	/**
	 * @return float
	 */
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

	/**
	 * @return bool
	 */
	public function isPayDocument(): bool
	{
		return true;
	}

}