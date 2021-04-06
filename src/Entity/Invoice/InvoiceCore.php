<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Address\Entity\Country;
use MatiCore\Company\Company;
use MatiCore\Company\CompanyStock;
use MatiCore\Currency\Currency;
use MatiCore\User\BaseUser;
use Nette\SmartObject;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;

/**
 * Class Invoice
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__invoice")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discriminator", type="string")
 */
class InvoiceCore
{

	use SmartObject;
	use UuidIdentifier;

	public const PAY_METHOD_BANK = 'bank';
	public const PAY_METHOD_CASH = 'cash';
	public const PAY_METHOD_CARD = 'card';
	public const PAY_METHOD_DELIVERY = 'delivery';
	public const PAY_DEPOSIT = 'deposit';

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $status = InvoiceStatus::CREATED;

	/**
	 * Relace na spolecnost
	 *
	 * @var Company|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\Company")
	 * @ORM\JoinColumn(name="company_id", referencedColumnName="id", nullable=true)
	 */
	protected Company|null $company;

	/**
	 * Relace na pobocku spolecnosti
	 *
	 * @var CompanyStock|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\CompanyStock")
	 * @ORM\JoinColumn(name="company_stock_id", referencedColumnName="id", nullable=true)
	 */
	protected CompanyStock|null $companyStock;

	/**
	 * Cislo faktury
	 *
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	protected string $number;

	/**
	 * Cislo bankovniho uctu
	 *
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $bankAccount;

	/**
	 * Kod banky
	 *
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $bankCode;

	/**
	 * Nazev banky
	 *
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $bankName;

	/**
	 * IBAN
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $iban;

	/**
	 * SWIFT
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $swift;

	/**
	 * Variabilni symbol
	 *
	 * @var string
	 * @ORM\Column(type="string", unique=true)
	 */
	protected string $variableSymbol;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $companyName;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $companyAddress;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $companyCity;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $companyPostalCode;

	/**
	 * @var Country
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="company_country_id", referencedColumnName="id")
	 */
	protected Country $companyCountry;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $companyCin;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $companyTin;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $companyLogo;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $customerName;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $customerAddress;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $customerCity;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $customerPostalCode;

	/**
	 * @var Country
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="customer_country_id", referencedColumnName="id")
	 */
	protected Country $customerCountry;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $customerCin;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $customerTin;

	/**
	 * Cislo objednavky
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $orderNumber;

	/**
	 * Cislo najemni smlouvy
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $rentNumber;

	/**
	 * Cislo zakazky
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $contractNumber;

	/**
	 * @var InvoiceTax[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceTax", mappedBy="invoice")
	 */
	protected array|Collection $taxList;

	/**
	 * Celkova castka
	 *
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $totalPrice;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $totalTax;

	/**
	 * @var Currency
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	protected Currency $currency;

	/**
	 * Smenny kurz
	 *
	 * @var float
	 * @ORM\Column(type="float")
	 */
	protected float $rate = 1.0;

	/**
	 * Datum smenneho kurzu
	 *
	 * @var \DateTime
	 * @ORM\Column(type="date")
	 */
	protected \DateTime $rateDate;

	/**
	 * Datum vytvoreni
	 *
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	protected \DateTime $createDate;

	/**
	 * Datum posledni editace
	 *
	 * @var \DateTime
	 * @ORM\Column(type="datetime")
	 */
	protected \DateTime $editDate;

	/**
	 * Datum vystaveni
	 *
	 * @var \DateTime
	 * @ORM\Column(type="date")
	 */
	protected \DateTime $date;

	/**
	 * Datum splatnosti
	 *
	 * @var \DateTime
	 * @ORM\Column(type="date")
	 */
	protected \DateTime $dueDate;

	/**
	 * Datum zdanitelneho plneni
	 *
	 * @var \DateTime
	 * @ORM\Column(type="date")
	 */
	protected \DateTime $taxDate;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $payMethod = self::PAY_METHOD_BANK;

