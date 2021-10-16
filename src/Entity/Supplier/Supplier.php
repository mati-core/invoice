<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice__supplier')]
class Supplier
{
	use IdentifierUnsigned;

	#[ORM\Column(type: 'string')]
	private string $name;

	#[ORM\Column(type: 'string', nullable: true)]
	private ?string $deliveryCompany = null;

	#[ORM\ManyToOne(targetEntity: Address::class)]
	private Address $address;

	#[ORM\ManyToOne(targetEntity: Currency::class)]
	private Currency $currency;

	#[ORM\Column(type: 'boolean')]
	private bool $active = true;


	public function __construct(string $name, Currency $currency, Address $address)
	{
		$this->name = $name;
		$this->currency = $currency;
		$this->address = $address;
	}


	public function getName(): string
	{
		return $this->name;
	}


	public function setName(string $name): void
	{
		$this->name = $name;
	}


	public function getDeliveryCompany(): ?string
	{
		return $this->deliveryCompany;
	}


	public function setDeliveryCompany(?string $deliveryCompany): void
	{
		$this->deliveryCompany = $deliveryCompany;
	}


	public function getCurrency(): Currency
	{
		return $this->currency;
	}


	public function setCurrency(Currency $currency): void
	{
		$this->currency = $currency;
	}


	public function getAddress(): Address
	{
		return $this->address;
	}


	public function setAddress(Address $address): void
	{
		$this->address = $address;
	}


	public function isActive(): bool
	{
		return $this->active;
	}


	public function setActive(bool $active): void
	{
		$this->active = $active;
	}
}
