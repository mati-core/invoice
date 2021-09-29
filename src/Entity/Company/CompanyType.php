<?php

declare(strict_types=1);

namespace MatiCore\Company;


class CompanyType
{
	public const
		STANDARD = 'company.type.standard',
		VIP = 'company.type.vip',
		CONTRACT = 'company.type.contract';


	/**
	 * @return string[]
	 */
	public static function getList(): array
	{
		return [
			self::STANDARD => self::STANDARD,
			self::VIP => self::VIP,
			self::CONTRACT => self::CONTRACT,
		];
	}


	public static function getDefault(): string
	{
		return self::STANDARD;
	}
}