	/**
	 * Datum uhrazeni
	 *
	 * @var \DateTime|null
	 * @ORM\Column(type="date", nullable=true)
	 */
	protected \DateTime|null $payDate;

	/**
	 * Soubor
	 *
	 * @var string[]
	 * @ORM\Column(type="json_array")
	 */
	protected array $files = [];

	/**
	 * Obrazek podpisu
	 *
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $signImage;

	/**
	 * Dokoncena (zakazana editace)
	 *
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $closed = false;

	/**
	 * Autor faktury
	 *
	 * @var BaseUser
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="create_user_id", referencedColumnName="id")
	 */
	protected BaseUser $createUser;

	/**
	 * Autor posledni zmeny
	 *
	 * @var BaseUser
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="edit_user_id", referencedColumnName="id")
	 */
	protected BaseUser $editUser;

	/**
	 * Polozky faktury
	 *
	 * @var InvoiceItem[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceItem", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"position"="ASC"})
	 */
	protected array|Collection $items;

	/**
	 * @var InvoiceHistory[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceHistory", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"})
	 */
	protected array|Collection $history;

	/**
	 * @var InvoiceComment[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceComment", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"})
	 */
	protected array|Collection $comments;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	protected string|null $textBeforeItems;
	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	protected string|null $textAfterItems;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $submitted = false;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $acceptStatus1 = InvoiceStatus::WAITING;

	/**
	 * @var BaseUser|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_1_id", referencedColumnName="id", nullable=true)
	 */
	protected BaseUser|null $acceptStatus1User;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $acceptStatus1Description;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $acceptStatus2 = InvoiceStatus::WAITING;

	/**
	 * @var BaseUser|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_2_id", referencedColumnName="id", nullable=true)
	 */
	protected BaseUser|null $acceptStatus2User;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	protected string|null $acceptStatus2Description;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $deleted = false;

	/**
	 * @var string
	 * @ORM\Column(type="text", nullable=true)
	 */
	protected string $emails;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	protected string $payAlertStatus = InvoiceStatus::PAY_ALERT_NONE;

	/**
	 * @var InvoicePayDocument|null
	 * @ORM\OneToOne(targetEntity="\MatiCore\Invoice\InvoicePayDocument", inversedBy="invoice")
	 * @ORM\JoinColumn(name="pay_document_id", referencedColumnName="id", nullable=true)
	 */
	protected InvoicePayDocument|null $payDocument;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	protected bool $disableStatistics = false;

	/**
	 * Faktury, ktere pouzivaji tuto fakturu jako zalohu
	 *
	 * @var InvoiceCore[]|Collection|null
	 * @ORM\ManyToMany(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="depositInvoices", fetch="EXTRA_LAZY")
	 * @ORM\JoinTable(name="invoice__invoice_deposit")
	 */
	private array|Collection|null $depositingInvoices;

	/**
	 * Odecteni zalohy
	 *
	 * @var InvoiceCore[]|Collection
	 * @ORM\ManyToMany(targetEntity="\MatiCore\Invoice\InvoiceCore", mappedBy="depositingInvoices", fetch="EXTRA_LAZY")
	 */
	private array|Collection $depositInvoices;

	/**
	 * InvoiceCore constructor.
	 * @param string $number
	 */
	public function __construct(string $number)
	{
		$this->number = $number;
		$this->items = new ArrayCollection;
		$this->history = new ArrayCollection;
		$this->comments = new ArrayCollection;
		$this->taxList = new ArrayCollection;
		$this->depositInvoices = new ArrayCollection;
	}

