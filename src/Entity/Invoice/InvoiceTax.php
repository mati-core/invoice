<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_tax")
 */
class InvoiceTax
{
	use SmartObject;
	use UuidIdentifier;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="taxList") */
	private InvoiceCore $invoice;

	/** @ORM\Column(type="float") */
	private float $tax;

	/** @ORM\Column(type="float") */
	private float $price;


	public function __construct(InvoiceCore $invoice, float $tax, float $price)
	{
		$this->invoice = $invoice;
		$this->tax = $tax;
		$this->price = $price;
	}


	public function getTax(): float
	{
		return $this->tax;
	}


	public function setTax(float $tax): void
	{
		$this->tax = $tax;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function setPrice(float $price): void
	{
		$this->price = $price;
	}


	public function getTaxPrice(): float
	{
		return ($this->price / 100) * $this->tax;
	}
}
