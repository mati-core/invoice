<?php

declare(strict_types=1);

namespace MatiCore\Company;

/**
 * Class CompanyType
 * @package MatiCore\Company
 */
class CompanyType
{

	public const STANDARD = 'company.type.standard';
	public const VIP = 'company.type.vip';
	public const CONTRACT = 'company.type.contract';

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

	/**
	 * @return string
	 */
	public static function getDefault(): string
	{
		return self::STANDARD;
	}

}