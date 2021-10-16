<?php

declare(strict_types=1);

namespace MatiCore\Company;


class CompanyType
{
	public const
		STANDARD = 'standard',
		VIP = 'vip',
		CONTRACT = 'contract';

	public const LIST = [
		self::STANDARD => self::STANDARD,
		self::VIP => self::VIP,
		self::CONTRACT => self::CONTRACT,
	];
}
