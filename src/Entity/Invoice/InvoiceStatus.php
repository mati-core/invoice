<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

class InvoiceStatus
{

	public const CREATED = 'created';
	public const WAITING = 'waiting';
	public const ACCEPTED = 'accepted';
	public const DENIED = 'denied';
	public const SENT = 'sent';
	public const CANCELLED = 'cancelled';
	public const PAID = 'paid';

	public const PAY_ALERT_NONE = 'none';
	public const PAY_ALERT_ONE = 'one';
	public const PAY_ALERT_TWO = 'two';
	public const PAY_ALERT_THREE = 'three';

	/**
	 * @return array
	 */
	public static function getList(): array
	{
		return [
			self::CREATED => 'Vytvořeno',
			self::WAITING => 'Čeká na schválení',
			self::ACCEPTED => 'Schváleno',
			self::DENIED => 'Zamítnuto',
			self::SENT => 'Odesláno',
			self::CANCELLED => 'Storno',
			self::PAID => 'Uhrazeno',
			self::PAY_ALERT_ONE => '1. upomínka',
			self::PAY_ALERT_TWO => '2. upomínka',
			self::PAY_ALERT_THREE => '3. upomínka',
		];
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public static function getNameByStatus(string $status): string
	{
		$list = self::getList();

		return $list[$status] ?? 'Unknown';
	}
	
	public static function getColorByStatus(string $status): string{
		$list = [
			self::CREATED => 'text-info',
			self::WAITING => 'text-warning',
			self::ACCEPTED => 'text-success',
			self::DENIED => 'text-danger',
			self::SENT => 'text-info',
			self::CANCELLED => 'test-danger',
			self::PAID => 'text-success',
			self::PAY_ALERT_ONE => 'text-warning',
			self::PAY_ALERT_TWO => 'text-warning',
			self::PAY_ALERT_THREE => 'text-danger',
		];

		return $list[$status] ?? 'Unknown';
	}
}