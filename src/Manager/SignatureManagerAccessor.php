<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

/**
 * Interface SignatureManagerAccessor
 * @package MatiCore\Invoice
 */
interface SignatureManagerAccessor
{

	/**
	 * @return SignatureManager
	 */
	public function get(): SignatureManager;

}