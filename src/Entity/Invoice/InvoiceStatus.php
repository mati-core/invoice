<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

class InvoiceStatus
{
	public const
		CREATED = 'created',
		WAITING = 'waiting',
		ACCEPTED = 'accepted',
		DENIED = 'denied',
		SENT = 'sent',
		CANCELLED = 'cancelled',
		PAID = 'paid',
		PAY_ALERT_NONE = 'none',
		PAY_ALERT_ONE = 'one',
		PAY_ALERT_TWO = 'two',
		PAY_ALERT_THREE = 'three';


	/**
	 * @return array<string, string>
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


	public static function getNameByStatus(string $status): string
	{
		$list = self::getList();

		return $list[$status] ?? 'Unknown';
	}


	public static function getColorByStatus(string $status): string
	{
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
