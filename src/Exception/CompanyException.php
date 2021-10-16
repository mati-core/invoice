<?php

declare(strict_types=1);

namespace MatiCore\Company;


class CompanyException extends \Exception
{
	public static function isUsed(): void
	{
		throw new self('Firmu nelze odstranit, protože je používána.');
	}


	public static function isStockUsed(): void
	{
		throw new self('Pobočku nelze odstranit, protože je používána.');
	}
}
