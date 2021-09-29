<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

interface ExpenseManagerAccessor
{
	public function get(): ExpenseManager;
}
