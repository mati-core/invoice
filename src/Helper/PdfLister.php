<?php

declare(strict_types=1);


namespace MatiCore\Invoice;

/**
 * Class PdfLister
 * @package App\Model
 */
class PdfLister
{

	private static $index = 0;

	public static $list = 'abcdefghijklmnopqrstuvwxyz';

	/**
	 * @return string
	 */
	public static function getItem(): string
	{
		$item = self::$list[self::$index];

		self::$index++;

		return $item;
	}

	public static function reset(): void
	{
		self::$index = 0;
	}
}