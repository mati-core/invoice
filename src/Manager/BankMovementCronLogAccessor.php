<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

interface BankMovementCronLogAccessor
{
	public function get(): BankMovementCronLog;
}
