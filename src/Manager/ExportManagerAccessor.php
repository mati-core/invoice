<?php

declare(strict_types=1);

namespace MatiCore\Invoice;

/**
 * Interface ExportManagerAccessor
 * @package MatiCore\Invoice
 */
interface ExportManagerAccessor
{

	/**
	 * @return ExportManager
	 */
	public function get(): ExportManager;

}