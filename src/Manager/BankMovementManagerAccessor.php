<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

/**
 * Interface BankMovementManagerAccessor
 * @package MatiCore\Invoice
 */
interface BankMovementManagerAccessor
{

	/**
	 * @return BankMovementManager
	 */
	public function get(): BankMovementManager;

}