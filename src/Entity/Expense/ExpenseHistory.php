<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__expense_history')]
class ExpenseHistory
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Expense::class)]
	private Expense $expense;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $user = null;

	#[ORM\Column(type: 'text')]
	private string $description;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $date;


	public function __construct(Expense $expense, string $description)
	{
		$this->expense = $expense;
		$this->description = $description;
		$this->date = new \DateTime;
	}


	public function getExpense(): Expense
	{
		return $this->expense;
	}


	public function getUserId(): ?int
	{
		return $this->user;
	}


	public function setUserId(?int $user): void
	{
		$this->user = $user;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}
}
