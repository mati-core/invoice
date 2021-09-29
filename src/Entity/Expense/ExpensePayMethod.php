<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


class ExpensePayMethod
{
	public const UNKNOWN = null;

	public const CASH = 'cash';

	public const BANK = 'bank';

	public const CARD = 'card';


	/**
	 * @return array
	 */
	public static function getList(): array
	{
		return [
			self::CASH => 'Hotově',
			self::BANK => 'Bankovní převod',
			self::CARD => 'Kartou',
		];
	}


	/**
	 * @param string|null $type
	 * @return string
	 */
	public static function getName(?string $type): string
	{
		return self::getList()[$type] ?? 'Neuvedeno';
	}

}
