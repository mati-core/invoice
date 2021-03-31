<?php

declare(strict_types=1);


namespace MatiCore\Company;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use Nette\SmartObject;

/**
 * Class CompanyContact
 * @package MatiCore\Company
 * @ORM\Entity()
 * @ORM\Table(name="company__company_contact")
 */
class CompanyContact
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var Company
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\Company", inversedBy="contacts")
	 */
	private Company $company;

	/**
	 * @var CompanyStock|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\CompanyStock", inversedBy="contacts")
	 */
	private CompanyStock|null $companyStock;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $role;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $firstName;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $lastName;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $email;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $phone;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $mobilePhone;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	private string|null $note;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $sendInvoice = false;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $sendOffer = false;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $sendOrder = false;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $sendMarketing = true;

	/**
	 * CompanyContact constructor.
	 * @param Company $company
	 * @param string $lastName
	 */
	public function __construct(Company $company, string $lastName)
	{
		$this->company = $company;
		$this->lastName = $lastName;
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
	 * @return string
	 */
	public function getName(): string
	{
		return $this->getFirstName() . '&nbsp;' . $this->getLastName();
	}

	/**
	 * @return string|null
	 */
	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	/**
	 * @param string|null $firstName
	 */
	public function setFirstName(?string $firstName): void
	{
		$this->firstName = $firstName;
	}

	/**
	 * @return string
	 */
	public function getLastName(): string
	{
		return $this->lastName;
	}

	/**
	 * @param string $lastName
	 */
	public function setLastName(string $lastName): void
	{
		$this->lastName = $lastName;
	}

	/**
	 * @return string|null
	 */
	public function getNote(): ?string
	{
		return $this->note;
	}

	/**
	 * @param string|null $note
	 */
	public function setNote(?string $note): void
	{
		$this->note = $note;
	}

	/**
	 * @return bool
	 */
	public function isSendInvoice(): bool
	{
		return $this->sendInvoice;
	}

	/**
	 * @param bool $sendInvoice
	 */
	public function setSendInvoice(bool $sendInvoice): void
	{
		$this->sendInvoice = $sendInvoice;
	}

	/**
	 * @return bool
	 */
	public function isSendOffer(): bool
	{
		return $this->sendOffer;
	}

	/**
	 * @param bool $sendOffer
	 */
	public function setSendOffer(bool $sendOffer): void
	{
		$this->sendOffer = $sendOffer;
	}

	/**
	 * @return bool
	 */
	public function isSendOrder(): bool
	{
		return $this->sendOrder;
	}

	/**
	 * @param bool $sendOrder
	 */
	public function setSendOrder(bool $sendOrder): void
	{
		$this->sendOrder = $sendOrder;
	}

	/**
	 * @return string
	 */
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

	/**
	 * @return string|null
	 */
	public function getEmail(): ?string
	{
		return $this->email;
	}

	/**
	 * @param string|null $email
	 */
	public function setEmail(?string $email): void
	{
		$this->email = $email;
	}

	/**
	 * @param bool $formatted
	 * @return string|null
	 */
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

	/**
	 * @param string|null $phone
	 */
	public function setPhone(?string $phone): void
	{
		$this->phone = $phone;
	}

	/**
	 * @param bool $formatted
	 * @return string|null
	 */
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

	/**
	 * @param string|null $mobilePhone
	 */
	public function setMobilePhone(?string $mobilePhone): void
	{
		$this->mobilePhone = $mobilePhone;
	}

	/**
	 * @return string|null
	 */
	public function getRole(): ?string
	{
		return $this->role;
	}

	/**
	 * @param string|null $role
	 */
	public function setRole(?string $role): void
	{
		$this->role = $role;
	}

	/**
	 * @return CompanyStock|null
	 */
	public function getCompanyStock(): ?CompanyStock
	{
		return $this->companyStock;
	}

	/**
	 * @param CompanyStock|null $companyStock
	 */
	public function setCompanyStock(?CompanyStock $companyStock): void
	{
		$this->companyStock = $companyStock;
	}

	/**
	 * @return bool
	 */
	public function isSendMarketing(): bool
	{
		return $this->sendMarketing ?? true;
	}

	/**
	 * @param bool $sendMarketing
	 */
	public function setSendMarketing(bool $sendMarketing): void
	{
		$this->sendMarketing = $sendMarketing;
	}

}