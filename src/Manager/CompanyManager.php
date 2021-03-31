<?php

declare(strict_types=1);


namespace MatiCore\Company;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use h4kuna\Ares\Ares;
use h4kuna\Ares\Data;
use h4kuna\Ares\Exceptions\IdentificationNumberNotFoundException;
use MatiCore\Currency\Number;
use MatiCore\Invoice\InvoiceItem;
use Nette\Localization\Translator;

/**
 * Class CompanyManager
 * @package MatiCore\Company
 */
class CompanyManager
{

	/**
	 * @var EntityManager
	 */
	private EntityManager $entityManager;

	/**
	 * @var Translator
	 */
	private Translator $translator;

	/**
	 * CompanyManager constructor.
	 * @param EntityManager $entityManager
	 * @param Translator $translator
	 */
	public function __construct(EntityManager $entityManager, Translator $translator)
	{
		$this->entityManager = $entityManager;
		$this->translator = $translator;
	}

	/**
	 * @param string $id
	 * @return Company
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getCompanyById(string $id): Company
	{
		return $this->entityManager->getRepository(Company::class)
			->createQueryBuilder('company')
			->select('company')
			->where('company.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @param string $id
	 * @return CompanyStock
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getCompanyStockById(string $id): CompanyStock
	{
		return $this->entityManager->getRepository(CompanyStock::class)
			->createQueryBuilder('cs')
			->select('cs')
			->where('cs.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @param string $ico
	 * @return Company
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getCompanyByIco(string $ico): Company
	{
		return $this->entityManager->getRepository(Company::class)
			->createQueryBuilder('company')
			->select('company')
			->join('company.invoiceAddress', 'invoiceAddress')
			->where('invoiceAddress.ic = :ico')
			->setParameter('ico', $ico)
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
					->select('company')
					->orderBy('company.name', 'ASC')
					->getQuery()
					->getResult() ?? [];
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
					->getScalarResult() ?? [];

			foreach ($list as $company) {
				$cache[$company['id']] = $company['name'];
			}
		}

		return $cache;
	}

	/**
	 * @param string $ic
	 * @return Data
	 * @throws IdentificationNumberNotFoundException
	 */
	public function getDataFromAres(string $ic): Data
	{
		$ares = new Ares();

		return $ares->loadData($ic);
	}

	/**
	 * @return array
	 */
	public function getCompanyTypes(): array
	{
		static $list;

		if ($list === null) {
			$list = CompanyType::getList();

			foreach ($list as $key => $item) {
				$list[$key] = $this->translator->translate($item);
			}
		}

		return $list;
	}

	/**
	 * @return string
	 */
	public function getDefaultCompanyType(): string
	{
		return CompanyType::getDefault();
	}

	/**
	 * @param Company $company
	 * @throws CompanyException
	 */
	public function removeCompany(Company $company): void
	{
		try {
			$this->entityManager->remove($company)->flush();
		} catch (EntityManagerException $e) {
			CompanyException::isUsed();
		}
	}

	/**
	 * @param CompanyStock $companyStock
	 * @throws CompanyException
	 */
	public function removeCompanyStock(CompanyStock $companyStock): void
	{
		try {
			$this->entityManager->remove($companyStock)->flush();
		} catch (EntityManagerException $e) {
			CompanyException::isStockUsed();
		}
	}

	/**
	 * @param string $id
	 * @return CompanyContact
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getContactById(string $id): CompanyContact
	{
		return $this->entityManager->getRepository(CompanyContact::class)
			->createQueryBuilder('c')
			->select('c')
			->where('c.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @param Company $company
	 * @return InvoiceItem[]
	 */
	public function getInvoicedItems(Company $company): array
	{
		$list = [];

		foreach ($company->getInvoices() as $invoice) { //TODO reimplement me!!!
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

		usort($list, static function ($a, $b) {
			return strcmp($a['name'], $b['name']);
		});

		return $list;
	}

}