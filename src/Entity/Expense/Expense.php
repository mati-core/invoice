<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__expense')]
class Expense
{
	use IdentifierUnsigned;

	public const
		DELIVERY_TYPE_ROAD = 3,
		DELIVERY_TYPE_AIRPLANE = 4,
		DELIVERY_TYPE_ROAD_AND_AIRPLANE = 5;

	public const
		PAY_METHOD_CASH = 'cash',
		PAY_METHOD_BANK = 'bank',
		PAY_METHOD_CARD = 'card';

	public const PAY_METHODS = [
		self::PAY_METHOD_CASH => 'Hotově',
		self::PAY_METHOD_BANK => 'Bankovní převod',
		self::PAY_METHOD_CARD => 'Kartou',
	];

	#[ORM\Column(type: 'string', unique: true)]
	private string $number;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $category = null;

	#[ORM\Column(type: 'string')]
	private string $description;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private Currency $currency;

	#[ORM\Column(type: 'float')]
	private float $rate = 1.0;

	#[ORM\Column(type: 'float')]
	private float $totalPrice;

	#[ORM\Column(type: 'float')]
	private float $totalTax = 0.0;

	#[ORM\Column(type: 'date')]
	private \DateTime $date;

	#[ORM\Column(type: 'date', nullable: true)]
	private \DateTime|null $dueDate = null;

	#[ORM\Column(type: 'boolean')]
	private bool $paid = false;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $payMethod = null;

	#[ORM\Column(type: 'date', nullable: true)]
	private \DateTime|null $payDate = null;

	#[ORM\Column(type: 'boolean')]
	private bool $hidden = false;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $createDate;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $createUser = null;

	#[ORM\Column(type: 'text', nullable: true)]
	private string|null $note = null;

	#[ORM\Column(type: 'boolean')]
	private bool $deleted = false;


	public function __construct(
		string $number,
		string $description,
		Currency $currency,
		float $totalPrice,
		\DateTime $date
	) {
		$this->number = $number;
		$this->description = $description;
		$this->currency = $currency;
		$this->totalPrice = $totalPrice;
		$this->date = $date;
		$this->createDate = new \DateTime;
	}


	public function getNumber(): string
	{
		return $this->number;
	}


	public function getCategory(): ?string
	{
		return $this->category;
	}


	public function setCategory(?string $category): void
	{
		$this->category = $category;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function setDescription(string $description): void
	{
		$this->description = $description;
	}


	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	public function setCurrency(Currency $currency): void
	{
		$this->currency = $currency;
	}


	public function getRate(): float
	{
		return $this->rate;
	}


	public function setRate(float $rate): void
	{
		$this->rate = $rate;
	}


	public function getTotalPrice(): float
	{
		return $this->totalPrice;
	}


	public function setTotalPrice(float $totalPrice): void
	{
		$this->totalPrice = $totalPrice;
	}


	public function getTotalTax(): float
	{
		return $this->totalTax;
	}


	public function setTotalTax(float $totalTax): void
	{
		$this->totalTax = $totalTax;
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}


	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}


	public function getDueDate(): ?\DateTime
	{
		return $this->dueDate;
	}


	public function setDueDate(?\DateTime $dueDate): void
	{
		$this->dueDate = $dueDate;
	}


	public function isPaid(): bool
	{
		return $this->paid;
	}


	public function setPaid(bool $paid): void
	{
		$this->paid = $paid;
	}


	public function isHidden(): bool
	{
		return $this->hidden;
	}


	public function setHidden(bool $hidden): void
	{
		$this->hidden = $hidden;
	}


	public function getCreateDate(): \DateTime
	{
		return $this->createDate;
	}


	public function setCreateDate(\DateTime $createDate): void
	{
		$this->createDate = $createDate;
	}


	public function getCreateUser(): ?int
	{
		return $this->createUser;
	}


	public function setCreateUser(?int $createUser): void
	{
		$this->createUser = $createUser;
	}


	public function getPayMethod(): ?string
	{
		return $this->payMethod;
	}


	public function setPayMethod(?string $payMethod): void
	{
		$this->payMethod = $payMethod;
	}


	public function getPayDate(): ?\DateTime
	{
		return $this->payDate;
	}


	public function setPayDate(?\DateTime $payDate): void
	{
		$this->payDate = $payDate;
	}


	public function getNote(): ?string
	{
		return $this->note;
	}


	public function setNote(?string $note): void
	{
		$this->note = $note;
	}


	public function isDeleted(): bool
	{
		return $this->deleted;
	}


	public function setDeleted(bool $deleted): void
	{
		$this->deleted = $deleted;
	}
}
