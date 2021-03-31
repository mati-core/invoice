<?php

declare(strict_types=1);

namespace MatiCore\Company;

/**
 * Interface CompanyManagerAccessor
 * @package MatiCore\Company
 */
interface CompanyManagerAccessor
{

	/**
	 * @return CompanyManager
	 */
	public function get(): CompanyManager;

}