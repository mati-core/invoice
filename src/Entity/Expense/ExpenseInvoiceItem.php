<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__expense_item')]
class ExpenseInvoiceItem
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: ExpenseInvoice::class)]
	private Expense $expense;

	#[ORM\Column(type: 'string')]
	private string $description;

	#[ORM\Column(type: 'float')]
	private float $quantity;

	#[ORM\ManyToOne(targetEntity: Unit::class)]
	private Unit $unit;

	#[ORM\Column(type: 'float')]
	private float $vat;

	#[ORM\Column(type: 'float')]
	private float $pricePerItem;

	#[ORM\Column(type: 'integer')]
	private int $position;


	public function __construct(
		Expense $expense,
		string $description,
		float $quantity,
		Unit $unit,
		float $vat,
		float $pricePerItem,
		int $position
	) {
		$this->expense = $expense;
		$this->description = $description;
		$this->quantity = $quantity;
		$this->unit = $unit;
		$this->vat = $vat;
		$this->pricePerItem = $pricePerItem;
		$this->position = $position;
	}


	public function getExpense(): Expense
	{
		return $this->expense;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function setDescription(string $description): void
	{
		$this->description = $description;
	}


	public function getQuantity(): float
	{
		return $this->quantity;
	}


	public function setQuantity(float $quantity): void
	{
		$this->quantity = $quantity;
	}


	public function getUnit(): Unit
	{
		return $this->unit;
	}


	public function setUnit(Unit $unit): void
	{
		$this->unit = $unit;
	}


	public function getVat(): float
	{
		return $this->vat;
	}


	public function setVat(float $vat): void
	{
		$this->vat = $vat;
	}


	public function getPricePerItem(): float
	{
		return $this->pricePerItem;
	}


	public function setPricePerItem(float $pricePerItem): void
	{
		$this->pricePerItem = $pricePerItem;
	}


	public function getPosition(): int
	{
		return $this->position;
	}


	public function setPosition(int $position): void
	{
		$this->position = $position;
	}


	public function getTotalPrice(): float
	{
		return $this->getPricePerItem() * $this->getQuantity();
	}
}
