<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


class ExpenseCategory
{
	public const DEFAULT = null;

	public const
		PROVOZNI_NAKLADY = 'provozni-naklady',
		PROVOZNI_NAKLADY_MZDY = 'provozni-naklady-mzdy',
		PROVOZNI_NAKLADY_VOZIDLA = 'provozni-naklady-vozidla',
		NAKLADY_DOPRAVA = 'naklady-doprava',
		NAKUP_VOZIK = 'nakup-vozik',
		NAKUP_NAHRADNI_DILY = 'nakup-nahradni-dily',
		SPOTREBNI_MATERIAL = 'spotrebni-material',
		PENZIJNI_FOND = 'penzijni-fond',
		REKLAMA = 'reklama',
		DOBROPIS = 'dobropis',
		DPH = 'tax',
		ODVOD_STATU = 'state-pay',
		UVER = 'uver';

	public const LIST = [
		self::PROVOZNI_NAKLADY => 'Provozní náklady',
		self::PROVOZNI_NAKLADY_VOZIDLA => 'Provozní náklady - vozidla',
		self::NAKLADY_DOPRAVA => 'Náklady - doprava',
		self::NAKUP_VOZIK => 'Nákup vozíků',
		self::NAKUP_NAHRADNI_DILY => 'Nákup náhradních dílů',
		self::SPOTREBNI_MATERIAL => 'Spotřební materiál',
		self::PENZIJNI_FOND => 'Penzijni fond',
		self::REKLAMA => 'Reklama',
		self::DOBROPIS => 'Dobropis',
		self::PROVOZNI_NAKLADY_MZDY => 'Provozní náklady - mzdy',
		self::UVER => 'Úvěr',
		self::DPH => 'DPH',
		self::ODVOD_STATU => 'Odvod státu',
	];

	public const ADMIN_LIST = [
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
