<?php

declare(strict_types=1);


namespace MatiCore\Supplier;

/**
 * Class SupplierException
 * @package App\Model
 */
class SupplierException extends \Exception
{

	/**
	 * @throws SupplierException
	 */
	public static function isUsed(): void
	{
		throw new self('Dodavatele nelze odstranit, protože je vázaný na položky ve skladu.');
	}

}