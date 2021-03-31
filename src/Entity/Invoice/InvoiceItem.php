<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Currency\Currency;
use MatiCore\Unit\Unit;
use Nette\SmartObject;

/**
 * Class InvoiceItem
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_item")
 */
class InvoiceItem
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var InvoiceCore
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="items")
	 */
	private InvoiceCore $invoice;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $description;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $code;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $quantity;

	/**
	 * @var Unit
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Unit\Unit")
	 * @ORM\JoinColumn(name="unit_id", referencedColumnName="id")
	 */
	private Unit $unit;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $vat = 21.0;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $pricePerItem;

	/**
	 * @var float|null
	 * @ORM\Column(type="float", nullable=true)
	 */
	private float|null $buyPrice;

	/**
	 * @var Currency|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="buy_curreny_id", referencedColumnName="id", nullable=true)
	 */
	private Currency|null $buyCurrency;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private int $position = 0;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private int $sale = 0;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $saleDescription = 'Sleva';

	/**
	 * InvoiceItem constructor.
	 * @param InvoiceCore $invoice
	 * @param string $description
	 * @param float $quantity
	 * @param Unit $unit
	 * @param float $pricePerItem
	 */
	public function __construct(InvoiceCore $invoice, string $description, float $quantity, Unit $unit, float $pricePerItem)
	{
		$this->invoice = $invoice;
		$this->description = $description;
		$this->quantity = $quantity;
		$this->unit = $unit;
		$this->pricePerItem = $pricePerItem;
	}

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
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription(string $description): void
	{
		$this->description = $description;
	}

	/**
	 * @return string|null
	 */
	public function getCode(): ?string
	{
		return $this->code;
	}

	/**
	 * @param string|null $code
	 */
	public function setCode(?string $code): void
	{
		$this->code = $code;
	}

	/**
	 * @return Unit
	 */
	public function getUnit(): Unit
	{
		return $this->unit;
	}

	/**
	 * @param Unit $unit
	 */
	public function setUnit(Unit $unit): void
	{
		$this->unit = $unit;
	}

	/**
	 * @return float
	 */
	public function getPricePerItemWithVat(): float
	{
		$price = $this->getPricePerItem();

		return $price + (($price / 100) * $this->getVat());
	}

	/**
	 * @return float
	 */
	public function getPricePerItem(): float
	{
		return $this->pricePerItem;
	}

	/**
	 * @param float $pricePerItem
	 */
	public function setPricePerItem(float $pricePerItem): void
	{
		$this->pricePerItem = $pricePerItem;
	}

	/**
	 * @return float|null
	 */
	public function getBuyPrice(): ?float
	{
		return $this->buyPrice;
	}

	/**
	 * @param float|null $buyPrice
	 */
	public function setBuyPrice(?float $buyPrice): void
	{
		$this->buyPrice = $buyPrice;
	}

	/**
	 * @return Currency|null
	 */
	public function getBuyCurrency(): ?Currency
	{
		return $this->buyCurrency;
	}

	/**
	 * @param Currency|null $buyCurrency
	 */
	public function setBuyCurrency(?Currency $buyCurrency): void
	{
		$this->buyCurrency = $buyCurrency;
	}

	/**
	 * @param Currency $currency
	 * @param float $rate
	 * @param float $reverseRate
	 * @return float
	 */
	public function getBuyPriceInCurrency(Currency $currency, float $rate = 26.0, float $reverseRate = 30.0): float
	{
		$price = $this->getBuyPrice();

		if ($price === null || $price <= 0.0) {
			return 0.0;
		}

		if ($this->getBuyCurrency() === null || $currency->getId() === $this->getBuyCurrency()->getId()) {
			return $price;
		}

		if ($currency->getCode() === 'CZK') {
			$price *= $reverseRate;
		} else {
			$price /= $rate;
		}

		return $price;
	}

	/**
	 * @param Currency $currency
	 * @return float
	 */
	public function getTotalBuyPriceInCurrency(Currency $currency): float
	{
		return $this->getQuantity() * $this->getBuyPriceInCurrency($currency);
	}

	/**
	 * @return float
	 */
	public function getVat(): float
	{
		return $this->vat;
	}

	/**
	 * @param float $vat
	 */
	public function setVat(float $vat): void
	{
		$this->vat = $vat;
	}

	/**
	 * @return int
	 */
	public function getPosition(): int
	{
		return $this->position;
	}

	/**
	 * @param int $position
	 */
	public function setPosition(int $position): void
	{
		$this->position = $position;
	}

	/**
	 * @return string
	 */
	public function getSaleDescription(): string
	{
		return $this->saleDescription;
	}

	/**
	 * @param string $saleDescription
	 */
	public function setSaleDescription(string $saleDescription): void
	{
		$this->saleDescription = $saleDescription;
	}

	/**
	 * @return int
	 */
	public function getSale(): int
	{
		return $this->sale;
	}

	/**
	 * @param int $sale
	 */
	public function setSale(int $sale): void
	{
		$this->sale = $sale;
	}

	/**
	 * @return float
	 */
	public function getSalePrice(): float
	{
		return -(($this->getTotalPrice() / 100) * $this->sale);
	}

	/**
	 * @return float
	 */
	public function getTotalPrice(): float
	{
		return $this->pricePerItem * $this->getQuantity();
	}

	/**
	 * @return float
	 */
	public function getQuantity(): float
	{
		return $this->quantity;
	}

	/**
	 * @param float $quantity
	 */
	public function setQuantity(float $quantity): void
	{
		$this->quantity = $quantity;
	}

	/**
	 * @return float
	 */
	public function getSalePriceWithVat(): float
	{
		return -(($this->getTotalPriceWithVat() / 100) * $this->sale);
	}

	/**
	 * @return float
	 */
	public function getTotalPriceWithVat(): float
	{
		$totalPrice = $this->getTotalPrice();

		return $totalPrice + (($totalPrice / 100) * $this->getVat());
	}

}