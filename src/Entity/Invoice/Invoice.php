<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Company\Company;
use MatiCore\Company\CompanyStock;
use Nette\Utils\Strings;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__invoice')]
class Invoice
{
	use IdentifierUnsigned;

	public const
		PAY_METHOD_BANK = 'bank',
		PAY_METHOD_CASH = 'cash',
		PAY_METHOD_CARD = 'card',
		PAY_METHOD_DELIVERY = 'delivery',
		PAY_DEPOSIT = 'deposit';

	public const
		TYPE_REGULAR = 'regular',
		TYPE_FIX = 'fix',
		TYPE_PAY_DOCUMENT = 'pay-document',
		TYPE_PROFORMA = 'proforma';

	public const
		STATUS_CREATED = 'created',
		STATUS_WAITING = 'waiting',
		STATUS_ACCEPTED = 'accepted',
		STATUS_DENIED = 'denied',
		STATUS_SENT = 'sent',
		STATUS_CANCELLED = 'cancelled',
		STATUS_PAID = 'paid',
		STATUS_PAY_ALERT_NONE = 'none',
		STATUS_PAY_ALERT_ONE = 'one',
		STATUS_PAY_ALERT_TWO = 'two',
		STATUS_PAY_ALERT_THREE = 'three';

	public const STATUS_LABELS = [
		self::STATUS_CREATED => 'Vytvořeno',
		self::STATUS_WAITING => 'Čeká na schválení',
		self::STATUS_ACCEPTED => 'Schváleno',
		self::STATUS_DENIED => 'Zamítnuto',
		self::STATUS_SENT => 'Odesláno',
		self::STATUS_CANCELLED => 'Storno',
		self::STATUS_PAID => 'Uhrazeno',
		self::STATUS_PAY_ALERT_ONE => '1. upomínka',
		self::STATUS_PAY_ALERT_TWO => '2. upomínka',
		self::STATUS_PAY_ALERT_THREE => '3. upomínka',
	];

	public const TYPES = [
		self::TYPE_REGULAR,
		self::TYPE_FIX,
		self::TYPE_PAY_DOCUMENT,
		self::TYPE_PROFORMA,
	];

	#[ORM\Column(type: 'string', length: 16)]
	private string $type;

	#[ORM\Column(type: 'string', length: 16)]
	private string $status = self::STATUS_CREATED;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $number;

	#[ORM\ManyToOne(targetEntity: self::class)]
	private ?self $parentInvoice = null;

	#[ORM\OneToMany(targetEntity: self::class)]
	private ?self $subInvoice = null;

	/**
	 * Relace na spolecnost
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\Company")
	 * @ORM\JoinColumn(name="company_id", referencedColumnName="id", nullable=true)
	 */
	private Company|null $company = null;

	/**
	 * Relace na pobocku spolecnosti
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\CompanyStock")
	 * @ORM\JoinColumn(name="company_stock_id", referencedColumnName="id", nullable=true)
	 */
	private CompanyStock|null $companyStock = null;

	/**
	 * Cislo bankovniho uctu
	 *
	 * @ORM\Column(type="string")
	 */
	private string $bankAccount;

	/**
	 * Kod banky
	 *
	 * @ORM\Column(type="string")
	 */
	private string $bankCode;

	/**
	 * Nazev banky
	 *
	 * @ORM\Column(type="string")
	 */
	private string $bankName;

	/**
	 * IBAN
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $iban;

	/**
	 * SWIFT
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $swift;

	/**
	 * Variabilni symbol
	 *
	 * @ORM\Column(type="string", unique=true)
	 */
	private string $variableSymbol;

	/** @ORM\Column(type="string") */
	private string $companyName;

	/** @ORM\Column(type="string") */
	private string $companyAddress;

	/** @ORM\Column(type="string") */
	private string $companyCity;

