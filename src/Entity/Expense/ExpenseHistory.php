<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\User\BaseUser;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * Class ExpenseHistory
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__expense_history")
 */
class ExpenseHistory
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var Expense
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\Expense")
	 * @ORM\JoinColumn(name="expense_id", referencedColumnName="id")
	 */
	private Expense $expense;

	/**
	 * @var BaseUser|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
	 */
	private BaseUser|null $user;

	/**
	 * @var string
	 * @ORM\Column(type="text")
	 */
	private string $description;

	/**
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	private \DateTime $date;

	/**
	 * ExpenseHistory constructor.
	 * @param Expense $expense
	 * @param string $description
	 * @throws \Exception
	 */
	public function __construct(Expense $expense, string $description)
	{
		$this->expense = $expense;
		$this->description = $description;
		$this->date = DateTime::from('NOW');
	}

	/**
	 * @return Expense
	 */
	public function getExpense(): Expense
	{
		return $this->expense;
	}

	/**
	 * @return BaseUser|null
	 */
	public function getUser(): ?BaseUser
	{
		return $this->user;
	}

	/**
	 * @param BaseUser|null $user
	 */
	public function setUser(?BaseUser $user): void
	{
		$this->user = $user;
	}

	/**
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * @return \DateTime
	 */
	public function getDate(): \DateTime
	{
		return $this->date;
	}

}