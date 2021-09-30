<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Currency\Currency;
use MatiCore\User\BaseUser;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__expense")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discriminator", type="string")
 */
class Expense
{
	use UuidIdentifier;
	use SmartObject;

	/** @ORM\Column(type="string", unique=true) */
	protected string $number;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $category = null;

	/** @ORM\Column(type="string") */
	protected string $description;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id") */
	protected Currency $currency;

	/** @ORM\Column(type="float") */
	protected float $rate = 1.0;

	/** @ORM\Column(type="float") */
	protected float $totalPrice;

	/** @ORM\Column(type="float") */
	protected float $totalTax = 0.0;

	/** @ORM\Column(type="date") */
	protected \DateTime $date;

	/** @ORM\Column(type="date", nullable=true) */
	protected \DateTime|null $dueDate = null;

	/** @ORM\Column(type="boolean") */
	protected bool $paid = false;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $payMethod = null;

	/** @ORM\Column(type="date", nullable=true) */
	protected \DateTime|null $payDate = null;

	/** @ORM\Column(type="boolean") */
	protected bool $hidden = false;

	/** @ORM\Column(type="datetime") */
	protected \DateTime $createDate;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="create_user_id", referencedColumnName="id", nullable=true) */
	protected BaseUser|null $createUser = null;

	/** @ORM\Column(type="text", nullable=true) */
	protected string|null $note = null;

	/** @ORM\Column(type="boolean") */
	protected bool $deleted = false;


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
		$this->createDate = DateTime::from('NOW');
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


	public function getCreateUser(): ?BaseUser
	{
		return $this->createUser;
	}


	public function setCreateUser(?BaseUser $createUser): void
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
