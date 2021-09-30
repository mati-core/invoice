<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\User\BaseUser;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_comment")
 */
class InvoiceComment
{
	use SmartObject;
	use UuidIdentifier;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="comments") */
	private InvoiceCore $invoice;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=true) */
	private BaseUser|null $user;

	/** @ORM\Column(type="text") */
	private string $description;

	/** @ORM\Column(type="datetime") */
	private \DateTime $date;


	public function __construct(InvoiceCore $invoice, string $description)
	{
		$this->invoice = $invoice;
		$this->description = $description;
		$this->date = new \DateTime;
	}


	public function getInvoice(): InvoiceCore
	{
		return $this->invoice;
	}


	public function setInvoice(InvoiceCore $invoice): void
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
