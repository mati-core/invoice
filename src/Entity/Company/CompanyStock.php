<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Address\Entity\Address;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__company_stock')]
class CompanyStock
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Company::class)]
	private Company $company;

	#[ORM\Column(type: 'string')]
	private string $name;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $address;

	/** @var CompanyContact[]|Collection */
	#[ORM\OneToMany(mappedBy: 'companyStock', targetEntity: CompanyContact::class)]
	private array|Collection $contacts;

	#[ORM\Column(type: 'text', nullable: true)]
	private string|null $note = null;


	public function __construct(Company $company, string $name, Address $address)
	{
		$this->company = $company;
		$this->name = $name;
		$this->address = $address;
	}


	public function getCompany(): Company
	{
		return $this->company;
	}


	public function setCompany(Company $company): void
	{
		$this->company = $company;
	}


	public function getAddress(): Address
	{
		return $this->address;
	}


	public function setAddress(Address $address): void
	{
		$this->address = $address;
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


	public function getName(): string
	{
		return $this->name;
	}


	public function setName(string $name): void
	{
		$this->name = $name;
	}


	public function getNote(): ?string
	{
		return $this->note;
	}


	public function setNote(?string $note): void
	{
		$this->note = $note;
	}
}
