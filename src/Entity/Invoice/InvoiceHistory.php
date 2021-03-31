<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\User\BaseUser;
use Nette\SmartObject;
use Nette\Utils\DateTime;

/**
 * Class InvoiceHistory
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice_history")
 */
class InvoiceHistory
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var InvoiceCore
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="history")
	 */
	private InvoiceCore $invoice;

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
	 * InvoiceHistory constructor.
	 * @param InvoiceCore $invoice
	 * @param string $description
	 * @throws \Exception
	 */
	public function __construct(InvoiceCore $invoice, string $description)
	{
		$this->invoice = $invoice;
		$this->description = $description;
		$this->date = DateTime::from('NOW');
	}

	/**
	 * @return InvoiceCore
	 */
	public function getInvoice(): InvoiceCore
	{
		return $this->invoice;
	}

	/**
	 * @param InvoiceCore $invoice
	 */
	public function setInvoice(InvoiceCore $invoice): void
	{
		$this->invoice = $invoice;
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
	 * @param string $description
	 */
	public function setDescription(string $description): void
	{
		$this->description = $description;
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

}