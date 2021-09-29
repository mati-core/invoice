<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


interface InvoiceManagerAccessor
{
	public function get(): InvoiceManager;
}
