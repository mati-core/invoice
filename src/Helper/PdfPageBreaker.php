<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use MatiCore\Currency\Currency;

class PdfPageBreaker
{
	private int $pageNumber = 1;

	private float $totalPrice = 0.0;

	private Currency $currency;

	private int $breakIndex = 0;

	private int $breakOn;


	public function __construct(Currency $currency, int $breakOn = 0)
	{
		$this->currency = $currency;
		$this->breakOn = $breakOn;
	}


	public function getPageNumber(): int
	{
		return $this->pageNumber;
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


	public function getBreakIndex(): int
	{
		return $this->breakIndex;
	}


	public function setBreakIndex(int $breakIndex): void
	{
		$this->breakIndex = $breakIndex;
	}


	public function getBreakOn(): int
	{
		return $this->breakOn;
	}


	public function setBreakOn(int $breakOn): void
	{
		$this->breakOn = $breakOn;
	}


	public function increase(int $breakIndex, float $price = 0.0): void
	{
		$this->breakIndex += $breakIndex;
		$this->totalPrice += $price;
	}


	public function isNewPage(): bool
	{
		return $this->breakIndex > $this->breakOn;
	}


	public function nextPage(): void
	{
		$this->pageNumber++;
		$this->breakIndex = 0;
	}
}
