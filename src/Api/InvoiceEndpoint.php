<?php

declare(strict_types=1);

namespace App\Api;


use Baraja\Doctrine\DatabaseException;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Currency\CurrencyManagerAccessor;
use MatiCore\Invoice\InvoiceException;
use MatiCore\Invoice\InvoiceHelper;
use Nette\Application\LinkGenerator;
use Nette\Security\User;
use Nette\Utils\DateTime;

/**
 * Class SignEndpoint
 * @package App\Api
 * @public
 */
class InvoiceEndpoint extends BaseEndpoint
{

	/**
	 * @var User
	 * @inject
	 */
	public $user;

	/**
	 * @var InvoiceHelper
	 * @inject
	 */
	public $invoiceHelper;

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
	public function postLoadInvoice(string $id): void
	{
		try {
			if ($id === '' || $id === null) {
				$invoiceData = $this->invoiceHelper->getNewInvoice();
			} else {
				$invoiceData = $this->invoiceHelper->getInvoiceById($id);
			}

			$this->sendOk([
				'invoice' => $invoiceData,
			]);
		} catch (InvoiceException | CurrencyException | EntityManagerException | UnitException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param array|null $data
	 * @return ResponseInterface
	 */
	public function postLoadFixInvoice(string $id): void
	{
		try {
			$invoiceData = $this->invoiceHelper->getFixInvoiceById($id);

			$this->sendOk([
				'invoice' => $invoiceData,
			]);
		} catch (EntityManagerException|UnitException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param array $data
	 * @return ResponseInterface
	 */
	public function postDepositInvoice(array $invoiceData, string $depositNumber): ResponseInterface
	{
		$depositNumber = trim($depositNumber);

		try {
			$invoiceData = $this->invoiceHelper->addDepositInvoice($invoiceData, $depositNumber);

			$this->sendOk([
				'invoice' => $invoiceData,
			]);
		} catch (InvoiceException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param string $id
	 */
	public function postLoadCompanyById(string $id): void
	{
		try {
			$company = $this->companyManager->get()->getCompanyById($id);
			$now = DateTime::from('NOW');

			$dueSelect = $company->getInvoiceDueDayCount();

			if (!in_array($dueSelect, [0, 7, 10, 14, 30])) {
				$dueSelect = '';
			}

			$this->sendOk([
				'customer' => [
					'id' => $company->getId(),
					'name' => $company->getName(),
					'address' => $company->getInvoiceAddress()->getStreet(),
					'city' => $company->getInvoiceAddress()->getCity(),
					'zipCode' => $company->getInvoiceAddress()->getZipCode(),
					'country' => $company->getInvoiceAddress()->getCountry()->getIsoCode(),
					'cin' => $company->getInvoiceAddress()->getCin(),
					'tin' => $company->getInvoiceAddress()->getTin(),
				],
				'currency' => $company->getCurrency()->getCode(),
				'date' => $now->format('Y-m-d'),
				'dateTax' => $now->format('Y-m-d'),
				'dateDue' => $now->modify('+' . $company->getInvoiceDueDayCount() . ' days')->format('Y-m-d'),
				'dateDueSelect' => $dueSelect,
			]);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->sendError([
				'customer' => [
					'id' => null,
					'name' => '',
					'address' => '',
					'city' => '',
					'zipCode' => '',
					'country' => 'CZE',
					'cin' => '',
					'tin' => '',
				],
				'currency' => 'CZK',
			]);
		}
	}

	/**
	 * @param array $invoiceData
	 */
	public function postReloadInvoiceNumber(array $invoiceData): void
	{
		try {
			$invoiceData = $this->invoiceHelper->reloadInvoiceNumber($invoiceData);

			$this->sendOk([
				'invoice' => $invoiceData,
			]);
		} catch (InvoiceException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param string $isoCode
	 * @param array $invoiceData
	 */
	public function postLoadCurrency(string $isoCode, array $invoiceData): void
	{
		try {
			$currency = $this->currencyManager->get()->getCurrencyByIsoCode($isoCode);
			$currencyTemp = $this->currencyManager->get()->getCurrencyRateByDate(
				$currency,
				DateTime::from($invoiceData['dateTax'] ?? 'NOW')
			);

			$this->sendOk([
				'currency' => [
					'id' => $currency->getId(),
					'code' => $currency->getCode(),
					'symbol' => $currency->getSymbol(),
					'rate' => $currencyTemp->getRate(),
					'rateDate' => $currencyTemp->getLastUpdate()->format('d.m.Y'),
				],
			]);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->sendError($e->getMessage());
		}
	}

	/**
	 * @param array $invoiceData
	 */
	public function postSave(array $invoiceData): void
	{
		try {
			$invoiceData = $this->invoiceHelper->saveInvoice($invoiceData);

			$this->sendOk([
				'invoice' => $invoiceData,
				'redirect' => $this->link('Admin:Invoice:show', ['id' => $invoiceData['id']]),
			]);
		} catch (EntityManagerException | UnitException | CurrencyException $e) {
			$this->sendError($e->getMessage());
		}
	}

}