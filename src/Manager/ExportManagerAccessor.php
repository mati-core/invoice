<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


interface ExportManagerAccessor
{
	public function get(): ExportManager;
}
