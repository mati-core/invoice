<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 * @ORM\Table(name="invoice__bank_movement")
 */
class BankMovement
{
	public const
		STATUS_NOT_PROCESSED = 'not-processed',
		STATUS_SUCCESS = 'success',
		STATUS_DONE = 'done',
		STATUS_BAD_PRICE = 'bad-price',
		STATUS_BAD_ACCOUNT = 'bad-account',
		STATUS_BAD_CURRENCY = 'bad-currency',
		STATUS_BAD_VARIABLE_SYMBOL = 'bad-vs',
		STATUS_IS_PAID = 'is-paid',
		STATUS_SYSTEM_ERROR = 'error';

	use IdentifierUnsigned;

	/** @ORM\Column(type="string") */
	private string $messageId;

	/** @ORM\Column(type="string") */
	private string $status = self::STATUS_NOT_PROCESSED;

	/**
	 * @ORM\ManyToOne(targetEntity="Invoice")
	 * @ORM\JoinColumn(name="invoice_id", referencedColumnName="id", nullable=true)
	 */
	private Invoice|null $invoice;

	/** @ORM\Column(type="string") */
	private string $bankAccountName;

	/** @ORM\Column(type="string") */
	private string $bankAccount;

	/** @ORM\Column(type="string") */
	private string $currencyIsoCode;

	/**
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="currency_id", referencedColumnName="id")
	 */
	private Currency $currency;

	/** @ORM\Column(type="string") */
	private string $customerBankAccount;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $customerName;

	/** @ORM\Column(type="string") */
	private string $variableSymbol;

	/** @ORM\Column(type="string", nullable=true) */
	private string|null $constantSymbol;

	/** @ORM\Column(type="text", nullable=true) */
	private string|null $message;

	/** @ORM\Column(type="float") */
	private float $price;

	/** @ORM\Column(type="date") */
	private \DateTime $date;

	/** @ORM\Column(type="datetime") */
	private \DateTime $createDate;


	public function __construct(
		string $messageId,
		string $bankAccountName,
		string $bankAccount,
		string $currencyIsoCode,
		Currency $currency,
		string $customerBankAccount,
		string $variableSymbol,
		float $price,
		\DateTime $date
	) {
		$this->messageId = $messageId;
		$this->bankAccountName = $bankAccountName;
		$this->bankAccount = $bankAccount;
		$this->currencyIsoCode = $currencyIsoCode;
		$this->currency = $currency;
		$this->customerBankAccount = $customerBankAccount;
		$this->variableSymbol = $variableSymbol;
		$this->price = $price;
		$this->date = $date;
		$this->createDate = new \DateTime;
	}


	public function getStatus(): string
	{
		return $this->status;
	}


	public function setStatus(string $status): void
	{
		$this->status = $status;
	}


	public function getMessageId(): string
	{
		return $this->messageId;
	}


	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}


	public function setInvoice(?Invoice $invoice): void
	{
		$this->invoice = $invoice;
	}


	public function getBankAccountName(): string
	{
		return $this->bankAccountName;
	}


	public function setBankAccountName(string $bankAccountName): void
	{
		$this->bankAccountName = $bankAccountName;
	}


	public function getBankAccount(): string
	{
		return $this->bankAccount;
	}


	public function setBankAccount(string $bankAccount): void
	{
		$this->bankAccount = $bankAccount;
	}


	public function getCurrencyIsoCode(): string
	{
		return $this->currencyIsoCode;
	}


	public function setCurrencyIsoCode(string $currencyIsoCode): void
	{
		$this->currencyIsoCode = $currencyIsoCode;
	}


	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	public function setCurrency(Currency $currency): void
	{
		$this->currency = $currency;
	}


	public function getCustomerBankAccount(): string
	{
		return $this->customerBankAccount;
	}


	public function setCustomerBankAccount(string $customerBankAccount): void
	{
		$this->customerBankAccount = $customerBankAccount;
	}


	public function getCustomerName(): ?string
	{
		return $this->customerName;
	}


	public function setCustomerName(?string $customerName): void
	{
		$this->customerName = $customerName;
	}


	public function getVariableSymbol(): string
	{
		return $this->variableSymbol;
	}


	public function setVariableSymbol(string $variableSymbol): void
	{
		$this->variableSymbol = $variableSymbol;
	}


	public function getConstantSymbol(): ?string
	{
		return $this->constantSymbol;
	}


	public function setConstantSymbol(?string $constantSymbol): void
	{
		$this->constantSymbol = $constantSymbol;
	}


	public function getMessage(): ?string
	{
		return $this->message;
	}


	public function setMessage(?string $message): void
	{
		$this->message = $message;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function setPrice(float $price): void
	{
		$this->price = $price;
	}


	public function getDate(): \DateTime
	{
		return $this->date;
	}


	public function setDate(\DateTime $date): void
	{
		$this->date = $date;
	}


	public function getCreateDate(): \DateTime
	{
		return $this->createDate;
	}
}
