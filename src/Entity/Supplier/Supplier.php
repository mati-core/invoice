<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


use Baraja\Doctrine\UUID\UuidIdentifier;
use Doctrine\ORM\Mapping as ORM;
use MatiCore\Address\Entity\Address;
use MatiCore\Currency\Currency;
use Nette\SmartObject;

/**
 * Class Supplier
 * @package MatiCore\Supplier
 * @ORM\Entity()
 * @ORM\Table(name="supplier__supplier")
 */
class Supplier
{

	use SmartObject;
	use UuidIdentifier;

	/**
	 * @var string
	 * @ORM\Column(type="string")
	 */
	private string $name;

	/**
	 * @var string|null
	 * @ORM\Column(type="string", nullable=true)
	 */
	private string|null $deliveryCompany = null;

	/**
	 * @var Address
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Address\Entity\Address")
	 * @ORM\JoinColumn(name="address_id", referencedColumnName="id")
	 */
	private Address $address;

	/**
	 * @var Currency
	 * @ORM\ManyToOne(targetEntity="\MatiCore\Currency\Currency")
	 * @ORM\JoinColumn(name="delivery_currency_id", referencedColumnName="id")
	 */
	private Currency $currency;

	/**
	 * @var bool
	 * @ORM\Column(type="boolean")
	 */
	private bool $active = true;

	/**
	 * Supplier constructor.
	 * @param string $name
	 * @param Currency $currency
	 * @param Address $address
	 */
	public function __construct(string $name, Currency $currency, Address $address)
	{
		$this->name = $name;
		$this->currency = $currency;
		$this->address = $address;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return string|null
	 */
	public function getDeliveryCompany(): ?string
	{
		return $this->deliveryCompany;
	}

	/**
	 * @param string|null $deliveryCompany
	 */
	public function setDeliveryCompany(?string $deliveryCompany): void
	{
		$this->deliveryCompany = $deliveryCompany;
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
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}

	/**
	 * @param bool $active
	 */
	public function setActive(bool $active): void
	{
		$this->active = $active;
	}

}