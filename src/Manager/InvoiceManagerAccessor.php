<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

/**
 * Interface InvoiceManagerAccessor
 * @package MatiCore\Invoice
 */
interface InvoiceManagerAccessor
{

	/**
	 * @return InvoiceManager
	 */
	public function get(): InvoiceManager;

}