<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

/**
 * Interface BankMovementCronLogAccessor
 * @package MatiCore\Invoice
 */
interface BankMovementCronLogAccessor
{

	/**
	 * @return BankMovementCronLog
	 */
	public function get(): BankMovementCronLog;

}