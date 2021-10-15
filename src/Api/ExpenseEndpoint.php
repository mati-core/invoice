<?php

declare(strict_types=1);

namespace App\Api;


use Baraja\Doctrine\EntityManagerException;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Invoice\ExpenseException;
use MatiCore\Invoice\ExpenseHelper;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\User;

#[PublicEndpoint]
class ExpenseEndpoint extends BaseEndpoint
{
	public function __construct(
		private User $user,
		private ExpenseHelper $expenseHelper,
		private CompanyManagerAccessor $companyManager,
		private CurrencyManagerAccessor $currencyManager,
	) {
	}


	public function postLoadExpense(string $id): void
	{
		try {
			if ($id === '') {
				$expenseData = $this->expenseHelper->getNewExpense();
			} else {
				$expenseData = $this->expenseHelper->getExpenseById($id);
			}

			$this->sendJson(
				[
					'expense' => $expenseData,
				]
			);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->sendError($e->getMessage());
		}
	}


	/**
	 * @param array $expenseData
	 */
	public function postLoadCurrency(string $code, array $expenseData): void
	{
		try {
			$currency = $this->currencyManager->get()->getCurrencyByIsoCode($code);
			$currencyTemp = $this->currencyManager->get()->getCurrencyRateByDate(
				$currency, new \DateTime($expenseData['date'] ?? 'now')
			);

			$this->sendJson(
				[
					'currency' => [
						'id' => $currency->getId(),
						'code' => $currency->getCode(),
						'symbol' => $currency->getSymbol(),
						'rateReal' => $currencyTemp->getRate(),
						'rateRealString' => str_replace('.', ',', (string) $currencyTemp->getRate()),
						'rateDate' => $currencyTemp->getLastUpdate()->format('d.m.Y'),
					],
				]
			);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Unknown currency');
		}
	}


	public function postLoadCompanyByCin(string $cin): void
	{
		try {
			$aresData = $this->companyManager->get()->getDataFromAres($cin);

			$this->sendJson(
				[
					'customer' => [
						'id' => null,
						'name' => $aresData->company,
						'address' => $aresData->street . ' ' . $aresData->house_number,
						'city' => $aresData->city,
						'zipCode' => $aresData->zip,
						'country' => 'CZE',
						'ic' => $aresData->in,
						'dic' => $aresData->tin,
					],
					'currency' => 'CZK',
				]
			);
		} catch (IdentificationNumberNotFoundException) {
			$this->sendJson(
				[
					'customer' => [
						'id' => null,
						'name' => '',
						'address' => '',
						'city' => '',
						'zipCode' => '',
						'country' => 'CZE',
						'ic' => $cin,
						'dic' => '',
					],
					'currency' => 'CZK',
				]
			);
		}
	}


	public function postLoadSupplier(string $id): void
	{
		try {
			$this->sendJson(
				[
					'customer' => $this->expenseHelper->getSupplierData($id),
				]
			);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendJson(
				[
					'customer' => [
						'name' => '',
						'address' => '',
						'city' => '',
						'zipCode' => '',
						'country' => '',
						'ic' => '',
						'dic' => '',
					],
				]
			);
		}
	}


	/**
	 * @param array|null $expenseData
	 */
	public function postSave(?array $expenseData): void
	{
		try {
			$expenseData = $this->expenseHelper->saveExpense($expenseData);
			$this->sendJson(
				[
					'expense' => $expenseData,
					'redirect' => $this->link(':Admin:Expense:show', ['id' => $expenseData['id']]),
				]
			);
		} catch (ExpenseException | EntityManagerException | NoResultException | NonUniqueResultException | CurrencyException | InvalidLinkException $e) {
			$this->sendError($e->getMessage());
		}
	}
}
