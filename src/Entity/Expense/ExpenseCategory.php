<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


class ExpenseCategory
{
	public const DEFAULT = null;

	public const PROVOZNI_NAKLADY = 'provozni-naklady';

	public const PROVOZNI_NAKLADY_MZDY = 'provozni-naklady-mzdy';

	public const PROVOZNI_NAKLADY_VOZIDLA = 'provozni-naklady-vozidla';

	public const NAKLADY_DOPRAVA = 'naklady-doprava';

	public const NAKUP_VOZIK = 'nakup-vozik';

	public const NAKUP_NAHRADNI_DILY = 'nakup-nahradni-dily';

	public const SPOTREBNI_MATERIAL = 'spotrebni-material';

	public const PENZIJNI_FOND = 'penzijni-fond';

	public const REKLAMA = 'reklama';

	public const DOBROPIS = 'dobropis';

	public const DPH = 'tax';

	public const ODVOD_STATU = 'state-pay';

	public const UVER = 'uver';


	/**
	 * @param string $key
	 * @return string
	 */
	public static function getName(string $key): string
	{
		return self::getListAll()[$key] ?? 'unknown';
	}


	/**
	 * @return array
	 */
	public static function getList(): array
	{
		return [
			self::PROVOZNI_NAKLADY => 'Provozní náklady',
			self::PROVOZNI_NAKLADY_VOZIDLA => 'Provozní náklady - vozidla',
			self::NAKLADY_DOPRAVA => 'Náklady - doprava',
			self::NAKUP_VOZIK => 'Nákup vozíků',
			self::NAKUP_NAHRADNI_DILY => 'Nákup náhradních dílů',
			self::SPOTREBNI_MATERIAL => 'Spotřební materiál',
			self::PENZIJNI_FOND => 'Penzijni fond',
			self::REKLAMA => 'Reklama',
			self::DOBROPIS => 'Dobropis',
		];
	}


	/**
	 * @return array
	 */
	public static function getAdminList(): array
	{
		return [
			self::PROVOZNI_NAKLADY => 'Provozní náklady',
			self::PROVOZNI_NAKLADY_VOZIDLA => 'Provozní náklady - vozidla',
			self::NAKLADY_DOPRAVA => 'Náklady - doprava',
			self::NAKUP_VOZIK => 'Nákup vozíků',
			self::NAKUP_NAHRADNI_DILY => 'Nákup náhradních dílů',
			self::SPOTREBNI_MATERIAL => 'Spotřební materiál',
			self::PENZIJNI_FOND => 'Penzijni fond',
			self::UVER => 'Úvěr',
			self::REKLAMA => 'Reklama',
			self::DOBROPIS => 'Dobropis',
		];
	}


	/**
	 * @return array
	 */
	public static function getListAll(): array
	{
		return [
			self::PROVOZNI_NAKLADY => 'Provozní náklady',
			self::PROVOZNI_NAKLADY_MZDY => 'Provozní náklady - mzdy',
			self::PROVOZNI_NAKLADY_VOZIDLA => 'Provozní náklady - vozidla',
			self::NAKLADY_DOPRAVA => 'Náklady - doprava',
			self::NAKUP_VOZIK => 'Nákup vozíků',
			self::NAKUP_NAHRADNI_DILY => 'Nákup náhradních dílů',
			self::SPOTREBNI_MATERIAL => 'Spotřební materiál',
			self::PENZIJNI_FOND => 'Penzijni fond',
			self::UVER => 'Úvěr',
			self::REKLAMA => 'Reklama',
			self::DOBROPIS => 'Dobropis',
			self::DPH => 'DPH',
			self::ODVOD_STATU => 'Odvod státu',
		];
	}

}
