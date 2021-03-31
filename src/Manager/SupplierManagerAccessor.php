<?php

declare(strict_types=1);

namespace MatiCore\Supplier;

/**
 * Interface SupplierManagerAccessor
 * @package MatiCore\Supplier
 */
interface SupplierManagerAccessor
{

	/**
	 * @return SupplierManager
	 */
	public function get(): SupplierManager;

}