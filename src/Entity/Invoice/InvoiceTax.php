<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;

/**
 * Class InvoiceTax
 * @package App\Model
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_tax")
 */
class InvoiceTax
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var InvoiceCore
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="taxList")
	 */
	private InvoiceCore $invoice;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $tax;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $price;

	/**
	 * InvoiceTax constructor.
	 * @param InvoiceCore $invoice
	 * @param float $tax
	 * @param float $price
	 */
	public function __construct(InvoiceCore $invoice, float $tax, float $price)
	{
		$this->invoice = $invoice;
		$this->tax = $tax;
		$this->price = $price;
	}

	/**
	 * @return float
	 */
	public function getTax(): float
	{
		return $this->tax;
	}

	/**
	 * @param float $tax
	 */
	public function setTax(float $tax): void
	{
		$this->tax = $tax;
	}

	/**
	 * @return float
	 */
	public function getPrice(): float
	{
		return $this->price;
	}

	/**
	 * @param float $price
	 */
	public function setPrice(float $price): void
	{
		$this->price = $price;
	}

	/**
	 * @return float
	 */
	public function getTaxPrice(): float
	{
		return ($this->price / 100) * $this->tax;
	}

}