<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Doctrine\ORM\Mapping as ORM;

/**
 * Class InvoiceProforma
 * @package MatiCore\Invoice
 * @ORM\Entity()
 */
class InvoiceProforma extends InvoiceCore
{

	/**
	 * @var Invoice|null
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\Invoice", inversedBy="proforma")
	 * @ORM\JoinColumn(name="invoice_id", referencedColumnName="id", nullable=true)
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

	/**
	 * @return bool
	 */
	public function isProforma(): bool
	{
		return true;
	}

}