<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_item")
 */
class InvoiceItem
{
	use IdentifierUnsigned;

	/** @ORM\ManyToOne(targetEntity="Invoice", inversedBy="items") */
	private Invoice $invoice;

	/** @ORM\Column(type="string") */
	private string $description;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $code;

	/** @ORM\Column(type="float") */
	private float $quantity;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Unit\Unit")
	 * @ORM\JoinColumn(name="unit_id", referencedColumnName="id")
	 */
	private Unit $unit;

	/** @ORM\Column(type="float") */
	private float $vat = 21.0;

	/** @ORM\Column(type="float") */
	private float $pricePerItem;

	/** @ORM\Column(type="float", nullable=true) */
	private float|null $buyPrice;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="buy_curreny_id", referencedColumnName="id", nullable=true)
	 */
	private Currency|null $buyCurrency;

	/** @ORM\Column(type="integer") */
	private int $position = 0;

	/** @ORM\Column(type="integer") */
	private int $sale = 0;

	/** @ORM\Column(type="string") */
	private string $saleDescription = 'Sleva';


	public function __construct(
		Invoice $invoice,
		string $description,
		float $quantity,
		Unit $unit,
		float $pricePerItem
	) {
		$this->invoice = $invoice;
		$this->description = $description;
		$this->quantity = $quantity;
		$this->unit = $unit;
		$this->pricePerItem = $pricePerItem;
	}


	public function getInvoice(): Invoice
	{
		return $this->invoice;
	}


	public function setInvoice(Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function setDescription(string $description): void
	{
		$this->description = $description;
	}


	public function getCode(): ?string
	{
		return $this->code;
	}


	public function setCode(?string $code): void
	{
		$this->code = $code;
	}


	public function getUnit(): Unit
	{
		return $this->unit;
	}


	public function setUnit(Unit $unit): void
	{
		$this->unit = $unit;
	}


	public function getPricePerItemWithVat(): float
	{
		$price = $this->getPricePerItem();

		return $price + (($price / 100) * $this->getVat());
	}


	public function getPricePerItem(): float
	{
		return $this->pricePerItem;
	}


	public function setPricePerItem(float $pricePerItem): void
	{
		$this->pricePerItem = $pricePerItem;
	}


	public function getBuyPrice(): ?float
	{
		return $this->buyPrice;
	}


	public function setBuyPrice(?float $buyPrice): void
	{
		$this->buyPrice = $buyPrice;
	}


	public function getBuyCurrency(): ?Currency
	{
		return $this->buyCurrency;
	}


	public function setBuyCurrency(?Currency $buyCurrency): void
	{
		$this->buyCurrency = $buyCurrency;
	}


	public function getBuyPriceInCurrency(Currency $currency, float $rate = 26.0, float $reverseRate = 30.0): float
	{
		$price = $this->getBuyPrice();

		if ($price === null || $price <= 0.0) {
			return 0.0;
		}
		if (
			$this->getBuyCurrency() === null
			|| $currency->getId() === $this->getBuyCurrency()->getId()
		) {
			return $price;
		}
		if ($currency->getCode() === 'CZK') {
			$price *= $reverseRate;
		} else {
			$price /= $rate;
		}

		return $price;
	}


	public function getTotalBuyPriceInCurrency(Currency $currency): float
	{
		return $this->getQuantity() * $this->getBuyPriceInCurrency($currency);
	}


	public function getVat(): float
	{
		return $this->vat;
	}


	public function setVat(float $vat): void
	{
		$this->vat = $vat;
	}


	public function getPosition(): int
	{
		return $this->position;
	}


	public function setPosition(int $position): void
	{
		$this->position = $position;
	}


	public function getSaleDescription(): string
	{
		return $this->saleDescription;
	}


	public function setSaleDescription(string $saleDescription): void
	{
		$this->saleDescription = $saleDescription;
	}


	public function getSale(): int
	{
		return $this->sale;
	}


	public function setSale(int $sale): void
	{
		$this->sale = $sale;
	}


	public function getSalePrice(): float
	{
		return -(($this->getTotalPrice() / 100) * $this->sale);
	}


	public function getTotalPrice(): float
	{
		return $this->pricePerItem * $this->getQuantity();
	}


	public function getQuantity(): float
	{
		return $this->quantity;
	}


	public function setQuantity(float $quantity): void
	{
		$this->quantity = $quantity;
	}


	public function getSalePriceWithVat(): float
	{
		return -(($this->getTotalPriceWithVat() / 100) * $this->sale);
	}


	public function getTotalPriceWithVat(): float
	{
		$totalPrice = $this->getTotalPrice();

		return $totalPrice + (($totalPrice / 100) * $this->getVat());
	}
}
