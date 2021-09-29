<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class FixInvoice extends InvoiceCore
{
	/**
	 * @var Invoice|null
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\Invoice", mappedBy="fixInvoice")
	 * @ORM\JoinColumn(name="fixed_invoice_id", referencedColumnName="id", nullable=true)
	 */
	private Invoice|null $invoice;


	/**
	 * @return Invoice|null
	 */
	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}


	/**
	 * @param Invoice|null $invoice
	 */
	public function setInvoice(?Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function isFix(): bool
	{
		return true;
	}


	public function getItemTotalPrice(): float
	{
		$totalPrice = parent::getItemTotalPrice();

		foreach ($this->getDepositInvoices() as $depositInvoice) {
			$totalPrice += $depositInvoice->getItemTotalPrice();
		}

		return $totalPrice;
	}


	public function getTotalPriceWithoutTaxCZK(): float
	{
		$totalPrice = parent::getItemTotalPrice() * $this->getRate();

		foreach ($this->getDepositInvoices() as $depositInvoice) {
			$totalPrice -= $depositInvoice->getItemTotalPrice() * $depositInvoice->getRate();
		}

		return $totalPrice;
	}


	public function getTotalTaxCZK(): float
	{
		$tax = 0.0;

		foreach ($this->getTaxList() as $invoiceTax) {
			$tax += ($invoiceTax->getTaxPrice() * $this->getRate());
		}

		foreach ($this->getDepositInvoices() as $depositInvoice) {
			$tax -= $depositInvoice->getTotalTax() * $depositInvoice->getRate();
		}

		return $tax;
	}

}
