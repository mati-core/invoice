<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Invoice
 * @package MatiCore\Invoice
 * @ORM\Entity()
 */
class Invoice extends InvoiceCore
{

	/**
	 * @var InvoiceProforma|null
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\InvoiceProforma", mappedBy="invoice")
	 * @ORM\JoinColumn(name="proforma_id", referencedColumnName="id", nullable=true)
	 */
	private InvoiceProforma|null $proforma = null;

	/**
	 * @var FixInvoice|null
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\FixInvoice", mappedBy="invoice")
	 * @ORM\JoinColumn(name="fixing_invoice_id", referencedColumnName="id", nullable=true)
	 */
	private FixInvoice|null $fixInvoice = null;

	/**
	 * @return FixInvoice|null
	 */
	public function getFixInvoice(): ?FixInvoice
	{
		return $this->fixInvoice;
	}

	/**
	 * @param FixInvoice|null $fixInvoice
	 */
	public function setFixInvoice(?FixInvoice $fixInvoice): void
	{
		$this->fixInvoice = $fixInvoice;
	}

	/**
	 * @return InvoiceProforma|null
	 */
	public function getProforma(): ?InvoiceProforma
	{
		return $this->proforma;
	}

	/**
	 * @param InvoiceProforma|null $proforma
	 */
	public function setProforma(?InvoiceProforma $proforma): void
	{
		$this->proforma = $proforma;
	}

	/**
	 * @return float
	 */
	public function getTotalPriceWithoutTaxCZK(): float
	{
		return $this->getItemTotalPrice() * $this->getRate();
	}

	/**
	 * @return float
	 */
	public function getItemTotalPrice(): float
	{
		$totalPrice = parent::getItemTotalPrice();

		foreach ($this->getDepositInvoices() as $depositInvoice) {
			$payDocument = $depositInvoice->getPayDocument();
			if ($depositInvoice instanceof InvoiceProforma && $payDocument !== null) {
				$totalPrice -= $payDocument->getItemTotalPrice();
			} else {
				$totalPrice -= $depositInvoice->getItemTotalPrice();
			}
		}

		return $totalPrice;
	}

}