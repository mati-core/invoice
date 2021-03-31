<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Currency\Currency;
use Nette\SmartObject;
use \DateTime;
use \Nette\Utils\DateTime as UDateTime;

/**
 * Class BankMovement
 * @package MatiCore\Invoice
 * @ORM\Entity()
 * @ORM\Table(name="invoice__bank_movement")
 */
class BankMovement
{

	public const STATUS_NOT_PROCESSED = 'not-processed';
	public const STATUS_SUCCESS = 'success';
	public const STATUS_DONE = 'done';
	public const STATUS_BAD_PRICE = 'bad-price';
	public const STATUS_BAD_ACCOUNT = 'bad-account';
	public const STATUS_BAD_CURRENCY = 'bad-currency';
	public const STATUS_BAD_VARIABLE_SYMBOL = 'bad-vs';
	public const STATUS_IS_PAID = 'is-paid';
	public const STATUS_SYSTEM_ERROR = 'error';

	use UuidIdentifier;
	use SmartObject;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $messageId;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $status = self::STATUS_NOT_PROCESSED;

	/**
	 * @var InvoiceCore|null
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Invoice\InvoiceCore")
	 * @ORM\JoinColumn(name="invoice_id", referencedColumnName="id", nullable=true)
	 */
	private InvoiceCore|null $invoice;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $bankAccountName;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $bankAccount;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $currencyIsoCode;

	/**
	 * @var Currency
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	private Currency $currency;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $customerBankAccount;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $customerName;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $variableSymbol;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $constantSymbol;

	/**
	 * @var string|null
	 * @ORM\Column(type="text", nullable=true)
	 */
	private string|null $message;

	/**
	 * @var float
	 * @ORM\Column(type="float")
	 */
	private float $price;

	/**
	 * @var DateTime
	 * @ORM\Column(type="date")
	 */
	private DateTime $date;

	/**
	 * @var DateTime
	 * @ORM\Column(type="datetime")
	 */
	private DateTime $createDate;

	/**
	 * BankMovement constructor.
	 * @param string $messageId
	 * @param string $bankAccountName
	 * @param string $bankAccount
	 * @param string $currencyIsoCode
	 * @param Currency $currency
	 * @param string $customerBankAccount
	 * @param string $variableSymbol
	 * @param float $price
	 * @param DateTime $date
	 * @throws \Exception
	 */
	public function __construct(string $messageId, string $bankAccountName, string $bankAccount, string $currencyIsoCode, Currency $currency, string $customerBankAccount, string $variableSymbol, float $price, DateTime $date)
	{
		$this->messageId = $messageId;
		$this->bankAccountName = $bankAccountName;
		$this->bankAccount = $bankAccount;
		$this->currencyIsoCode = $currencyIsoCode;
		$this->currency = $currency;
		$this->customerBankAccount = $customerBankAccount;
		$this->variableSymbol = $variableSymbol;
		$this->price = $price;
		$this->date = $date;
		$this->createDate = UDateTime::from('NOW');
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
	 * @return string
	 */
	public function getMessageId(): string
	{
		return $this->messageId;
	}

	/**
	 * @return InvoiceCore|null
	 */
	public function getInvoice(): ?InvoiceCore
	{
		return $this->invoice;
	}

	/**
	 * @param InvoiceCore|null $invoice
	 */
	public function setInvoice(?InvoiceCore $invoice): void
	{
		$this->invoice = $invoice;
	}

	/**
	 * @return string
	 */
	public function getBankAccountName(): string
	{
		return $this->bankAccountName;
	}

	/**
	 * @param string $bankAccountName
	 */
	public function setBankAccountName(string $bankAccountName): void
	{
		$this->bankAccountName = $bankAccountName;
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
	public function getCurrencyIsoCode(): string
	{
		return $this->currencyIsoCode;
	}

	/**
	 * @param string $currencyIsoCode
	 */
	public function setCurrencyIsoCode(string $currencyIsoCode): void
	{
		$this->currencyIsoCode = $currencyIsoCode;
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
	public function getCustomerBankAccount(): string
	{
		return $this->customerBankAccount;
	}

	/**
	 * @param string $customerBankAccount
	 */
	public function setCustomerBankAccount(string $customerBankAccount): void
	{
		$this->customerBankAccount = $customerBankAccount;
	}

	/**
	 * @return string|null
	 */
	public function getCustomerName(): ?string
	{
		return $this->customerName;
	}

	/**
	 * @param string|null $customerName
	 */
	public function setCustomerName(?string $customerName): void
	{
		$this->customerName = $customerName;
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
	 * @return string|null
	 */
	public function getConstantSymbol(): ?string
	{
		return $this->constantSymbol;
	}

	/**
	 * @param string|null $constantSymbol
	 */
	public function setConstantSymbol(?string $constantSymbol): void
	{
		$this->constantSymbol = $constantSymbol;
	}

	/**
	 * @return string|null
	 */
	public function getMessage(): ?string
	{
		return $this->message;
	}

	/**
	 * @param string|null $message
	 */
	public function setMessage(?string $message): void
	{
		$this->message = $message;
	}

	/**
	 * @return float
	 */
	public function getPrice(): float
	{
		return $this->price;
	}

	/**
	 * @param float $price
	 */
	public function setPrice(float $price): void
	{
		$this->price = $price;
	}

	/**
	 * @return DateTime
	 */
	public function getDate(): DateTime
	{
		return $this->date;
	}

	/**
	 * @param DateTime $date
	 */
	public function setDate(DateTime $date): void
	{
		$this->date = $date;
	}

	/**
	 * @return DateTime
	 */
	public function getCreateDate(): DateTime
	{
		return $this->createDate;
	}

}