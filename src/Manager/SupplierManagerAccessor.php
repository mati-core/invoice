<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


interface SupplierManagerAccessor
{
	public function get(): SupplierManager;
}