	/**
	 * @return string
	 */
	public function getStatus(): string
	{
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus(string $status): void
	{
		$this->status = $status;
	}

	/**
	 * @return Company|null
	 */
	public function getCompany(): ?Company
	{
		return $this->company;
	}

	/**
	 * @param Company|null $company
	 */
	public function setCompany(?Company $company): void
	{
		$this->company = $company;
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
	 * @return string
	 */
	public function getBankAccount(): string
	{
		return $this->bankAccount;
	}

	/**
	 * @param string $bankAccount
	 */
	public function setBankAccount(string $bankAccount): void
	{
		$this->bankAccount = $bankAccount;
	}

	/**
	 * @return string
	 */
	public function getBankCode(): string
	{
		return $this->bankCode;
	}

	/**
	 * @param string $bankCode
	 */
	public function setBankCode(string $bankCode): void
	{
		$this->bankCode = $bankCode;
	}

	/**
	 * @return string
	 */
	public function getCompanyName(): string
	{
		return $this->companyName;
	}

	/**
	 * @param string $companyName
	 */
	public function setCompanyName(string $companyName): void
	{
		$this->companyName = $companyName;
	}

	/**
	 * @return string
	 */
	public function getCompanyAddress(): string
	{
		return $this->companyAddress;
	}

	/**
	 * @param string $companyAddress
	 */
	public function setCompanyAddress(string $companyAddress): void
	{
		$this->companyAddress = $companyAddress;
	}

	/**
	 * @return string
	 */
	public function getCompanyCity(): string
	{
		return $this->companyCity;
	}

	/**
	 * @param string $companyCity
	 */
	public function setCompanyCity(string $companyCity): void
	{
		$this->companyCity = $companyCity;
	}

	/**
	 * @return string
	 */
	public function getCompanyPostalCode(): string
	{
		return $this->companyPostalCode;
	}

	/**
	 * @param string $companyPostalCode
	 */
	public function setCompanyPostalCode(string $companyPostalCode): void
	{
		$this->companyPostalCode = $companyPostalCode;
	}

	/**
	 * @return Country
	 */
	public function getCompanyCountry(): Country
	{
		return $this->companyCountry;
	}

	/**
	 * @param Country $companyCountry
	 */
	public function setCompanyCountry(Country $companyCountry): void
	{
		$this->companyCountry = $companyCountry;
	}

	/**
	 * @return string|null
	 */
	public function getCompanyCin(): ?string
	{
		return $this->companyCin;
	}

	/**
	 * @param string|null $companyCin
	 */
	public function setCompanyCin(?string $companyCin): void
	{
		$this->companyCin = $companyCin;
	}

	/**
	 * @return string|null
	 */
	public function getCompanyTin(): ?string
	{
		return $this->companyTin;
	}

	/**
	 * @param string|null $companyTin
	 */
	public function setCompanyTin(?string $companyTin): void
	{
		$this->companyTin = $companyTin;
	}

	/**
	 * @return string|null
	 */
	public function getCompanyLogo(): ?string
	{
		return $this->companyLogo;
	}

	/**
	 * @param string|null $companyLogo
	 */
	public function setCompanyLogo(?string $companyLogo): void
	{
		$this->companyLogo = $companyLogo;
	}

	/**
	 * @return string
	 */
	public function getCustomerName(): string
	{
		return $this->customerName;
	}

	/**
	 * @param string $customerName
	 */
	public function setCustomerName(string $customerName): void
	{
		$this->customerName = $customerName;
	}

	/**
	 * @return string
	 */
	public function getCustomerAddress(): string
	{
		return $this->customerAddress;
	}

	/**
	 * @param string $customerAddress
	 */
	public function setCustomerAddress(string $customerAddress): void
	{
		$this->customerAddress = $customerAddress;
	}

	/**
	 * @return string
	 */
	public function getCustomerCity(): string
	{
		return $this->customerCity;
	}

	/**
	 * @param string $customerCity
	 */
	public function setCustomerCity(string $customerCity): void
	{
		$this->customerCity = $customerCity;
	}

	/**
	 * @return string
	 */
	public function getCustomerPostalCode(): string
	{
		return $this->customerPostalCode;
	}

	/**
	 * @param string $customerPostalCode
	 */
	public function setCustomerPostalCode(string $customerPostalCode): void
	{
		$this->customerPostalCode = $customerPostalCode;
	}

	/**
	 * @return Country
	 */
	public function getCustomerCountry(): Country
	{
		return $this->customerCountry;
	}

	/**
	 * @param Country $customerCountry
	 */
	public function setCustomerCountry(Country $customerCountry): void
	{
		$this->customerCountry = $customerCountry;
	}

	/**
	 * @return string|null
	 */
	public function getCustomerCin(): ?string
	{
		return $this->customerCin;
	}

	/**
	 * @param string|null $customerCin
	 */
	public function setCustomerCin(?string $customerCin): void
	{
		$this->customerCin = $customerCin;
	}

	/**
	 * @return string|null
	 */
	public function getCustomerTin(): ?string
	{
		return $this->customerTin;
	}

	/**
	 * @param string|null $customerTin
	 */
	public function setCustomerTin(?string $customerTin): void
	{
		$this->customerTin = $customerTin;
	}

	/**
	 * @return \DateTime
	 */
	public function getRateDate(): \DateTime
	{
		return $this->rateDate;
	}

	/**
	 * @param \DateTime $rateDate
	 */
	public function setRateDate(\DateTime $rateDate): void
	{
		$this->rateDate = $rateDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getCreateDate(): \DateTime
	{
		return $this->createDate;
	}

	/**
	 * @param \DateTime $createDate
	 */
	public function setCreateDate(\DateTime $createDate): void
	{
		$this->createDate = $createDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getEditDate(): \DateTime
	{
		return $this->editDate;
	}

	/**
	 * @param \DateTime $editDate
	 */
	public function setEditDate(\DateTime $editDate): void
	{
		$this->editDate = $editDate;
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

	/**
	 * @return \DateTime
	 */
	public function getTaxDate(): \DateTime
	{
		return $this->taxDate;
	}

	/**
	 * @param \DateTime $taxDate
	 */
	public function setTaxDate(\DateTime $taxDate): void
	{
		$this->taxDate = $taxDate;
	}

	/**
	 * @return string
	 */
	public function getPayMethod(): string
	{
		return $this->payMethod;
	}

	/**
	 * @param string $payMethod
	 */
	public function setPayMethod(string $payMethod): void
	{
		$this->payMethod = $payMethod;
	}

	/**
	 * @return string[]
	 */
	public function getFiles(): array
	{
		return $this->files;
	}

	/**
	 * @param string[] $files
	 */
	public function setFiles(array $files): void
	{
		$this->files = $files;
	}

	/**
	 * @param string $file
	 */
	public function addFile(string $file): void
	{
		$this->files[] = $file;
	}

	/**
	 * @param string|null $domain
	 * @return string|null
	 */
	public function getSignImage(string $domain = null): ?string
	{
		if ($domain !== null && $this->signImage !== null) {
			return $domain . $this->signImage;
		}

		return $this->signImage;
	}

	/**
	 * @param string|null $signImage
	 */
	public function setSignImage(?string $signImage): void
	{
		$this->signImage = $signImage;
	}

	/**
	 * @return bool
	 */
	public function isClosed(): bool
	{
		return $this->closed;
	}

	/**
	 * @param bool $closed
	 */
	public function setClosed(bool $closed): void
	{
		$this->closed = $closed;
	}

	/**
	 * @return BaseUser
	 */
	public function getEditUser(): BaseUser
	{
		return $this->editUser;
	}

	/**
	 * @param BaseUser $editUser
	 */
	public function setEditUser(BaseUser $editUser): void
	{
		$this->editUser = $editUser;
	}

	/**
	 * @param InvoiceItem $item
	 */
	public function addItem(InvoiceItem $item): void
	{
		$this->items[] = $item;
	}

	/**
	 * @param InvoiceItem $item
	 */
	public function removeItem(InvoiceItem $item): void
	{
		foreach ($this->items as $k => $v) {
			if ($v->getId() === $item->getId()) {
				unset($this->items[$k]);

				break;
			}
		}
	}

	/**
	 * @return InvoiceHistory[]|Collection
	 */
	public function getHistory(): array|Collection
	{
		return $this->history;
	}

	/**
	 * @param InvoiceHistory $history
	 */
	public function addHistory(InvoiceHistory $history): void
	{
		$this->history[] = $history;
	}

	/**
	 * @return InvoiceComment[]|Collection
	 */
	public function getComments(): array|Collection
	{
		return $this->comments;
	}

	/**
	 * @param InvoiceComment $comment
	 */
	public function addComments(InvoiceComment $comment): void
	{
		$this->comments[] = $comment;
	}

	/**
	 * @return string|null
	 */
	public function getTextBeforeItems(): ?string
	{
		return $this->textBeforeItems;
	}

	/**
	 * @param string|null $textBeforeItems
	 */
	public function setTextBeforeItems(?string $textBeforeItems): void
	{
		$this->textBeforeItems = $textBeforeItems;
	}

	/**
	 * @return string|null
	 */
	public function getTextAfterItems(): ?string
	{
		return $this->textAfterItems;
	}

	/**
	 * @param string|null $textAfterItems
	 */
	public function setTextAfterItems(?string $textAfterItems): void
	{
		$this->textAfterItems = $textAfterItems;
	}

	/**
	 * @return bool
	 */
	public function isProforma(): bool
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function isFix(): bool
	{
		return false;
	}

	/**
	 * @return BaseUser|null
	 */
	public function getAcceptStatus1User(): ?BaseUser
	{
		return $this->acceptStatus1User;
	}

	/**
	 * @param BaseUser|null $acceptStatus1User
	 */
	public function setAcceptStatus1User(?BaseUser $acceptStatus1User): void
	{
		$this->acceptStatus1User = $acceptStatus1User;
	}

	/**
	 * @return BaseUser|null
	 */
	public function getAcceptStatus2User(): ?BaseUser
	{
		return $this->acceptStatus2User;
	}

	/**
	 * @param BaseUser|null $acceptStatus2User
	 */
	public function setAcceptStatus2User(?BaseUser $acceptStatus2User): void
	{
		$this->acceptStatus2User = $acceptStatus2User;
	}

	/**
	 * @return string|null
	 */
	public function getAcceptStatus1Description(): ?string
	{
		return $this->acceptStatus1Description;
	}

	/**
	 * @param string|null $acceptStatus1Description
	 */
	public function setAcceptStatus1Description(?string $acceptStatus1Description): void
	{
		$this->acceptStatus1Description = $acceptStatus1Description;
	}

	/**
	 * @return string|null
	 */
	public function getAcceptStatus2Description(): ?string
	{
		return $this->acceptStatus2Description;
	}

	/**
	 * @param string|null $acceptStatus2Description
	 */
	public function setAcceptStatus2Description(?string $acceptStatus2Description): void
	{
		$this->acceptStatus2Description = $acceptStatus2Description;
	}

	/**
	 * @return bool
	 */
	public function isLate(): bool
	{
		return $this->isPaid() === false && $this->getPayDateDiff() > 0;
	}

	/**
	 * @return bool
	 */
	public function isPaid(): bool
	{
		return $this->getPayDate() !== null;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getPayDate(): ?\DateTime
	{
		return $this->payDate;
	}

	/**
	 * @param \DateTime|null $payDate
	 */
	public function setPayDate(?\DateTime $payDate): void
	{
		$this->payDate = $payDate;
	}

	/**
	 * @return int
	 * @throws \Exception
	 */
	public function getPayDateDiff(): int
	{
		if ($this->isPaid()) {
			$date = DateTime::from($this->getPayDate());
		} else {
			$date = DateTime::from('NOW');
		}
		$payDate = $this->getDueDate();

		if ($date->format('Y-m-d') === $payDate->format('Y-m-d')) {
			return 0;
		}

		$diff = $date->getTimestamp() - $payDate->getTimestamp();

		return (int) round($diff / 86400, 0);
	}

	/**
	 * @return \DateTime
	 */
	public function getDueDate(): \DateTime
	{
		return $this->dueDate;
	}

	/**
	 * @return string
	 */
	public function getDueDateFormatted(): string
	{
		$dueDate = $this->getDueDate();
		$date = $this->getDate();

		if($dueDate <= $date){
			return 'Ihned';
		}

		return $this->getDueDate()->format('d.m.Y');
	}

	/**
	 * @param \DateTime $dueDate
	 */
	public function setDueDate(\DateTime $dueDate): void
	{
		$this->dueDate = $dueDate;
	}

	/**
	 * @return InvoiceTax[]|Collection
	 */
	public function getTaxList(): array|Collection
	{
		return $this->taxList;
	}

	/**
	 * @param InvoiceTax $invoiceTax
	 */
	public function addTax(InvoiceTax $invoiceTax): void
	{
		$this->taxList[] = $invoiceTax;
	}

	/**
	 * @param InvoiceTax $invoiceTax
	 */
	public function removeTax(InvoiceTax $invoiceTax): void
	{
		foreach ($this->taxList as $key => $tax) {
			if ($tax->getId() === $invoiceTax->getId()) {
				unset($this->taxList[$key]);

				break;
			}
		}
	}

	public function clearTaxList(): void
	{
		$this->taxList = [];
	}

	/**
	 * @return InvoiceTax[]
	 */
	public function getTaxTable(): array
	{
		/** @var InvoiceTax[] $taxTable */
		$taxTable = [];

		foreach ($this->getItems() as $item) {
			if ($item->getVat() > 0) {
				if (isset($taxTable[md5((string) $item->getVat())])) {
					$it = $taxTable[md5((string) $item->getVat())];
					$price = $it->getPrice() + ($item->getTotalPrice() + $item->getSalePrice());
					$it->setPrice($price);
				} else {
					$taxTable[md5((string) $item->getVat())] = new InvoiceTax($this, $item->getVat(), ($item->getTotalPrice() + $item->getSalePrice()));
				}
			}
		}

		return $taxTable;
	}

	/**
	 * @return InvoiceItem[]|Collection
	 */
	public function getItems(): array|Collection
	{
		return $this->items;
	}

	/**
	 * @return float
	 */
	public function getRate(): float
	{
		return $this->rate;
	}

	/**
	 * @param float $rate
	 */
	public function setRate(float $rate): void
	{
		$this->rate = $rate;
	}

	/**
	 * @return string
	 */
	public function getAuthorName(): string
	{
		$user = $this->getCreateUser();
		$f = $user->getFirstName();
		$s = $user->getLastName();

		$str = ($f === null ?: $f[0] . '-');
		$str .= ($s[0] ?? '') . ($s[1] ?? '') . ($s[2] ?? '');

		return Strings::upper( $str );
	}

	/**
	 * @return BaseUser
	 */
	public function getCreateUser(): BaseUser
	{
		return $this->createUser;
	}

	/**
	 * @param BaseUser $createUser
	 */
	public function setCreateUser(BaseUser $createUser): void
	{
		$this->createUser = $createUser;
	}

	/**
	 * @return string|null
	 */
	public function getQRCode(): ?string
	{
		return base64_encode($this->generateQRCode());
	}

	/**
	 * @return string
	 */
	private function generateQRCode(): string
	{
		$renderer = new ImageRenderer(
			new RendererStyle(300),
			new SvgImageBackEnd()
		);
		$writer = new Writer($renderer);


		return $writer->writeString($this->getQRMessage(), 'UTF-8');
	}

	/**
	 * @return string
	 */
	private function getQRMessage(): string
	{
		return 'SPD*1.0'
			. '*ACC:' . str_replace(' ', '', $this->getIban())
			. '+' . str_replace(' ', '', $this->getSwift())
			. '*AM:' . $this->getTotalPrice()
			. '*CC:' . $this->getCurrency()->getCode()
			. '*X-VS:' . $this->getVariableSymbol()
			. '*MSG:QR platba faktura ' . $this->getNumber();
	}

	/**
	 * @return string|null
	 */
	public function getIban(): ?string
	{
		return $this->iban;
	}

	/**
	 * @param string|null $iban
	 */
	public function setIban(?string $iban): void
	{
		$this->iban = $iban;
	}

	/**
	 * @return string|null
	 */
	public function getSwift(): ?string
	{
		return $this->swift;
	}

	/**
	 * @param string|null $swift
	 */
	public function setSwift(?string $swift): void
	{
		$this->swift = $swift;
	}

	/**
	 * @return float
	 */
	public function getTotalPrice(): float
	{
		return $this->totalPrice;
	}

	/**
	 * @param float $totalPrice
	 */
	public function setTotalPrice(float $totalPrice): void
	{
		$this->totalPrice = $totalPrice;
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
	 * @return string
	 */
	public function getNumber(): string
	{
		return $this->number;
	}

	/**
	 * @param string $number
	 */
	public function setNumber(string $number): void
	{
		$this->number = $number;
	}

	/**
	 * @return string
	 */
	public function getVariableSymbol(): string
	{
		return $this->variableSymbol;
	}

	/**
	 * @param string $variableSymbol
	 */
	public function setVariableSymbol(string $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}


	/**
	 * @return InvoiceCore[]|Collection
	 */
	public function getDepositingInvoices(): array|Collection
	{
		return $this->depositingInvoices;
	}

	public function clearDepositingInvoices(): void
	{
		$this->depositingInvoices = new ArrayCollection;
	}

	/**
	 * @param InvoiceCore $invoice
	 */
	public function removeDepositingInvoice(InvoiceCore $invoice): void
	{
		foreach ($this->depositingInvoices as $key => $depositingInvoice) {
			if ($depositingInvoice->getId() === $invoice->getId()) {
				unset($this->depositingInvoices[$key]);

				return;
			}
		}
	}

	/**
	 * @param InvoiceCore|null $depositingInvoice
	 */
	public function addDepositingInvoice(?InvoiceCore $depositingInvoice): void
	{
		$this->depositingInvoices[] = $depositingInvoice;
	}

	public function clearDepositInvoices(): void
	{
		$this->depositInvoices = new ArrayCollection;
	}

	/**
	 * @param InvoiceCore $depositInvoice
	 */
	public function addDepositInvoice(InvoiceCore $depositInvoice): void
	{
		$this->depositInvoices[] = $depositInvoice;
	}

	/**
	 * @return float
	 */
	public function getTotalPriceDiff(): float
	{
		if ($this->getCurrency()->getCode() !== 'CZK') {
			return 0.0;
		}

		return round($this->getTotalPrice() - ($this->getItemTotalPrice() + $this->getTotalTax()), 2);
	}

	/**
	 * @return float
	 */
	public function getItemTotalPrice(): float
	{
		$totalPrice = 0;

		foreach ($this->getItems() as $item) {
			$totalPrice += ($item->getTotalPrice() + $item->getSalePrice());
		}

		return $totalPrice;
	}

	/**
	 * @return float
	 */
	public function getTotalTax(): float
	{
		return $this->totalTax;
	}

	/**
	 * @return float
	 */
	public function getTotalTaxCZK(): float
	{
		return $this->getTotalTax() * $this->getRate();
	}

	/**
	 * @param float $totalTax
	 */
	public function setTotalTax(float $totalTax): void
	{
		$this->totalTax = $totalTax;
	}

	/**
	 * @return InvoiceCore[]|Collection
	 */
	public function getDepositInvoices(): array|Collection
	{
		return $this->depositInvoices;
	}

	/**
	 * @return float
	 */
	public function getTotalPriceWithoutTax(): float
	{
		return $this->getItemTotalPrice();
	}

	/**
	 * @return float
	 */
	public function getTotalPriceWithoutTaxCZK(): float
	{
		return $this->getTotalPriceWithoutTax() * $this->getRate();
	}

	/**
	 * @return string
	 */
	public function getBankName(): string
	{
		return $this->bankName;
	}

	/**
	 * @param string $bankName
	 */
	public function setBankName(string $bankName): void
	{
		$this->bankName = $bankName;
	}

	/**
	 * @return string|null
	 */
	public function getOrderNumber(): ?string
	{
		return $this->orderNumber;
	}

	/**
	 * @param string|null $orderNumber
	 */
	public function setOrderNumber(?string $orderNumber): void
	{
		$this->orderNumber = $orderNumber;
	}

	/**
	 * @return string|null
	 */
	public function getRentNumber(): ?string
	{
		return $this->rentNumber;
	}

	/**
	 * @param string|null $rentNumber
	 */
	public function setRentNumber(?string $rentNumber): void
	{
		$this->rentNumber = $rentNumber;
	}

	/**
	 * @return string|null
	 */
	public function getContractNumber(): ?string
	{
		return $this->contractNumber;
	}

	/**
	 * @param string|null $contractNumber
	 */
	public function setContractNumber(?string $contractNumber): void
	{
		$this->contractNumber = $contractNumber;
	}

	/**
	 * @return bool
	 */
	public function isReady(): bool
	{
		return $this->isSubmitted() && $this->getAcceptStatus1() === InvoiceStatus::ACCEPTED && $this->getAcceptStatus2() === InvoiceStatus::ACCEPTED;
	}

	/**
	 * @return bool
	 */
	public function isSubmitted(): bool
	{
		return $this->submitted;
	}

	/**
	 * @param bool $submitted
	 */
	public function setSubmitted(bool $submitted): void
	{
		$this->submitted = $submitted;
	}

	/**
	 * @return string
	 */
	public function getAcceptStatus1(): string
	{
		return $this->acceptStatus1;
	}

	/**
	 * @param string $acceptStatus1
	 */
	public function setAcceptStatus1(string $acceptStatus1): void
	{
		$this->acceptStatus1 = $acceptStatus1;
	}

	/**
	 * @return string
	 */
	public function getAcceptStatus2(): string
	{
		return $this->acceptStatus2;
	}

	/**
	 * @param string $acceptStatus2
	 */
	public function setAcceptStatus2(string $acceptStatus2): void
	{
		$this->acceptStatus2 = $acceptStatus2;
	}

	/**
	 * @return bool
	 */
	public function isDeleted(): bool
	{
		return $this->deleted;
	}

	/**
	 * @param bool $deleted
	 */
	public function setDeleted(bool $deleted): void
	{
		$this->deleted = $deleted;
	}

	/**
	 * @param string $email
	 */
	public function addEmail(string $email): void
	{
		$emails = $this->getEmailList();

		if (!in_array($email, $emails, true)) {
			$emails[] = $email;
		}

		$this->setEmails(
			implode(';', $emails)
		);
	}

	/**
	 * @return string[]
	 */
	public function getEmailList(): array
	{
		$data = explode(';', $this->getEmails());
		$emails = [];

		foreach ($data as $email) {
			if (trim($email) !== '') {
				$emails[] = $email;
			}
		}

		return $emails;
	}

	/**
	 * @return string
	 */
	public function getEmails(): string
	{
		return $this->emails ?? '';
	}

	/**
	 * @param string $emails
	 */
	public function setEmails(string $emails): void
	{
		dump($this->emails);
		dumpe($emails);
		$this->emails = $emails;
	}

	/**
	 * @return string
	 */
	public function getPayAlertStatus(): string
	{
		return $this->payAlertStatus;
	}

	/**
	 * @param string $payAlertStatus
	 */
	public function setPayAlertStatus(string $payAlertStatus): void
	{
		$this->payAlertStatus = $payAlertStatus;
	}

	/**
	 * @return InvoicePayDocument|null
	 */
	public function getPayDocument(): ?InvoicePayDocument
	{
		return $this->payDocument;
	}

	/**
	 * @param InvoicePayDocument|null $payDocument
	 */
	public function setPayDocument(?InvoicePayDocument $payDocument): void
	{
		$this->payDocument = $payDocument;
	}

	/**
	 * @return bool
	 */
	public function isPayDocument(): bool
	{
		return false;
	}

	/**
	 * @return bool
	 */
	public function isDisableStatistics(): bool
	{
		return $this->disableStatistics;
	}

	/**
	 * @param bool $disableStatistics
	 */
	public function setDisableStatistics(bool $disableStatistics): void
	{
		$this->disableStatistics = $disableStatistics;
	}

}