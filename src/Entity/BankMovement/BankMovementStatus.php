<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

/**
 * Class BankMovementStatus
 * @package MatiCore\Invoice
 */
class BankMovementStatus{

	private static $names = [
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

	private static $colors = [
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

	/**
	 * @param string $status
	 * @return string
	 */
	public static function getName(string $status): string
	{
		return self::$names[$status] ?? 'Unknown';
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function getColor(string $status) : string
	{
		return self::$colors[$status] ?? 'text-info';
	}

}