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

	/**
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	protected string $number;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $category = null;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $description;

	/**
	 * @var Currency
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	protected Currency $currency;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $rate = 1.0;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $totalPrice;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $totalTax = 0.0;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="date")
	 */
	protected \DateTime $date;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="date", nullable=true)
	 */
	protected \DateTime|null $dueDate = null;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $paid = false;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $payMethod = null;

	/**
	 * @var \DateTime|null
	 * @ORM\Column(type="date", nullable=true)
	 */
	protected \DateTime|null $payDate = null;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $hidden = false;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	protected \DateTime $createDate;

	/**
	 * @var BaseUser|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="create_user_id", referencedColumnName="id", nullable=true)
	 */
	protected BaseUser|null $createUser = null;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	protected string|null $note = null;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $deleted = false;


	/**
	 * Expense constructor.
	 *
	 * @param string $number
	 * @param string $description
	 * @param Currency $currency
	 * @param float $totalPrice
	 * @param \DateTime $date
	 * @throws \Exception
	 */
	public function __construct(
		string $number, string $description, Currency $currency, float $totalPrice, \DateTime $date
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


	/**
	 * @return string|null
	 */
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


	/**
	 * @return Currency
	 */
	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	/**
	 * @param Currency $currency
	 */
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


	/**
	 * @return \DateTime
	 */
	public function getDate(): \DateTime
	{
		return $this->date;
	}


	/**
	 * @param \DateTime $date
	 */
	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}


	/**
	 * @return \DateTime|null
	 */
	public function getDueDate(): ?\DateTime
	{
		return $this->dueDate;
	}


	/**
	 * @param \DateTime|null $dueDate
	 */
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


	/**
	 * @return \DateTime
	 */
	public function getCreateDate(): \DateTime
	{
		return $this->createDate;
	}


	/**
	 * @param \DateTime $createDate
	 */
	public function setCreateDate(\DateTime $createDate): void
	{
		$this->createDate = $createDate;
	}


	/**
	 * @return BaseUser|null
	 */
	public function getCreateUser(): ?BaseUser
	{
		return $this->createUser;
	}


	/**
	 * @param BaseUser|null $createUser
	 */
	public function setCreateUser(?BaseUser $createUser): void
	{
		$this->createUser = $createUser;
	}


	/**
	 * @return string|null
	 */
	public function getPayMethod(): ?string
	{
		return $this->payMethod;
	}


	public function setPayMethod(?string $payMethod): void
	{
		$this->payMethod = $payMethod;
	}


	/**
	 * @return \DateTime|null
	 */
	public function getPayDate(): ?\DateTime
	{
		return $this->payDate;
	}


	/**
	 * @param \DateTime|null $payDate
	 */
	public function setPayDate(?\DateTime $payDate): void
	{
		$this->payDate = $payDate;
	}


	/**
	 * @return string|null
	 */
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