	/** @ORM\Column(type="string") */
	private string $companyPostalCode;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="company_country_id", referencedColumnName="id")
	 */
	private Country $companyCountry;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $companyCin;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $companyTin;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $companyLogo;

	/** @ORM\Column(type="string") */
	private string $customerName;

	/** @ORM\Column(type="string") */
	private string $customerAddress;

	/** @ORM\Column(type="string") */
	private string $customerCity;

	/** @ORM\Column(type="string") */
	private string $customerPostalCode;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="customer_country_id", referencedColumnName="id")
	 */
	private Country $customerCountry;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $customerCin;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $customerTin;

	/**
	 * Cislo objednavky
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $orderNumber;

	/**
	 * Cislo najemni smlouvy
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $rentNumber;

	/**
	 * Cislo zakazky
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $contractNumber;

	/**
	 * @var InvoiceTax[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceTax", mappedBy="invoice")
	 */
	private array|Collection $taxList;

	/** @ORM\Column(type="boolean") */
	private bool $taxEnabled = false;

	/**
	 * Celkova castka
	 *
	 * @ORM\Column(type="float")
	 */
	private float $totalPrice;

	/** @ORM\Column(type="float") */
	private float $totalTax;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	private Currency $currency;

	/**
	 * Smenny kurz
	 *
	 * @ORM\Column(type="float")
	 */
	private float $rate = 1.0;

	/**
	 * Datum smenneho kurzu
	 *
	 * @ORM\Column(type="date")
	 */
	private \DateTime $rateDate;

	/**
	 * Datum vytvoreni
	 *
	 * @ORM\Column(type="datetime")
	 */
	private \DateTime $createDate;

	/**
	 * Datum posledni editace
	 *
	 * @ORM\Column(type="datetime")
	 */
	private \DateTime $editDate;

	/**
	 * Datum vystaveni
	 *
	 * @ORM\Column(type="date")
	 */
	private \DateTime $date;

	/**
	 * Datum splatnosti
	 *
	 * @ORM\Column(type="date")
	 */
	private \DateTime $dueDate;

	/**
	 * Datum zdanitelneho plneni
	 *
	 * @ORM\Column(type="date")
	 */
	private \DateTime $taxDate;

	/** @ORM\Column(type="string") */
	private string $payMethod = self::PAY_METHOD_BANK;

	/**
	 * Datum uhrazeni
	 *
	 * @ORM\Column(type="date", nullable=true)
	 */
	private \DateTime|null $payDate;

	/**
	 * Soubor
	 *
	 * @var string[]
	 * @ORM\Column(type="json")
	 */
	private array $files = [];

	/**
	 * Obrazek podpisu
	 *
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $signImage;

	/**
	 * Dokoncena (zakazana editace)
	 *
	 * @ORM\Column(type="boolean")
	 */
	private bool $closed = false;

	/**
	 * Autor faktury
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="create_user_id", referencedColumnName="id")
	 */
	private BaseUser $createUser;

	/**
	 * Autor posledni zmeny
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="edit_user_id", referencedColumnName="id")
	 */
	private BaseUser $editUser;

	/**
	 * Polozky faktury
	 *
	 * @var InvoiceItem[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceItem", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"position"="ASC"})
	 */
	private array|Collection $items;

	/**
	 * @var InvoiceHistory[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceHistory", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"})
	 */
	private array|Collection $history;

	/**
	 * @var InvoiceComment[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceComment", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"})
	 */
	private array|Collection $comments;

	/** @ORM\Column(type="text", nullable=true) */
	private string|null $textBeforeItems;

	/** @ORM\Column(type="text", nullable=true) */
	private string|null $textAfterItems;

	/** @ORM\Column(type="boolean") */
	private bool $submitted = false;

	/** @ORM\Column(type="string") */
	private string $acceptStatus1 = Invoice::STATUS_WAITING;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_1_id", referencedColumnName="id", nullable=true)
	 */
	private BaseUser|null $acceptStatus1User;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $acceptStatus1Description;

	/** @ORM\Column(type="string") */
	private string $acceptStatus2 = Invoice::STATUS_WAITING;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_2_id", referencedColumnName="id", nullable=true)
	 */
	private BaseUser|null $acceptStatus2User;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $acceptStatus2Description;

	/** @ORM\Column(type="boolean") */
	private bool $deleted = false;

	/** @ORM\Column(type="text", nullable=true) */
	private string|null $emails;

	/** @ORM\Column(type="string") */
	private string $payAlertStatus = Invoice::STATUS_PAY_ALERT_NONE;

	/** @ORM\Column(type="boolean") */
	private bool $disableStatistics = false;

	/**
	 * Faktury, ktere pouzivaji tuto fakturu jako zalohu
	 *
	 * @var Invoice[]|Collection|null
	 * @ORM\ManyToMany(targetEntity="Invoice", inversedBy="depositInvoices", fetch="EXTRA_LAZY")
	 * @ORM\JoinTable(name="invoice__invoice_deposit")
	 */
	private array|Collection|null $depositingInvoices;

	/**
	 * Odecteni zalohy
	 *
	 * @var Invoice[]|Collection
	 * @ORM\ManyToMany(targetEntity="Invoice", mappedBy="depositingInvoices", fetch="EXTRA_LAZY")
	 */
	private array|Collection $depositInvoices;


	public function __construct(string $number, string $type = self::TYPE_REGULAR)
	{
		$this->number = $number;
		$this->type = strtolower($type);
		$this->items = new ArrayCollection;
		$this->history = new ArrayCollection;
		$this->comments = new ArrayCollection;
		$this->taxList = new ArrayCollection;
		$this->depositInvoices = new ArrayCollection;
	}


	public function getType(): string
	{
		return $this->type;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function setStatus(string $status): void
	{
		$this->status = $status;
	}


	public function getCompany(): ?Company
	{
		return $this->company;
	}


	public function setCompany(?Company $company): void
	{
		$this->company = $company;
	}


	public function getCompanyStock(): ?CompanyStock
	{
		return $this->companyStock;
	}


	public function setCompanyStock(?CompanyStock $companyStock): void
	{
		$this->companyStock = $companyStock;
	}


	public function getBankAccount(): string
	{
		return $this->bankAccount;
	}


	public function setBankAccount(string $bankAccount): void
	{
		$this->bankAccount = $bankAccount;
	}


	public function getBankCode(): string
	{
		return $this->bankCode;
	}


	public function setBankCode(string $bankCode): void
	{
		$this->bankCode = $bankCode;
	}


	public function getCompanyName(): string
	{
		return $this->companyName;
	}


	public function setCompanyName(string $companyName): void
	{
		$this->companyName = $companyName;
	}


	public function getCompanyAddress(): string
	{
		return $this->companyAddress;
	}


	public function setCompanyAddress(string $companyAddress): void
	{
		$this->companyAddress = $companyAddress;
	}


	public function getCompanyCity(): string
	{
		return $this->companyCity;
	}


	public function setCompanyCity(string $companyCity): void
	{
		$this->companyCity = $companyCity;
	}


	public function getCompanyPostalCode(): string
	{
		return $this->companyPostalCode;
	}


	public function setCompanyPostalCode(string $companyPostalCode): void
	{
		$this->companyPostalCode = $companyPostalCode;
	}


	public function getCompanyCountry(): Country
	{
		return $this->companyCountry;
	}


	public function setCompanyCountry(Country $companyCountry): void
	{
		$this->companyCountry = $companyCountry;
	}


	public function getCompanyCin(): ?string
	{
		return $this->companyCin;
	}


	public function setCompanyCin(?string $companyCin): void
	{
		$this->companyCin = $companyCin;
	}


	public function getCompanyTin(): ?string
	{
		return $this->companyTin;
	}


	public function setCompanyTin(?string $companyTin): void
	{
		$this->companyTin = $companyTin;
	}


	public function getCompanyLogo(): ?string
	{
		return $this->companyLogo;
	}


	public function setCompanyLogo(?string $companyLogo): void
	{
		$this->companyLogo = $companyLogo;
	}


	public function getCustomerName(): string
	{
		return $this->customerName;
	}


	public function setCustomerName(string $customerName): void
	{
		$this->customerName = $customerName;
	}


	public function getCustomerAddress(): string
	{
		return $this->customerAddress;
	}


	public function setCustomerAddress(string $customerAddress): void
	{
		$this->customerAddress = $customerAddress;
	}


	public function getCustomerCity(): string
	{
		return $this->customerCity;
	}


	public function setCustomerCity(string $customerCity): void
	{
		$this->customerCity = $customerCity;
	}


	public function getCustomerPostalCode(): string
	{
		return $this->customerPostalCode;
	}


	public function setCustomerPostalCode(string $customerPostalCode): void
	{
		$this->customerPostalCode = $customerPostalCode;
	}


	public function getCustomerCountry(): Country
	{
		return $this->customerCountry;
	}


	public function setCustomerCountry(Country $customerCountry): void
	{
		$this->customerCountry = $customerCountry;
	}


	public function getCustomerCin(): ?string
	{
		return $this->customerCin;
	}


	public function setCustomerCin(?string $customerCin): void
	{
		$this->customerCin = $customerCin;
	}


	public function getCustomerTin(): ?string
	{
		return $this->customerTin;
	}


	public function setCustomerTin(?string $customerTin): void
	{
		$this->customerTin = $customerTin;
	}


	public function getRateDate(): \DateTime
	{
		return $this->rateDate;
	}


	public function setRateDate(\DateTime $rateDate): void
	{
		$this->rateDate = $rateDate;
	}


	public function getCreateDate(): \DateTime
	{
		return $this->createDate;
	}


	public function setCreateDate(\DateTime $createDate): void
	{
		$this->createDate = $createDate;
	}


	public function getEditDate(): \DateTime
	{
		return $this->editDate;
	}


	public function setEditDate(\DateTime $editDate): void
	{
		$this->editDate = $editDate;
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}


	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}


	public function getTaxDate(): \DateTime
	{
		return $this->taxDate;
	}


	public function setTaxDate(\DateTime $taxDate): void
	{
		$this->taxDate = $taxDate;
	}


	public function getPayMethod(): string
	{
		return $this->payMethod;
	}


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


	public function addFile(string $file): void
	{
		$this->files[] = $file;
	}


	public function getSignImage(string $domain = null): ?string
	{
		if ($domain !== null && $this->signImage !== null) {
			return $domain . $this->signImage;
		}

		return $this->signImage;
	}


	public function setSignImage(?string $signImage): void
	{
		$this->signImage = $signImage;
	}


	public function isClosed(): bool
	{
		return $this->closed;
	}


	public function setClosed(bool $closed): void
	{
		$this->closed = $closed;
	}


	public function getEditUser(): BaseUser
	{
		return $this->editUser;
	}


	public function setEditUser(BaseUser $editUser): void
	{
		$this->editUser = $editUser;
	}


	public function addItem(InvoiceItem $item): void
	{
		$this->items[] = $item;
	}


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


	public function addComments(InvoiceComment $comment): void
	{
		$this->comments[] = $comment;
	}


	public function getTextBeforeItems(): ?string
	{
		return $this->textBeforeItems;
	}


	public function setTextBeforeItems(?string $textBeforeItems): void
	{
		$this->textBeforeItems = $textBeforeItems;
	}


	public function getTextAfterItems(): ?string
	{
		return $this->textAfterItems;
	}


	public function setTextAfterItems(?string $textAfterItems): void
	{
		$this->textAfterItems = $textAfterItems;
	}


	public function isProforma(): bool
	{
		return $this->type === self::TYPE_PROFORMA;
	}


	public function isRegular(): bool
	{
		return $this->type === self::TYPE_REGULAR;
	}


	public function isFix(): bool
	{
		return $this->type === self::TYPE_FIX;
	}


	public function getAcceptStatus1User(): ?BaseUser
	{
		return $this->acceptStatus1User;
	}


	public function setAcceptStatus1User(?BaseUser $acceptStatus1User): void
	{
		$this->acceptStatus1User = $acceptStatus1User;
	}


	public function getAcceptStatus2User(): ?BaseUser
	{
		return $this->acceptStatus2User;
	}


	public function setAcceptStatus2User(?BaseUser $acceptStatus2User): void
	{
		$this->acceptStatus2User = $acceptStatus2User;
	}


	public function getAcceptStatus1Description(): ?string
	{
		return $this->acceptStatus1Description;
	}


	public function setAcceptStatus1Description(?string $acceptStatus1Description): void
	{
		$this->acceptStatus1Description = $acceptStatus1Description;
	}


	public function getAcceptStatus2Description(): ?string
	{
		return $this->acceptStatus2Description;
	}


	public function setAcceptStatus2Description(?string $acceptStatus2Description): void
	{
		$this->acceptStatus2Description = $acceptStatus2Description;
	}


	public function isLate(): bool
	{
		return $this->isPaid() === false && $this->getPayDateDiff() > 0;
	}


	public function isPaid(): bool
	{
		return $this->getPayDate() !== null;
	}


	public function getPayDate(): ?\DateTime
	{
		return $this->payDate;
	}


	public function setPayDate(?\DateTime $payDate): void
	{
		$this->payDate = $payDate;
	}


	/**
	 * @throws \Exception
	 */
	public function getPayDateDiff(): int
	{
		if ($this->isPaid()) {
			$date = new \DateTime($this->getPayDate());
		} else {
			$date = new \DateTime;
		}
		$payDate = $this->getDueDate();
		if ($date->format('Y-m-d') === $payDate->format('Y-m-d')) {
			return 0;
		}

		$diff = $date->getTimestamp() - $payDate->getTimestamp();

		return (int) round($diff / 86400, 0);
	}


	public function getDueDate(): \DateTime
	{
		return $this->dueDate;
	}


	public function setDueDate(\DateTime $dueDate): void
	{
		$this->dueDate = $dueDate;
	}


	public function getDueDateFormatted(): string
	{
		$dueDate = $this->getDueDate();
		$date = $this->getDate();

		if ($dueDate <= $date) {
			return 'Ihned';
		}

		return $this->getDueDate()->format('d.m.Y');
	}


	/**
	 * @return InvoiceTax[]|Collection
	 */
	public function getTaxList(): array|Collection
	{
		return $this->taxList;
	}


	public function addTax(InvoiceTax $invoiceTax): void
	{
		$this->taxList[] = $invoiceTax;
	}


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
					$taxTable[md5((string) $item->getVat())] = new InvoiceTax(
						$this, $item->getVat(), ($item->getTotalPrice() + $item->getSalePrice())
					);
				}
			}
		}

		return $taxTable;
	}


	public function isTaxEnabled(): bool
	{
		return $this->taxEnabled;
	}


	public function setTaxEnabled(bool $taxEnabled): void
	{
		$this->taxEnabled = $taxEnabled;
	}


	/**
	 * @return InvoiceItem[]|Collection
	 */
	public function getItems(): array|Collection
	{
		return $this->items;
	}


	public function getRate(): float
	{
		return $this->rate;
	}


	public function setRate(float $rate): void
	{
		$this->rate = $rate;
	}


	public function getAuthorName(): string
	{
		$user = $this->getCreateUser();
		$f = $user->getFirstName();
		$s = $user->getLastName();

		$str = ($f === null ?: $f[0] . '-');
		$str .= ($s[0] ?? '') . ($s[1] ?? '') . ($s[2] ?? '');

		return Strings::upper($str);
	}


	public function getCreateUser(): BaseUser
	{
		return $this->createUser;
	}


	public function setCreateUser(BaseUser $createUser): void
	{
		$this->createUser = $createUser;
	}


	public function getQRCode(): ?string
	{
		return base64_encode($this->generateQRCode());
	}


	public function getIban(): ?string
	{
		return $this->iban;
	}


	public function setIban(?string $iban): void
	{
		$this->iban = $iban;
	}


	public function getSwift(): ?string
	{
		return $this->swift;
	}


	public function setSwift(?string $swift): void
	{
		$this->swift = $swift;
	}


	public function getTotalPrice(): float
	{
		return $this->totalPrice;
	}


	public function setTotalPrice(float $totalPrice): void
	{
		$this->totalPrice = $totalPrice;
	}


	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	public function setCurrency(Currency $currency): void
	{
		$this->currency = $currency;
	}


	public function getNumber(): string
	{
		return $this->number;
	}


	public function setNumber(string $number): void
	{
		$this->number = $number;
	}


	public function getVariableSymbol(): string
	{
		return $this->variableSymbol;
	}


	public function setVariableSymbol(string $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}


	/**
	 * @return Invoice[]|Collection
	 */
	public function getDepositingInvoices(): array|Collection
	{
		return $this->depositingInvoices;
	}


	public function clearDepositingInvoices(): void
	{
		$this->depositingInvoices = new ArrayCollection;
	}


	public function removeDepositingInvoice(Invoice $invoice): void
	{
		foreach ($this->depositingInvoices as $key => $depositingInvoice) {
			if ($depositingInvoice->getId() === $invoice->getId()) {
				unset($this->depositingInvoices[$key]);

				return;
			}
		}
	}


	public function addDepositingInvoice(?Invoice $depositingInvoice): void
	{
		$this->depositingInvoices[] = $depositingInvoice;
	}


	public function clearDepositInvoices(): void
	{
		$this->depositInvoices = new ArrayCollection;
	}


	public function addDepositInvoice(Invoice $depositInvoice): void
	{
		$this->depositInvoices[] = $depositInvoice;
	}


	public function getTotalPriceDiff(): float
	{
		if ($this->getCurrency()->getCode() !== 'CZK') {
			return 0.0;
		}

		return round($this->getTotalPrice() - ($this->getItemTotalPrice() + $this->getTotalTax()), 2);
	}


	public function getItemTotalPrice(): float
	{
		$totalPrice = 0;
		foreach ($this->getItems() as $item) {
			$totalPrice += ($item->getTotalPrice() + $item->getSalePrice());
		}

		if ($this->isFix()) {
			foreach ($this->getDepositInvoices() as $depositInvoice) {
				$totalPrice += $depositInvoice->getItemTotalPrice();
			}
		}
		if ($this->isRegular()) {
			foreach ($this->getDepositInvoices() as $depositInvoice) {
				$payDocument = $depositInvoice->getParentInvoice();
				if ($payDocument !== null && $depositInvoice->isProforma()) {
					$totalPrice -= $payDocument->getItemTotalPrice();
				} else {
					$totalPrice -= $depositInvoice->getItemTotalPrice();
				}
			}
		}

		return $totalPrice;
	}


	public function getTotalTax(): float
	{
		return $this->totalTax;
	}


	public function setTotalTax(float $totalTax): void
	{
		$this->totalTax = $totalTax;
	}


	public function getTotalTaxCZK(): float
	{
		if ($this->isFix()) {
			$return = 0.0;
			foreach ($this->getTaxList() as $invoiceTax) {
				$return += ($invoiceTax->getTaxPrice() * $this->getRate());
			}
			foreach ($this->getDepositInvoices() as $depositInvoice) {
				$return -= $depositInvoice->getTotalTax() * $depositInvoice->getRate();
			}
		} else {
			$return = $this->getTotalTax() * $this->getRate();
		}

		return $return;
	}


	/**
	 * @return Invoice[]|Collection
	 */
	public function getDepositInvoices(): array|Collection
	{
		return $this->depositInvoices;
	}


	public function getTotalPriceWithoutTax(): float
	{
		return $this->getItemTotalPrice();
	}


	public function getTotalPriceWithoutTaxCZK(): float
	{
		$totalPrice = $this->getTotalPriceWithoutTax() * $this->getRate();
		if ($this->isFix()) {
			foreach ($this->getDepositInvoices() as $depositInvoice) {
				$totalPrice -= $depositInvoice->getItemTotalPrice() * $depositInvoice->getRate();
			}
		}

		return $totalPrice;
	}


	public function getBankName(): string
	{
		return $this->bankName;
	}


	public function setBankName(string $bankName): void
	{
		$this->bankName = $bankName;
	}


	public function getOrderNumber(): ?string
	{
		return $this->orderNumber;
	}


	public function setOrderNumber(?string $orderNumber): void
	{
		$this->orderNumber = $orderNumber;
	}


	public function getRentNumber(): ?string
	{
		return $this->rentNumber;
	}


	public function setRentNumber(?string $rentNumber): void
	{
		$this->rentNumber = $rentNumber;
	}


	public function getContractNumber(): ?string
	{
		return $this->contractNumber;
	}


	public function setContractNumber(?string $contractNumber): void
	{
		$this->contractNumber = $contractNumber;
	}


	public function isReady(): bool
	{
		return $this->isSubmitted()
			&& $this->getAcceptStatus1() === Invoice::STATUS_ACCEPTED
			&& $this->getAcceptStatus2() === Invoice::STATUS_ACCEPTED;
	}


	public function isSubmitted(): bool
	{
		return $this->submitted;
	}


	public function setSubmitted(bool $submitted): void
	{
		$this->submitted = $submitted;
	}


	public function getAcceptStatus1(): string
	{
		return $this->acceptStatus1;
	}


	public function setAcceptStatus1(string $acceptStatus1): void
	{
		$this->acceptStatus1 = $acceptStatus1;
	}


	public function getAcceptStatus2(): string
	{
		return $this->acceptStatus2;
	}


	public function setAcceptStatus2(string $acceptStatus2): void
	{
		$this->acceptStatus2 = $acceptStatus2;
	}


	public function isDeleted(): bool
	{
		return $this->deleted;
	}


	public function setDeleted(bool $deleted): void
	{
		$this->deleted = $deleted;
	}


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


	public function getEmails(): string
	{
		return $this->emails ?? '';
	}


	public function setEmails(?string $emails): void
	{
		$this->emails = $emails;
	}


	public function getPayAlertStatus(): string
	{
		return $this->payAlertStatus;
	}


	public function setPayAlertStatus(string $payAlertStatus): void
	{
		$this->payAlertStatus = $payAlertStatus;
	}


	public function getParentInvoice(): ?self
	{
		return $this->parentInvoice;
	}


	public function setParentInvoice(?self $parentInvoice): void
	{
		if ($parentInvoice !== null && $parentInvoice->isPayDocument() === false) {
			throw new \InvalidArgumentException(
				'Given invoice "' . $parentInvoice->getNumber() . '" is not pay document, '
				. 'because "' . $parentInvoice->getType() . '" given.',
			);
		}
		$this->parentInvoice = $parentInvoice;
	}


	public function isPayDocument(): bool
	{
		return $this->type === self::TYPE_PAY_DOCUMENT;
	}


	public function isDisableStatistics(): bool
	{
		return $this->disableStatistics;
	}


	public function setDisableStatistics(bool $disableStatistics): void
	{
		$this->disableStatistics = $disableStatistics;
	}


	public function getSubInvoice(): ?self
	{
		return $this->subInvoice;
	}


	public function setSubInvoice(?self $subInvoice): void
	{
		$this->subInvoice = $subInvoice;
	}


	public function getProforma(): ?self
	{
		return $this->getSubInvoice();
	}


	public function setProforma(?self $invoice): void
	{
		$this->checkType($invoice, self::TYPE_PROFORMA);
		$this->subInvoice = $invoice;
	}


	public function getFixInvoice(): ?Invoice
	{
		return $this->getSubInvoice();
	}


	public function setFixInvoice(?self $invoice): void
	{
		$this->checkType($invoice, self::TYPE_FIX);
		$this->subInvoice = $invoice;
	}


	public function getColor(): string
	{
		$list = [
			self::STATUS_CREATED => 'text-info',
			self::STATUS_WAITING => 'text-warning',
			self::STATUS_ACCEPTED => 'text-success',
			self::STATUS_DENIED => 'text-danger',
			self::STATUS_SENT => 'text-info',
			self::STATUS_CANCELLED => 'test-danger',
			self::STATUS_PAID => 'text-success',
			self::STATUS_PAY_ALERT_ONE => 'text-warning',
			self::STATUS_PAY_ALERT_TWO => 'text-warning',
			self::STATUS_PAY_ALERT_THREE => 'text-danger',
		];

		return $list[$this->getStatus()] ?? 'Unknown';
	}


	public function getLabel(): string
	{
		return self::STATUS_LABELS[$this->getStatus()] ?? 'Unknown';
	}


	public function getPayDocument(): ?self
	{
		return $this->getSubInvoice();
	}


	private function checkType(?self $invoice, string $type, bool $canBeNull = true): void
	{
		if (in_array($type, self::TYPES, true) === false) {
			throw new \InvalidArgumentException(
				'Validation invoice type "' . $type . '" does not exist. '
				. 'Did you mean "' . implode('", "', self::TYPES) . '"?',
			);
		}
		if ($canBeNull && $invoice === null) {
			return;
		}
		if ($invoice === null) {
			throw new \InvalidArgumentException('Invoice is null.');
		}
		if ($invoice->getType() !== $type) {
			throw new \InvalidArgumentException(
				'Invoice type "' . $invoice->getType() . '" is invalid, '
				. 'because type "' . $type . '" expected.',
			);
		}
	}


	private function generateQRCode(): string
	{
		$renderer = new ImageRenderer(
			new RendererStyle(300),
			new SvgImageBackEnd()
		);
		$writer = new Writer($renderer);


		return $writer->writeString($this->getQRMessage(), 'UTF-8');
	}


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
}
