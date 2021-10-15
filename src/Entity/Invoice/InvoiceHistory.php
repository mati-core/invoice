<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_history")
 */
class InvoiceHistory
{
	use IdentifierUnsigned;

	/** @ORM\ManyToOne(targetEntity="Invoice", inversedBy="history") */
	private Invoice $invoice;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true)
	 */
	private BaseUser|null $user;

	/** @ORM\Column(type="text") */
	private string $description;

	/** @ORM\Column(type="datetime") */
	private \DateTime $date;


	public function __construct(Invoice $invoice, string $description)
	{
		$this->invoice = $invoice;
		$this->description = $description;
		$this->date = new \DateTime;
	}


	public function getInvoice(): Invoice
	{
		return $this->invoice;
	}


	public function setInvoice(Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function getUser(): ?BaseUser
	{
		return $this->user;
	}


	public function setUser(?BaseUser $user): void
	{
		$this->user = $user;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function setDescription(string $description): void
	{
		$this->description = $description;
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}


	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}
}
