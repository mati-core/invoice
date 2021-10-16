<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__company')]
class Company
{
	use IdentifierUnsigned;

	public const
		TYPE_STANDARD = 'standard',
		TYPE_VIP = 'vip',
		TYPE_CONTRACT = 'contract';

	#[ORM\Column(type: 'string')]
	private string $name;

	/** @var CompanyStock[]|Collection */
	#[ORM\OneToMany(mappedBy: 'company', targetEntity: CompanyStock::class)]
	#[ORM\OrderBy((['name' => 'ASC']))]
	private array|Collection $stocks;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private Currency $currency;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $invoiceAddress;

	/** @var CompanyContact[]|Collection */
	#[ORM\OneToMany(mappedBy: 'company', targetEntity: CompanyContact::class)]
	private array|Collection $contacts;

	#[ORM\Column(type: 'boolean')]
	private bool $sendInvoicesInOneFile = false;

	#[ORM\Column(type: 'integer')]
	private int $invoiceDueDayCount = 14;

	#[ORM\Column(type: 'string', nullable: true)]
	private string $note;

	#[ORM\Column(type: 'string', nullable: true)]
	private string $type = CompanyType::STANDARD;

	#[ORM\Column(type: 'boolean')]
	private bool $blackList = false;


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


	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	public function setCurrency(Currency $currency): void
	{
		$this->currency = $currency;
	}


	public function getInvoiceAddress(): Address
	{
		return $this->invoiceAddress;
	}


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
