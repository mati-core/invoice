<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


interface BankMovementManagerAccessor
{
	public function get(): BankMovementManager;
}
