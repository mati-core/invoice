<?php

declare(strict_types=1);

namespace MatiCore\Company;


interface CompanyManagerAccessor
{
	public function get(): CompanyManager;
}
