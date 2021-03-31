<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Address\Entity\Address;
use MatiCore\Address\Entity\Country;
use MatiCore\Currency\Currency;

/**
 * Class SupplierManager
 * @package MatiCore\Supplier
 */
class SupplierManager
{

	/**
	 * @var EntityManager
	 */
	private EntityManager $entityManager;

	/**
	 * SupplierManager constructor.
	 * @param EntityManager $entityManager
	 */
	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}

	/**
	 * @param string $id
	 * @return Supplier
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getSupplierById(string $id): Supplier
	{
		return $this->entityManager->getRepository(Supplier::class)
			->createQueryBuilder('supplier')
			->select('supplier')
			->where('supplier.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @return array
	 */
	public function getSuppliersForForm(): array
	{
		static $cache;

		if ($cache === null) {
			$items = [];

			foreach ($this->getSuppliers() as $supplier) {
				$items[$supplier->getId()] = $supplier->getName();
			}

			$cache = $items;
		}

		return $cache;
	}

	/**
	 * @return Supplier[]
	 */
	public function getSuppliers(): array
	{
		static $cache;

		if ($cache === null) {
			$cache = $this->entityManager->getRepository(Supplier::class)
					->createQueryBuilder('supplier')
					->select('supplier')
					->orderBy('supplier.name', 'ASC')
					->getQuery()
					->getResult() ?? [];
		}

		return $cache;
	}

	/**
	 * @param string $name
	 * @param Currency $currency
	 * @param string $street
	 * @param string $city
	 * @param Country $country
	 * @return Supplier
	 */
	public function createSupplier(string $name, Currency $currency, string $street, string $city, Country $country): Supplier
	{
		$address = new Address($street, $city);
		$address->setCountry($country);

		$this->entityManager->persist($address);

		$supplier = new Supplier($name, $currency, $address);

		$this->entityManager->persist($supplier)->flush();

		return $supplier;
	}

	/**
	 * @param Supplier $supplier
	 * @throws SupplierException
	 */
	public function removeSupplier(Supplier $supplier): void
	{
		try {
			$this->entityManager->remove($supplier)->flush();
		} catch (EntityManagerException $e) {
			SupplierException::isUsed();
		}
	}

}