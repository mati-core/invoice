<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Address\Entity\Address;
use Nette\SmartObject;

/**
 * @ORM\Entity()
 * @ORM\Table(name="company__company_stock")
 */
class CompanyStock
{
	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var Company
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\Company", inversedBy="stocks")
	 */
	private Company $company;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $name;

	/**
	 * @var Address
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Address")
	 * @ORM\JoinColumn(name="address_id", referencedColumnName="id")
	 */
	private Address $address;

	/**
	 * @var CompanyContact[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Company\CompanyContact", mappedBy="companyStock")
	 */
	private array|Collection $contacts;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	private string|null $note;


	/**
	 * CompanyStock constructor.
	 *
	 * @param Company $company
	 * @param string $name
	 * @param Address $address
	 */
	public function __construct(Company $company, string $name, Address $address)
	{
		$this->company = $company;
		$this->name = $name;
		$this->address = $address;
	}


	/**
	 * @return Company
	 */
	public function getCompany(): Company
	{
		return $this->company;
	}


	/**
	 * @param Company $company
	 */
	public function setCompany(Company $company): void
	{
		$this->company = $company;
	}


	/**
	 * @return Address
	 */
	public function getAddress(): Address
	{
		return $this->address;
	}


	/**
	 * @param Address $address
	 */
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

}
