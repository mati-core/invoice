<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

class BankMovementStatus
{

	/**
	 * @var array<string>
	 */
	private static array $names = [
		BankMovement::STATUS_NOT_PROCESSED => 'Nezpracováno',
		BankMovement::STATUS_SUCCESS => 'Hotovo',
		BankMovement::STATUS_DONE => 'Vyřešeno',
		BankMovement::STATUS_BAD_PRICE => 'Špatná částka',
		BankMovement::STATUS_BAD_ACCOUNT => 'Špatné číslo účtu',
		BankMovement::STATUS_BAD_CURRENCY => 'Špatná měna',
		BankMovement::STATUS_BAD_VARIABLE_SYMBOL => 'Špatný VS',
		BankMovement::STATUS_IS_PAID => 'Faktura již uhrazena',
		BankMovement::STATUS_SYSTEM_ERROR => 'Chyba',
	];

	/**
	 * @var array<string>
	 */
	private static array $colors = [
		BankMovement::STATUS_NOT_PROCESSED => 'text-warning',
		BankMovement::STATUS_SUCCESS => 'text-success',
		BankMovement::STATUS_DONE => 'text-info',
		BankMovement::STATUS_BAD_PRICE => 'text-danger',
		BankMovement::STATUS_BAD_ACCOUNT => 'text-danger',
		BankMovement::STATUS_BAD_CURRENCY => 'text-danger',
		BankMovement::STATUS_BAD_VARIABLE_SYMBOL => 'text-danger',
		BankMovement::STATUS_IS_PAID => 'text-danger',
		BankMovement::STATUS_SYSTEM_ERROR => 'text-danger',
	];


	public static function getName(string $status): string
	{
		return self::$names[$status] ?? 'Unknown';
	}


	public static function getColor(string $status): string
	{
		return self::$colors[$status] ?? 'text-info';
	}
}
