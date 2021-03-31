<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Unit\Unit;
use Nette\SmartObject;

/**
 * Class ExpenseInvoiceItem
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__expense_item")
 */
class ExpenseInvoiceItem
{

	use UuidIdentifier;
	use SmartObject;

	/**
	 * @var Expense
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\ExpenseInvoice", inversedBy="items")
	 * @ORM\JoinColumn(name="expense_id", referencedColumnName="id")
	 */
	private Expense $expense;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $description;

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
	private float $vat;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $pricePerItem;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private int $position;

	/**
	 * ExpenseInvoiceItem constructor.
	 * @param Expense $expense
	 * @param string $description
	 * @param float $quantity
	 * @param Unit $unit
	 * @param float $vat
	 * @param float $pricePerItem
	 * @param int $position
	 */
	public function __construct(Expense $expense, string $description, float $quantity, Unit $unit, float $vat, float $pricePerItem, int $position)
	{
		$this->expense = $expense;
		$this->description = $description;
		$this->quantity = $quantity;
		$this->unit = $unit;
		$this->vat = $vat;
		$this->pricePerItem = $pricePerItem;
		$this->position = $position;
	}

	/**
	 * @return Expense
	 */
	public function getExpense(): Expense
	{
		return $this->expense;
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
	 * @return float
	 */
	public function getTotalPrice(): float
	{
		return $this->getPricePerItem() * $this->getQuantity();
	}

}