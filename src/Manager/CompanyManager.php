<?php

declare(strict_types=1);

namespace MatiCore\Company;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Invoice\Invoice;
use Nette\Localization\Translator;

class CompanyManager
{
	private EntityManager $entityManager;

	private Translator $translator;


	public function __construct(EntityManager $entityManager, Translator $translator)
	{
		$this->entityManager = $entityManager;
		$this->translator = $translator;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getCompanyById(string $id): Company
	{
		return $this->entityManager->getRepository(Company::class)
			->createQueryBuilder('company')
			->where('company.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getCompanyStockById(string $id): CompanyStock
	{
		return $this->entityManager->getRepository(CompanyStock::class)
			->createQueryBuilder('cs')
			->where('cs.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getCompanyByCIN(string $cin): Company
	{
		return $this->entityManager->getRepository(Company::class)
			->createQueryBuilder('company')
			->join('company.invoiceAddress', 'invoiceAddress')
			->where('invoiceAddress.cin = :cin')
			->setParameter('cin', $cin)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return Company[]
	 */
	public function getCompanies(): array
	{
		static $cache;

		if ($cache === null) {
			$cache = $this->entityManager->getRepository(Company::class)
					->createQueryBuilder('company')
					->orderBy('company.name', 'ASC')
					->getQuery()
					->getResult();
		}

		return $cache;
	}


	/**
	 * @return array
	 */
	public function getCompaniesForForm(): array
	{
		static $cache;

		if ($cache === null) {
			$cache = [];
			$list = $this->entityManager->getRepository(Company::class)
					->createQueryBuilder('company')
					->select('company.id as id, company.name as name')
					->orderBy('company.name', 'ASC')
					->getQuery()
					->getScalarResult();

			foreach ($list as $company) {
				$cache[$company['id']] = $company['name'];
			}
		}

		return $cache;
	}


	/**
	 * @throws IdentificationNumberNotFoundException
	 */
	public function getDataFromAres(string $in): Data
	{
		return (new Ares)->loadData($in);
	}


	/**
	 * @return array<string, string>
	 */
	public function getCompanyTypes(): array
	{
		static $list;
		if ($list === null) {
			$list = [];
			foreach (CompanyType::LIST as $key => $item) {
				$list[$key] = $this->translator->translate($item);
			}
		}

		return $list;
	}


	public function getDefaultCompanyType(): string
	{
		return CompanyType::STANDARD;
	}


	public function removeCompany(Company $company): void
	{
		try {
			$this->entityManager->remove($company);
			$this->entityManager->flush();
		} catch (EntityManagerException) {
			CompanyException::isUsed();
		}
	}


	public function removeCompanyStock(CompanyStock $companyStock): void
	{
		try {
			$this->entityManager->remove($companyStock);
			$this->entityManager->flush();
		} catch (EntityManagerException) {
			CompanyException::isStockUsed();
		}
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getContactById(string $id): CompanyContact
	{
		return $this->entityManager->getRepository(CompanyContact::class)
			->createQueryBuilder('c')
			->where('c.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function getInvoicedItems(Company $company): array
	{
		/** @var Invoice[] $invoices */
		$invoices = $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('invoice')
				->where('invoice.company = :company')
				->setParameter('company', $company->getId())
				->orderBy('invoice.date', 'DESC')
				->getQuery()
				->getResult();

		$list = [];
		foreach ($invoices as $invoice) {
			if ($invoice->isDeleted() === false) {
				foreach ($invoice->getItems() as $item) {
					$hash = md5($item->getDescription() . '-' . $item->getPricePerItem());

					if (!isset($list[$hash])) {
						$list[$hash] = [
							'code' => $item->getCode(),
							'name' => $item->getDescription(),
							'price' => Number::formatPrice($item->getPricePerItem(), $invoice->getCurrency()),
							'invoice' => [
								'id' => $invoice->getId(),
								'number' => $invoice->getNumber(),
							],
						];
					} else {
						$list[$hash]['invoice'] = [
							'id' => $invoice->getId(),
							'number' => $invoice->getNumber(),
						];
					}
				}
			}
		}

		usort(
			$list,
			static function ($a, $b)
			{
				return strcmp($a['name'], $b['name']);
			}
		);

		return $list;
	}
}
