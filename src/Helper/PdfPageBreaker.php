<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use MatiCore\Currency\Currency;

/**
 * Class PdfPageBreaker
 * @package App\Model
 */
class PdfPageBreaker
{

	/**
	 * @var int
	 */
	private int $pageNumber = 1;

	/**
	 * @var float
	 */
	private float $totalPrice = 0.0;

	/**
	 * @var Currency
	 */
	private Currency $currency;

	/**
	 * @var int
	 */
	private int $breakIndex = 0;

	/**
	 * @var int
	 */
	private int $breakOn = 0;

	/**
	 * PdfPageBreaker constructor.
	 * @param Currency $currency
	 * @param int $breakOn
	 */
	public function __construct(Currency $currency, int $breakOn)
	{
		$this->currency = $currency;
		$this->breakOn = $breakOn;
	}

	/**
	 * @return int
	 */
	public function getPageNumber(): int
	{
		return $this->pageNumber;
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
	 * @return int
	 */
	public function getBreakIndex(): int
	{
		return $this->breakIndex;
	}

	/**
	 * @param int $breakIndex
	 */
	public function setBreakIndex(int $breakIndex): void
	{
		$this->breakIndex = $breakIndex;
	}

	/**
	 * @return int
	 */
	public function getBreakOn(): int
	{
		return $this->breakOn;
	}

	/**
	 * @param int $breakOn
	 */
	public function setBreakOn(int $breakOn): void
	{
		$this->breakOn = $breakOn;
	}

	/**
	 * @param int $breakIndex
	 * @param float $price
	 */
	public function increase(int $breakIndex, float $price = 0.0): void
	{
		$this->breakIndex += $breakIndex;
		$this->totalPrice += $price;
	}

	/**
	 * @return bool
	 */
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