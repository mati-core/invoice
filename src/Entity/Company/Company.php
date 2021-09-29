<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Address\Entity\Address;
use MatiCore\Currency\Currency;
use Nette\SmartObject;

/**
 * @ORM\Entity()
 * @ORM\Table(name="company__company")
 */
class Company
{
	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $name;

	/**
	 * @var CompanyStock[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Company\CompanyStock", mappedBy="company")
	 * @ORM\OrderBy({"name"="ASC"})
	 */
	private array|Collection $stocks;

	/**
	 * @var Currency
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	private Currency $currency;

	/**
	 * @var Address
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Address")
	 * @ORM\JoinColumn(name="invoice_address_id", referencedColumnName="id")
	 */
	private Address $invoiceAddress;

	/**
	 * @var CompanyContact[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Company\CompanyContact", mappedBy="company")
	 * @ORM\OrderBy({"lastName"="ASC","firstName"="ASC"})
	 */
	private array|Collection $contacts;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $sendInvoicesInOneFile = false;

	/**
	 * @var int
	 * @ORM\Column(type="integer")
	 */
	private int $invoiceDueDayCount = 14;

	/**
	 * @var string
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string $note;

	/**
	 * @var string
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string $type = CompanyType::STANDARD;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $blackList = false;


	/**
	 * Company constructor.
	 *
	 * @param Address $invoiceAddress
	 * @param Currency $currency
	 */
	public function __construct(Address $invoiceAddress, Currency $currency)
	{
		$this->currency = $currency;
		$this->invoiceAddress = $invoiceAddress;
		$this->name = $invoiceAddress->getCompanyName() ?? $invoiceAddress->getName();
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function setName(string $name): void
	{
		$this->name = $name;
	}


	/**
	 * @return Collection|CompanyStock[]
	 */
	public function getStocks(): array|Collection
	{
		return $this->stocks;
	}


	/**
	 * @param Collection|CompanyStock[] $stocks
	 */
	public function setStocks(array|Collection $stocks): void
	{
		$this->stocks = $stocks;
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


	/**
	 * @return Address
	 */
	public function getInvoiceAddress(): Address
	{
		return $this->invoiceAddress;
	}


	/**
	 * @param Address $invoiceAddress
	 */
	public function setInvoiceAddress(Address $invoiceAddress): void
	{
		$this->invoiceAddress = $invoiceAddress;
		$this->name = $invoiceAddress->getCompanyName() ?? $invoiceAddress->getName();
	}


	/**
	 * @return Collection|CompanyContact[]
	 */
	public function getContacts(): array|Collection
	{
		return $this->contacts;
	}


	/**
	 * @param Collection|CompanyContact[] $contacts
	 */
	public function setContacts(array|Collection $contacts): void
	{
		$this->contacts = $contacts;
	}


	public function getContactCount(): int
	{
		return count($this->contacts);
	}


	public function getNote(): string
	{
		return $this->note;
	}


	public function setNote(string $note): void
	{
		$this->note = $note;
	}


	public function isBlackList(): bool
	{
		return $this->blackList;
	}


	public function setBlackList(bool $blackList): void
	{
		$this->blackList = $blackList;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function setType(string $type): void
	{
		$this->type = $type;
	}


	public function getInvoiceDueDayCount(): int
	{
		return $this->invoiceDueDayCount;
	}


	public function setInvoiceDueDayCount(int $invoiceDueDayCount): void
	{
		$this->invoiceDueDayCount = $invoiceDueDayCount;
	}


	public function isSendInvoicesInOneFile(): bool
	{
		return $this->sendInvoicesInOneFile;
	}


	public function setSendInvoicesInOneFile(bool $sendInvoicesInOneFile): void
	{
		$this->sendInvoicesInOneFile = $sendInvoicesInOneFile;
	}

}
