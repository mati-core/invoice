<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

/**
 * Interface ExpenseManagerAccessor
 * @package MatiCore\Invoice
 */
interface ExpenseManagerAccessor
{

	/**
	 * @return ExpenseManager
	 */
	public function get(): ExpenseManager;

}