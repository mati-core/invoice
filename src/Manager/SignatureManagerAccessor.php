<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


interface SignatureManagerAccessor
{
	public function get(): SignatureManager;
}
