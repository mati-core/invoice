<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Country\Entity\Country;
use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @deprecated
 */
class ExpenseInvoice extends Expense
{
	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierInvoiceNumber = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $variableSymbol = null;

	/** @ORM\Column(type="string") */
	private string $supplierName;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierCin = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierTin = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierStreet = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierCity = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierZipCode = null;

	#[ORM\ManyToOne(targetEntity: Country::class)]
	private Country $supplierCountry;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierBankAccount = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierIBAN = null;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $supplierSWIFT = null;

	/**
	 * @var ExpenseInvoiceItem[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\ExpenseInvoiceItem", mappedBy="expense")
	 * @ORM\OrderBy({"position"="ASC"})
	 */
	private array|Collection $items;

	/** @ORM\Column(type="date", nullable=true) */
	private \DateTime|null $datePrint = null;

	/** @ORM\Column(type="integer") */
	private int $deliveryType = Expense::DELIVERY_TYPE_ROAD;

	/** @ORM\Column(type="float") */
	private float $weight = 0.0;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $productCode = null;


	public function __construct(
		string $number,
		string $description,
		Currency $currency,
		float $totalPrice,
		\DateTime $date,
		string $supplierName
	) {
		parent::__construct($number, $description, $currency, $totalPrice, $date);
		$this->supplierName = $supplierName;
		$this->items = new ArrayCollection();
	}


	public function getVariableSymbol(): ?string
	{
		return $this->variableSymbol;
	}


	public function setVariableSymbol(?string $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}


	public function getSupplierInvoiceNumber(): ?string
	{
		return $this->supplierInvoiceNumber;
	}


	public function setSupplierInvoiceNumber(?string $supplierInvoiceNumber): void
	{
		$this->supplierInvoiceNumber = $supplierInvoiceNumber;
	}


	public function getSupplierName(): string
	{
		return $this->supplierName;
	}


	public function setSupplierName(string $supplierName): void
	{
		$this->supplierName = $supplierName;
	}


	public function getSupplierCin(): ?string
	{
		return $this->supplierCin;
	}


	public function setSupplierCin(?string $supplierCin): void
	{
		$this->supplierCin = $supplierCin;
	}


	public function getSupplierTin(): ?string
	{
		return $this->supplierTin;
	}


	public function setSupplierTin(?string $supplierTin): void
	{
		$this->supplierTin = $supplierTin;
	}


	public function getSupplierStreet(): ?string
	{
		return $this->supplierStreet;
	}


	public function setSupplierStreet(?string $supplierStreet): void
	{
		$this->supplierStreet = $supplierStreet;
	}


	public function getSupplierCity(): ?string
	{
		return $this->supplierCity;
	}


	public function setSupplierCity(?string $supplierCity): void
	{
		$this->supplierCity = $supplierCity;
	}


	public function getSupplierZipCode(): ?string
	{
		return $this->supplierZipCode;
	}


	public function setSupplierZipCode(?string $supplierZipCode): void
	{
		$this->supplierZipCode = $supplierZipCode;
	}


	public function getSupplierCountry(): Country
	{
		return $this->supplierCountry;
	}


	public function setSupplierCountry(Country $supplierCountry): void
	{
		$this->supplierCountry = $supplierCountry;
	}


	public function getSupplierBankAccount(): ?string
	{
		return $this->supplierBankAccount;
	}


	public function setSupplierBankAccount(?string $supplierBankAccount): void
	{
		$this->supplierBankAccount = $supplierBankAccount;
	}


	public function getSupplierIBAN(): ?string
	{
		return $this->supplierIBAN;
	}


	public function setSupplierIBAN(?string $supplierIBAN): void
	{
		$this->supplierIBAN = $supplierIBAN;
	}


	public function getSupplierSWIFT(): ?string
	{
		return $this->supplierSWIFT;
	}


	public function setSupplierSWIFT(?string $supplierSWIFT): void
	{
		$this->supplierSWIFT = $supplierSWIFT;
	}


	/**
	 * @return ExpenseInvoiceItem[]|Collection
	 */
	public function getItems(): array|Collection
	{
		return $this->items;
	}


	public function resetItems(): void
	{
		$this->items = new ArrayCollection;
	}


	public function addItem(ExpenseInvoiceItem $item): void
	{
		$this->items[] = $item;
	}


	public function getDatePrint(): ?\DateTime
	{
		return $this->datePrint;
	}


	public function setDatePrint(?\DateTime $datePrint): void
	{
		$this->datePrint = $datePrint;
	}


	public function getItemsTotalPrice(): float
	{
		if (count($this->getItems()) === 0) {
			return 0.0;
		}

		$price = 0.0;

		foreach ($this->getItems() as $item) {
			$price += $item->getTotalPrice();
		}

		return round($price, 2);
	}


	public function checkTotalPrice(): bool
	{
		if (count($this->getItems()) === 0) {
			return true;
		}

		return ($this->getItemsTotalPrice() + $this->getTotalTax()) === $this->getTotalPrice()
			|| round($this->getItemsTotalPrice() + $this->getTotalTax()) === $this->getTotalPrice();
	}


	public function getItemsTotalTax(): float
	{
		if (count($this->getItems()) === 0) {
			return 0.0;
		}

		$tax = 0;

		foreach ($this->getItems() as $item) {
			$tax += (($item->getTotalPrice() / 100) * $item->getVat());
		}

		return round($tax, 2);
	}


	public function checkTotalTax(): bool
	{
		if (count($this->getItems()) === 0) {
			return true;
		}

		return $this->getItemsTotalTax() === $this->getTotalTax();
	}


	public function getDeliveryType(): int
	{
		return $this->deliveryType ?? Expense::DELIVERY_TYPE_ROAD;
	}


	public function setDeliveryType(int $deliveryType): void
	{
		$this->deliveryType = $deliveryType;
	}


	public function getWeight(): float
	{
		return $this->weight ?? 0.0;
	}


	public function setWeight(float $weight): void
	{
		$this->weight = $weight;
	}


	public function getProductCode(): ?string
	{
		return $this->productCode;
	}


	public function setProductCode(?string $productCode): void
	{
		$this->productCode = $productCode;
	}
}
