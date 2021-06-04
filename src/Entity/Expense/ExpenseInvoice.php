<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Address\Entity\Country;
use MatiCore\Currency\Currency;

/**
 * Class ExpenseInvoice
 * @package MatiCore\Invoice
 * @ORM\Entity()
 */
class ExpenseInvoice extends Expense
{

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierInvoiceNumber = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $variableSymbol = null;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $supplierName;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierCin = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierTin = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierStreet = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierCity = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierZipCode = null;

	/**
	 * @var Country
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="customer_country_id", referencedColumnName="id", nullable=true)
	 */
	protected Country $supplierCountry;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierBankAccount = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierIBAN = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $supplierSWIFT = null;

	/**
	 * @var ExpenseInvoiceItem[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\ExpenseInvoiceItem", mappedBy="expense")
	 * @ORM\OrderBy({"position"="ASC"})
	 */
	protected array|Collection $items;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="date", nullable=true)
	 */
	protected \DateTime|null $datePrint = null;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	protected int $deliveryType = ExpenseDeliveryType::ROAD;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $weight = 0.0;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $productCode = null;

	/**
	 * ExpenseInvoice constructor.
	 * @param string $number
	 * @param string $description
	 * @param Currency $currency
	 * @param float $totalPrice
	 * @param \DateTime $date
	 * @param string $supplierName
	 * @throws \Exception
	 */
	public function __construct(string $number, string $description, Currency $currency, float $totalPrice, \DateTime $date, string $supplierName)
	{
		parent::__construct($number, $description, $currency, $totalPrice, $date);
		$this->supplierName = $supplierName;
		$this->items = new ArrayCollection();
	}

	/**
	 * @return string|null
	 */
	public function getVariableSymbol(): ?string
	{
		return $this->variableSymbol;
	}

	/**
	 * @param string|null $variableSymbol
	 */
	public function setVariableSymbol(?string $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierInvoiceNumber(): ?string
	{
		return $this->supplierInvoiceNumber;
	}

	/**
	 * @param string|null $supplierInvoiceNumber
	 */
	public function setSupplierInvoiceNumber(?string $supplierInvoiceNumber): void
	{
		$this->supplierInvoiceNumber = $supplierInvoiceNumber;
	}

	/**
	 * @return string
	 */
	public function getSupplierName(): string
	{
		return $this->supplierName;
	}

	/**
	 * @param string $supplierName
	 */
	public function setSupplierName(string $supplierName): void
	{
		$this->supplierName = $supplierName;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierCin(): ?string
	{
		return $this->supplierCin;
	}

	/**
	 * @param string|null $supplierCin
	 */
	public function setSupplierCin(?string $supplierCin): void
	{
		$this->supplierCin = $supplierCin;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierTin(): ?string
	{
		return $this->supplierTin;
	}

	/**
	 * @param string|null $supplierTin
	 */
	public function setSupplierTin(?string $supplierTin): void
	{
		$this->supplierTin = $supplierTin;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierStreet(): ?string
	{
		return $this->supplierStreet;
	}

	/**
	 * @param string|null $supplierStreet
	 */
	public function setSupplierStreet(?string $supplierStreet): void
	{
		$this->supplierStreet = $supplierStreet;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierCity(): ?string
	{
		return $this->supplierCity;
	}

	/**
	 * @param string|null $supplierCity
	 */
	public function setSupplierCity(?string $supplierCity): void
	{
		$this->supplierCity = $supplierCity;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierZipCode(): ?string
	{
		return $this->supplierZipCode;
	}

	/**
	 * @param string|null $supplierZipCode
	 */
	public function setSupplierZipCode(?string $supplierZipCode): void
	{
		$this->supplierZipCode = $supplierZipCode;
	}

	/**
	 * @return Country
	 */
	public function getSupplierCountry(): Country
	{
		return $this->supplierCountry;
	}

	/**
	 * @param Country $supplierCountry
	 */
	public function setSupplierCountry(Country $supplierCountry): void
	{
		$this->supplierCountry = $supplierCountry;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierBankAccount(): ?string
	{
		return $this->supplierBankAccount;
	}

	/**
	 * @param string|null $supplierBankAccount
	 */
	public function setSupplierBankAccount(?string $supplierBankAccount): void
	{
		$this->supplierBankAccount = $supplierBankAccount;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierIBAN(): ?string
	{
		return $this->supplierIBAN;
	}

	/**
	 * @param string|null $supplierIBAN
	 */
	public function setSupplierIBAN(?string $supplierIBAN): void
	{
		$this->supplierIBAN = $supplierIBAN;
	}

	/**
	 * @return string|null
	 */
	public function getSupplierSWIFT(): ?string
	{
		return $this->supplierSWIFT;
	}

	/**
	 * @param string|null $supplierSWIFT
	 */
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

	/**
	 * @param ExpenseInvoiceItem[]|Collection $items
	 */
	public function setItems(array|Collection $items): void
	{
		$this->items = $items;
	}

	/**
	 * @param ExpenseInvoiceItem $item
	 */
	public function addItem(ExpenseInvoiceItem $item): void
	{
		$this->items[] = $item;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getDatePrint(): ?\DateTime
	{
		return $this->datePrint;
	}

	/**
	 * @param \DateTime|null $datePrint
	 */
	public function setDatePrint(?\DateTime $datePrint): void
	{
		$this->datePrint = $datePrint;
	}

	/**
	 * @return float
	 */
	public function getItemsTotalPrice(): float
	{
		if(count($this->getItems()) === 0){
			return 0.0;
		}

		$price = 0.0;

		foreach($this->getItems() as $item){
			$price += $item->getTotalPrice();
		}

		return round($price, 2);
	}

	/**
	 * @return bool
	 */
	public function checkTotalPrice(): bool
	{
		if(count($this->getItems()) === 0){
			return true;
		}

		return ($this->getItemsTotalPrice() + $this->getTotalTax()) === $this->getTotalPrice()
			|| round($this->getItemsTotalPrice() + $this->getTotalTax()) === $this->getTotalPrice();
	}

	/**
	 * @return float
	 */
	public function getItemsTotalTax(): float
	{
		if(count($this->getItems()) === 0){
			return 0.0;
		}

		$tax = 0;

		foreach($this->getItems() as $item){
			$tax += (($item->getTotalPrice() / 100) * $item->getVat());
		}

		return round($tax, 2);
	}

	/**
	 * @return bool
	 */
	public function checkTotalTax(): bool
	{
		if(count($this->getItems()) === 0){
			return true;
		}

		return $this->getItemsTotalTax() === $this->getTotalTax();
	}

	/**
	 * @return int
	 */
	public function getDeliveryType(): int
	{
		return $this->deliveryType ?? ExpenseDeliveryType::ROAD;
	}

	/**
	 * @param int $deliveryType
	 */
	public function setDeliveryType(int $deliveryType): void
	{
		$this->deliveryType = $deliveryType;
	}

	/**
	 * @return float
	 */
	public function getWeight(): float
	{
		return $this->weight ?? 0.0;
	}

	/**
	 * @param float $weight
	 */
	public function setWeight(float $weight): void
	{
		$this->weight = $weight;
	}

	/**
	 * @return string|null
	 */
	public function getProductCode(): ?string
	{
		return $this->productCode;
	}

	/**
	 * @param string|null $productCode
	 */
	public function setProductCode(?string $productCode): void
	{
		$this->productCode = $productCode;
	}

}