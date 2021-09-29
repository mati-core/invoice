<?php

declare(strict_types=1);

namespace MatiCore\Company;


class CompanyException extends \Exception
{
	/**
	 * @throws CompanyException
	 */
	public static function isUsed(): void
	{
		throw new self('Firmu nelze odstranit, protože je používána.');
	}


	/**
	 * @throws CompanyException
	 */
	public static function isStockUsed(): void
	{
		throw new self('Pobočku nelze odstranit, protože je používána.');
	}
}
