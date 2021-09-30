<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class InvoiceProforma extends InvoiceCore
{
	/** @ORM\OneToOne(targetEntity="\MatiCore\Invoice\Invoice", inversedBy="proforma")
	 * @ORM\JoinColumn(name="invoice_id", referencedColumnName="id", nullable=true) */
	private Invoice|null $invoice;


	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}


	public function setInvoice(?Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function isProforma(): bool
	{
		return true;
	}
}
