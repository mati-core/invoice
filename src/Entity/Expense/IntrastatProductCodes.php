<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


/**
 * Class IntrastatProductCodes
 * @package MatiCore\Invoice
 */
class IntrastatProductCodes
{

	/**
	 * @return string[]
	 */
	public static function getList(): array
	{
		return [
			'8431 20 00' => 'Části vozíků vidlicových stohovacích apod., se zařízením zdvihacím, manipulačním',
			'8427 10 10' => 'Samohybné vozíky poháněné elektrickým motorem s výškou zdvihu 1m nebo vyšším.',
			'8427 10 90' => 'Samohybné vozíky poháněné elektrickým motorem ostatní.',
			'8427 20 11' => 'Vozíky vidlicové zdvihací terénní apod.stohovací,zdvih 1m a víc, ne s el. motorem',
			'8427 20 19' => 'Vozíky zdvihací samohybné ostatní, zdvih 1m a víc, ne s el. motorem',
			'8427 20 90' => 'Vozíky zdvihací samohybné ostatní, zdvih do 1m, ne s el. motorem',
			'8427 90 00' => 'Vozíky ostatní se zařízením zdvihacím, manipulačním, ne s el. motorem',
		];
	}

}