<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


class PdfLister
{
	public const CHARS = 'abcdefghijklmnopqrstuvwxyz';

	private static int $index = 0;


	public static function getItem(): string
	{
		$item = self::CHARS[self::$index] ?? '?';
		self::$index++;

		return $item;
	}


	public static function reset(): void
	{
		self::$index = 0;
	}
}
