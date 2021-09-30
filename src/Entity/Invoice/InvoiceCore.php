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

	/** @ORM\Column(type="string") */
	protected string $status = InvoiceStatus::CREATED;

	/**
	 * Relace na spolecnost
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\Company")
	 * @ORM\JoinColumn(name="company_id", referencedColumnName="id", nullable=true) */
	protected Company|null $company = null;

	/**
	 * Relace na pobocku spolecnosti
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Company\CompanyStock")
	 * @ORM\JoinColumn(name="company_stock_id", referencedColumnName="id", nullable=true) */
	protected CompanyStock|null $companyStock = null;

	/**
	 * Cislo faktury
	 *
	 * @ORM\Column(type="string", unique=true) */
	protected string $number;

	/**
	 * Cislo bankovniho uctu
	 *
	 * @ORM\Column(type="string") */
	protected string $bankAccount;

	/**
	 * Kod banky
	 *
	 * @ORM\Column(type="string") */
	protected string $bankCode;

	/**
	 * Nazev banky
	 *
	 * @ORM\Column(type="string") */
	protected string $bankName;

	/**
	 * IBAN
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $iban;

	/**
	 * SWIFT
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $swift;

	/**
	 * Variabilni symbol
	 *
	 * @ORM\Column(type="string", unique=true) */
	protected string $variableSymbol;

	/** @ORM\Column(type="string") */
	protected string $companyName;

	/** @ORM\Column(type="string") */
	protected string $companyAddress;

	/** @ORM\Column(type="string") */
	protected string $companyCity;

	/** @ORM\Column(type="string") */
	protected string $companyPostalCode;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="company_country_id", referencedColumnName="id") */
	protected Country $companyCountry;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $companyCin;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $companyTin;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $companyLogo;

	/** @ORM\Column(type="string") */
	protected string $customerName;

	/** @ORM\Column(type="string") */
	protected string $customerAddress;

	/** @ORM\Column(type="string") */
	protected string $customerCity;

	/** @ORM\Column(type="string") */
	protected string $customerPostalCode;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Country")
	 * @ORM\JoinColumn(name="customer_country_id", referencedColumnName="id") */
	protected Country $customerCountry;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $customerCin;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $customerTin;

	/**
	 * Cislo objednavky
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $orderNumber;

	/**
	 * Cislo najemni smlouvy
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $rentNumber;

	/**
	 * Cislo zakazky
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $contractNumber;

	/**
	 * @var InvoiceTax[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceTax", mappedBy="invoice") */
	protected array|Collection $taxList;

	/** @ORM\Column(type="boolean") */
	protected bool $taxEnabled = false;

	/**
	 * Celkova castka
	 *
	 * @ORM\Column(type="float") */
	protected float $totalPrice;

	/** @ORM\Column(type="float") */
	protected float $totalTax;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id") */
	protected Currency $currency;

	/**
	 * Smenny kurz
	 *
	 * @ORM\Column(type="float") */
	protected float $rate = 1.0;

	/**
	 * Datum smenneho kurzu
	 *
	 * @ORM\Column(type="date") */
	protected \DateTime $rateDate;

	/**
	 * Datum vytvoreni
	 *
	 * @ORM\Column(type="datetime") */
	protected \DateTime $createDate;

	/**
	 * Datum posledni editace
	 *
	 * @ORM\Column(type="datetime") */
	protected \DateTime $editDate;

	/**
	 * Datum vystaveni
	 *
	 * @ORM\Column(type="date") */
	protected \DateTime $date;

	/**
	 * Datum splatnosti
	 *
	 * @ORM\Column(type="date") */
	protected \DateTime $dueDate;

	/**
	 * Datum zdanitelneho plneni
	 *
	 * @ORM\Column(type="date") */
	protected \DateTime $taxDate;

	/** @ORM\Column(type="string") */
	protected string $payMethod = self::PAY_METHOD_BANK;

	/**
	 * Datum uhrazeni
	 *
	 * @ORM\Column(type="date", nullable=true) */
	protected \DateTime|null $payDate;

	/**
	 * Soubor
	 *
	 * @var string[]
	 * @ORM\Column(type="json") */
	protected array $files = [];

	/**
	 * Obrazek podpisu
	 *
	 * @ORM\Column(type="string", nullable=true) */
	protected string|null $signImage;

	/**
	 * Dokoncena (zakazana editace)
	 *
	 * @ORM\Column(type="boolean") */
	protected bool $closed = false;

	/**
	 * Autor faktury
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="create_user_id", referencedColumnName="id") */
	protected BaseUser $createUser;

	/**
	 * Autor posledni zmeny
	 *
	 * @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="edit_user_id", referencedColumnName="id") */
	protected BaseUser $editUser;

	/**
	 * Polozky faktury
	 *
	 * @var InvoiceItem[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceItem", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"position"="ASC"}) */
	protected array|Collection $items;

	/**
	 * @var InvoiceHistory[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceHistory", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"}) */
	protected array|Collection $history;

	/**
	 * @var InvoiceComment[]|Collection
	 * @ORM\OneToMany(targetEntity="\MatiCore\Invoice\InvoiceComment", mappedBy="invoice", fetch="EXTRA_LAZY")
	 * @ORM\OrderBy({"date"="DESC"}) */
	protected array|Collection $comments;

	/** @ORM\Column(type="text", nullable=true) */
	protected string|null $textBeforeItems;

	/** @ORM\Column(type="text", nullable=true) */
	protected string|null $textAfterItems;

	/** @ORM\Column(type="boolean") */
	protected bool $submitted = false;

	/** @ORM\Column(type="string") */
	protected string $acceptStatus1 = InvoiceStatus::WAITING;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_1_id", referencedColumnName="id", nullable=true) */
	protected BaseUser|null $acceptStatus1User;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $acceptStatus1Description;

	/** @ORM\Column(type="string") */
	protected string $acceptStatus2 = InvoiceStatus::WAITING;

	/** @ORM\ManyToOne(targetEntity="\MatiCore\User\BaseUser")
	 * @ORM\JoinColumn(name="accept_user_2_id", referencedColumnName="id", nullable=true) */
	protected BaseUser|null $acceptStatus2User;

	/** @ORM\Column(type="string", nullable=true) */
	protected string|null $acceptStatus2Description;

	/** @ORM\Column(type="boolean") */
	protected bool $deleted = false;

	/** @ORM\Column(type="text", nullable=true) */
	protected string|null $emails;

	/** @ORM\Column(type="string") */
	protected string $payAlertStatus = InvoiceStatus::PAY_ALERT_NONE;

	/** @ORM\OneToOne(targetEntity="\MatiCore\Invoice\InvoicePayDocument", inversedBy="invoice")
	 * @ORM\JoinColumn(name="pay_document_id", referencedColumnName="id", nullable=true) */
	protected InvoicePayDocument|null $payDocument;

	/** @ORM\Column(type="boolean") */
	protected bool $disableStatistics = false;

	/**
	 * Faktury, ktere pouzivaji tuto fakturu jako zalohu
	 *
	 * @var InvoiceCore[]|Collection|null
	 * @ORM\ManyToMany(targetEntity="\MatiCore\Invoice\InvoiceCore", inversedBy="depositInvoices", fetch="EXTRA_LAZY")
	 * @ORM\JoinTable(name="invoice__invoice_deposit") */
	private array|Collection|null $depositingInvoices;

	/**
	 * Odecteni zalohy
	 *
	 * @var InvoiceCore[]|Collection
	 * @ORM\ManyToMany(targetEntity="\MatiCore\Invoice\InvoiceCore", mappedBy="depositingInvoices", fetch="EXTRA_LAZY") */
	private array|Collection $depositInvoices;


	public function __construct(string $number)
	{
		$this->number = $number;
		$this->items = new ArrayCollection;
		$this->history = new ArrayCollection;
		$this->comments = new ArrayCollection;
		$this->taxList = new ArrayCollection;
		$this->depositInvoices = new ArrayCollection;
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
		return false;
	}


	public function isFix(): bool
	{
		return false;
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


	public function removeDepositingInvoice(InvoiceCore $invoice): void
	{
		foreach ($this->depositingInvoices as $key => $depositingInvoice) {
			if ($depositingInvoice->getId() === $invoice->getId()) {
				unset($this->depositingInvoices[$key]);

				return;
			}
		}
	}


	public function addDepositingInvoice(?InvoiceCore $depositingInvoice): void
	{
		$this->depositingInvoices[] = $depositingInvoice;
	}


	public function clearDepositInvoices(): void
	{
		$this->depositInvoices = new ArrayCollection;
	}


	public function addDepositInvoice(InvoiceCore $depositInvoice): void
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
		return $this->getTotalTax() * $this->getRate();
	}


	/**
	 * @return InvoiceCore[]|Collection
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
		return $this->getTotalPriceWithoutTax() * $this->getRate();
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
		return $this->isSubmitted() && $this->getAcceptStatus1() === InvoiceStatus::ACCEPTED && $this->getAcceptStatus2(
			) === InvoiceStatus::ACCEPTED;
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


	public function getPayDocument(): ?InvoicePayDocument
	{
		return $this->payDocument;
	}


	public function setPayDocument(?InvoicePayDocument $payDocument): void
	{
		$this->payDocument = $payDocument;
	}


	public function isPayDocument(): bool
	{
		return false;
	}


	public function isDisableStatistics(): bool
	{
		return $this->disableStatistics;
	}


	public function setDisableStatistics(bool $disableStatistics): void
	{
		$this->disableStatistics = $disableStatistics;
	}


	public function getFixInvoice(): ?FixInvoice
	{
		return null;
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
