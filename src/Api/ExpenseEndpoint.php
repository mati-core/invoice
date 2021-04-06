<?php

declare(strict_types=1);

namespace App\Api;


use Baraja\Doctrine\DatabaseException;
use Baraja\Doctrine\EntityManagerException;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use h4kuna\Ares\Exceptions\IdentificationNumberNotFoundException;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\CurrencyManagerAccessor;
use MatiCore\Invoice\ExpenseException;
use MatiCore\Invoice\ExpenseHelper;
use MatiCore\Invoice\InvoiceException;
use MatiCore\Invoice\InvoiceHelper;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\User;
use Nette\Utils\DateTime;

/**
 * Class SignEndpoint
 * @package App\Api
 * @public
 */
class ExpenseEndpoint extends BaseEndpoint
{

	/**
	 * @var User
	 * @inject
	 */
	public User $user;

	/**
	 * @var ExpenseHelper
	 * @inject
	 */
	public ExpenseHelper $expenseHelper;

	/**
	 * @var CompanyManagerAccessor
	 * @inject
	 */
	public CompanyManagerAccessor $companyManager;

	/**
	 * @var CurrencyManagerAccessor
	 * @inject
	 */
	public CurrencyManagerAccessor $currencyManager;

	/**
	 * @param string $id
	 */
	public function postLoadExpense(string $id): void
	{
		try {
			if ($id === '') {
				$expenseData = $this->expenseHelper->getNewExpense();
			} else {
				$expenseData = $this->expenseHelper->getExpenseById($id);
			}

			$this->sendOk([
				'expense' => $expenseData,
			]);
		} catch (NoResultException|NonUniqueResultException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param string $code
	 * @param array $expenseData
	 * @throws \Exception
	 */
	public function postLoadCurrency(string $code, array $expenseData): void
	{
		try {
			$currency = $this->currencyManager->get()->getCurrencyByIsoCode($isoCode);
			$currencyTemp = $this->currencyManager->get()->getCurrencyRateByDate($currency, DateTime::from($expenseData['date'] ?? 'NOW'));

			$this->sendOk([
				'currency' => [
					'id' => $currency->getId(),
					'code' => $currency->getCode(),
					'symbol' => $currency->getSymbol(),
					'rateReal' => $currencyTemp->getRate(),
					'rateRealString' => str_replace('.', ',', (string) $currencyTemp->getRate()),
					'rateDate' => $currencyTemp->getLastUpdate()->format('d.m.Y'),
				],
			]);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendError('Unknown currency');
		}
	}

	/**
	 * @param string $cin
	 */
	public function postLoadCompanyByCin(string $cin): void
	{
		try {
			$aresData = $this->companyManager->get()->getDataFromAres($cin);

			$this->sendOk([
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
			]);
		} catch (IdentificationNumberNotFoundException) {
			$this->sendOk([
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
			]);
		}
	}

	/**
	 * @param string $id
	 */
	public function postLoadSupplier(string $id): void
	{
		try {
			$customer = $this->expenseHelper->getSupplierData($id);

			$this->sendOk([
				'customer' => $customer,
			]);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendOk([
				'customer' => [
					'name' => '',
					'address' => '',
					'city' => '',
					'zipCode' => '',
					'country' => '',
					'ic' => '',
					'dic' => '',
				],
			]);
		}
	}

	/**
	 * @param array|null $expenseData
	 */
	public function postSave(?array $expenseData): void
	{
		try {
			$expenseData = $this->expenseHelper->saveExpense($expenseData);

			$this->sendOk([
				'expense' => $expenseData,
				'redirect' => $this->link(':Admin:Expense:show', ['id' => $expenseData['id']]),
			]);
		} catch (ExpenseException | EntityManagerException | NoResultException | NonUniqueResultException | CurrencyException | InvalidLinkException $e) {
			$this->sendError($e->getMessage());
		}
	}

}