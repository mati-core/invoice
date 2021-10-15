<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


use Baraja\Country\Entity\Country;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Address\Entity\Address;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class SupplierManager
{
	private EntityManager $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
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


	public function createSupplier(
		string $name,
		Currency $currency,
		string $street,
		string $city,
		Country $country
	): Supplier {
		$address = new Address($country, $name, null, $street, $city);

		$this->entityManager->persist($address);
		$supplier = new Supplier($name, $currency, $address);
		$this->entityManager->persist($supplier);
		$this->entityManager->flush();

		return $supplier;
	}


	/**
	 * @throws SupplierException
	 */
	public function removeSupplier(Supplier $supplier): void
	{
		try {
			$this->entityManager->remove($supplier);
			$this->entityManager->flush();
		} catch (EntityManagerException) {
			SupplierException::isUsed();
		}
	}
}
