<?php

declare(strict_types=1);

namespace App\Api;


use Baraja\Doctrine\EntityManagerException;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Invoice\InvoiceException;

#[PublicEndpoint]
class InvoiceEndpoint extends BaseEndpoint
{
	public function __construct(
		private $user,
		private $invoiceHelper,
		private CompanyManagerAccessor $companyManager,
		private CurrencyManagerAccessor $currencyManager,
	) {
	}


	public function postLoadInvoice(string $id): void
	{
		try {
			if ($id === '') {
				$invoiceData = $this->invoiceHelper->getNewInvoice();
			} else {
				$invoiceData = $this->invoiceHelper->getInvoiceById($id);
			}

			$this->sendJson(
				[
					'invoice' => $invoiceData,
				]
			);
		} catch (InvoiceException | CurrencyException | EntityManagerException | UnitException $e) {
			$this->sendError($e->getMessage());
		}
	}


	public function postLoadFixInvoice(string $id): void
	{
		try {
			$invoiceData = $this->invoiceHelper->getFixInvoiceById($id);
			$this->sendJson(
				[
					'invoice' => $invoiceData,
				]
			);
		} catch (EntityManagerException | UnitException $e) {
			$this->sendError($e->getMessage());
		}
	}


	public function postDepositInvoice(array $invoiceData, string $depositNumber): void
	{
		$depositNumber = trim($depositNumber);
		try {
			$this->sendJson(
				[
					'invoice' => $this->invoiceHelper->addDepositInvoice($invoiceData, $depositNumber),
				]
			);
		} catch (InvoiceException $e) {
			$this->sendError($e->getMessage());
		}
	}


	/**
	 * @throws \Exception
	 */
	public function postLoadCompanyById(string $id): void
	{
		try {
			$company = $this->companyManager->get()->getCompanyById($id);
			$now = new \DateTime;
			$dueSelect = $company->getInvoiceDueDayCount();
			if (!in_array($dueSelect, [0, 7, 10, 14, 30])) {
				$dueSelect = '';
			}

			$depositList = $this->invoiceHelper->getDepositList($company);
			$this->sendJson(
				[
					'customer' => [
						'id' => $company->getId(),
						'name' => $company->getName(),
						'address' => $company->getInvoiceAddress()->getStreet(),
						'city' => $company->getInvoiceAddress()->getCity(),
						'zipCode' => $company->getInvoiceAddress()->getZipCode(),
						'country' => $company->getInvoiceAddress()->getCountry()->getIsoCode(),
						'cin' => $company->getInvoiceAddress()->getCin(),
						'tin' => $company->getInvoiceAddress()->getTin(),
						'depositList' => $depositList,
					],
					'currency' => $company->getCurrency()->getCode(),
					'date' => $now->format('Y-m-d'),
					'dateTax' => $now->format('Y-m-d'),
					'dateDue' => $now->modify('+' . $company->getInvoiceDueDayCount() . ' days')->format('Y-m-d'),
					'dateDueSelect' => $dueSelect,
				]
			);
		} catch (NoResultException | NonUniqueResultException) {
			$this->sendJson(
				[
					'customer' => [
						'id' => null,
						'name' => '',
						'address' => '',
						'city' => '',
						'zipCode' => '',
						'country' => 'CZE',
						'cin' => '',
						'tin' => '',
						'depositList' => [],
					],
					'currency' => 'CZK',
				]
			);
		}
	}


	/**
	 * @throws \Exception
	 */
	public function postLoadCompanyByCIN(string $cin): void
	{
		$now = new \DateTime;

		try {
			$company = $this->companyManager->get()->getCompanyByCIN($cin);
			$dueSelect = $company->getInvoiceDueDayCount();

			if (!in_array($dueSelect, [0, 7, 10, 14, 30])) {
				$dueSelect = '';
			}

			$depositList = $this->invoiceHelper->getDepositList($company);

			$this->sendJson(
				[
					'customer' => [
						'id' => $company->getId(),
						'name' => $company->getName(),
						'address' => $company->getInvoiceAddress()->getStreet(),
						'city' => $company->getInvoiceAddress()->getCity(),
						'zipCode' => $company->getInvoiceAddress()->getZipCode(),
						'country' => $company->getInvoiceAddress()->getCountry()->getIsoCode(),
						'cin' => $company->getInvoiceAddress()->getCin(),
						'tin' => $company->getInvoiceAddress()->getTin(),
						'depositList' => $depositList,
					],
					'currency' => $company->getCurrency()->getCode(),
					'date' => $now->format('Y-m-d'),
					'dateTax' => $now->format('Y-m-d'),
					'dateDue' => $now->modify('+' . $company->getInvoiceDueDayCount() . ' days')->format('Y-m-d'),
					'dateDueSelect' => $dueSelect,
				]
			);
		} catch (NoResultException | NonUniqueResultException) {
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
							'cin' => $aresData->in,
							'tin' => $aresData->tin,
							'depositList' => [],
						],
						'currency' => 'CZK',
						'date' => $now->format('Y-m-d'),
						'dateTax' => $now->format('Y-m-d'),
						'dateDue' => $now->modify('+14 days')->format('Y-m-d'),
						'dateDueSelect' => 14,
					]
				);
			} catch (IdentificationNumberNotFoundException $e) {
				$this->sendJson(
					[
						'customer' => [
							'id' => null,
							'name' => '',
							'address' => '',
							'city' => '',
							'zipCode' => '',
							'country' => 'CZE',
							'cin' => '',
							'tin' => '',
							'depositList' => [],
						],
						'currency' => 'CZK',
						'date' => $now->format('Y-m-d'),
						'dateTax' => $now->format('Y-m-d'),
						'dateDue' => $now->modify('+14 days')->format('Y-m-d'),
						'dateDueSelect' => 14,
					]
				);
			}
		}
	}


	/**
	 * @param array $invoiceData
	 */
	public function postReloadInvoiceNumber(array $invoiceData): void
	{
		try {
			$invoiceData = $this->invoiceHelper->reloadInvoiceNumber($invoiceData);

			$this->sendJson(
				[
					'invoice' => $invoiceData,
				]
			);
		} catch (InvoiceException $e) {
			$this->sendError($e->getMessage());
		}
	}


	/**
	 * @param array $invoiceData
	 */
	public function postLoadCurrency(string $isoCode, array $invoiceData): void
	{
		try {
			$currency = $this->currencyManager->get()->getCurrencyByIsoCode($isoCode);
			$currencyTemp = $this->currencyManager->get()->getCurrencyRateByDate(
				$currency,
				new \DateTime($invoiceData['dateTax'] ?? 'NOW')
			);

			$this->sendJson(
				[
					'currency' => [
						'id' => $currency->getId(),
						'code' => $currency->getCode(),
						'symbol' => $currency->getSymbol(),
						'rate' => $currencyTemp->getRate(),
						'rateDate' => $currencyTemp->getLastUpdate()->format('d.m.Y'),
					],
				]
			);
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

			$this->sendJson(
				[
					'invoice' => $invoiceData,
					'redirect' => $this->link('Admin:Invoice:show', ['id' => $invoiceData['id']]),
				]
			);
		} catch (EntityManagerException | UnitException | CurrencyException $e) {
			$this->sendError($e->getMessage());
		}
	}
}
