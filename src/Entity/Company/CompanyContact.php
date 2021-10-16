<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__company_contact')]
class CompanyContact
{
	use IdentifierUnsigned;

	#[ORM\ManyToOne(targetEntity: Company::class)]
	private Company $company;

	#[ORM\ManyToOne(targetEntity: CompanyStock::class)]
	private ?CompanyStock $companyStock = null;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $role = null;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $firstName = null;

	#[ORM\Column(type: 'string')]
	private string $lastName;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $email = null;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $phone = null;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $mobilePhone = null;

	#[ORM\Column(type: 'string', nullable: true)]
	private string|null $note = null;

	#[ORM\Column(type: 'boolean')]
	private bool $sendInvoice = false;

	#[ORM\Column(type: 'boolean')]
	private bool $sendOffer = false;

	#[ORM\Column(type: 'boolean')]
	private bool $sendOrder = false;

	#[ORM\Column(type: 'boolean')]
	private bool $sendMarketing = true;


	public function __construct(Company $company, string $lastName)
	{
		$this->company = $company;
		$this->lastName = $lastName;
	}


	public function getCompany(): Company
	{
		return $this->company;
	}


	public function setCompany(Company $company): void
	{
		$this->company = $company;
	}


	public function getName(): string
	{
		return $this->getFirstName() . '&nbsp;' . $this->getLastName();
	}


	public function getFirstName(): ?string
	{
		return $this->firstName;
	}


	public function setFirstName(?string $firstName): void
	{
		$this->firstName = $firstName;
	}


	public function getLastName(): string
	{
		return $this->lastName;
	}


	public function setLastName(string $lastName): void
	{
		$this->lastName = $lastName;
	}


	public function getNote(): ?string
	{
		return $this->note;
	}


	public function setNote(?string $note): void
	{
		$this->note = $note;
	}


	public function isSendInvoice(): bool
	{
		return $this->sendInvoice;
	}


	public function setSendInvoice(bool $sendInvoice): void
	{
		$this->sendInvoice = $sendInvoice;
	}


	public function isSendOffer(): bool
	{
		return $this->sendOffer;
	}


	public function setSendOffer(bool $sendOffer): void
	{
		$this->sendOffer = $sendOffer;
	}


	public function isSendOrder(): bool
	{
		return $this->sendOrder;
	}


	public function setSendOrder(bool $sendOrder): void
	{
		$this->sendOrder = $sendOrder;
	}


	public function getSearchString(): string
	{
		$ret = ($this->getFirstName() ?? '')
			. ', ' . $this->getLastName()
			. ', ' . ($this->getEmail() ?? '')
			. ', ' . ($this->getPhone() ?? '')
			. ', ' . ($this->getMobilePhone() ?? '')
			. ', ' . ($this->getRole() ?? '');

		if ($this->getCompanyStock() !== null) {
			$ret .= $this->getCompanyStock()->getName();
		}

		return $ret;
	}


	public function getEmail(): ?string
	{
		return $this->email;
	}


	public function setEmail(?string $email): void
	{
		$this->email = $email;
	}


	public function getPhone(bool $formatted = false): ?string
	{
		if ($formatted === true) {
			$str = '';
			$phone = str_replace(' ', '', $this->phone);
			$length = strlen($phone);
			for ($i = 0; $i < $length; $i++) {
				if ($i % 3 === 0 && $i !== 0) {
					$str .= '&nbsp;';
				}
				$str .= $phone[$i];
			}

			return $str;
		}

		return $this->phone;
	}


	public function setPhone(?string $phone): void
	{
		$this->phone = $phone;
	}


	public function getMobilePhone(bool $formatted = false): ?string
	{
		if ($formatted === true) {
			$str = '';
			$mobilePhone = str_replace(' ', '', $this->mobilePhone);
			$length = strlen($mobilePhone);
			for ($i = 0; $i < $length; $i++) {
				if ($i % 3 === 0 && $i !== 0) {
					$str .= '&nbsp;';
				}
				$str .= $mobilePhone[$i];
			}

			return $str;
		}

		return $this->mobilePhone;
	}


	public function setMobilePhone(?string $mobilePhone): void
	{
		$this->mobilePhone = $mobilePhone;
	}


	public function getRole(): ?string
	{
		return $this->role;
	}


	public function setRole(?string $role): void
	{
		$this->role = $role;
	}


	public function getCompanyStock(): ?CompanyStock
	{
		return $this->companyStock;
	}


	public function setCompanyStock(?CompanyStock $companyStock): void
	{
		$this->companyStock = $companyStock;
	}


	public function isSendMarketing(): bool
	{
		return $this->sendMarketing ?? true;
	}


	public function setSendMarketing(bool $sendMarketing): void
	{
		$this->sendMarketing = $sendMarketing;
	}
}
