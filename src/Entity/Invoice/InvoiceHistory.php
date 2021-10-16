<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__invoice_history')]
class InvoiceHistory
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Invoice::class)]
	private Invoice $invoice;

	#[ORM\Column(type: 'integer', nullable: true)]
	private ?int $userId = null;

	#[ORM\Column(type: 'text')]
	private string $description;

	#[ORM\Column(type: 'datetime')]
	private \DateTime $insertedDate;


	public function __construct(Invoice $invoice, string $description)
	{
		$this->invoice = $invoice;
		$this->description = $description;
		$this->insertedDate = new \DateTime;
	}


	public function getInvoice(): Invoice
	{
		return $this->invoice;
	}


	public function setInvoice(Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function getUserId(): ?int
	{
		return $this->userId;
	}


	public function setUserId(?int $userId): void
	{
		$this->userId = $userId;
	}


	public function getDescription(): string
	{
		return $this->description;
	}


	public function setDescription(string $description): void
	{
		$this->description = $description;
	}


	public function getInsertedDate(): \DateTime
	{
		return $this->insertedDate;
	}


	public function setInsertedDate(\DateTime $insertedDate): void
	{
		$this->insertedDate = $insertedDate;
	}
}
