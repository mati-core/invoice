<?php

declare(strict_types=1);

namespace MatiCore\Supplier;


use Baraja\Country\Entity\Country;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Address\Entity\Address;
use Baraja\Shop\Entity\Currency\Currency;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class SupplierManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getSupplierById(string $id): Supplier
	{
		return $this->entityManager->getRepository(Supplier::class)
			->createQueryBuilder('supplier')
			->where('supplier.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return array<int, string>
	 */
	public function getSuppliersForForm(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = [];
			foreach ($this->getAll() as $supplier) {
				$cache[$supplier->getId()] = $supplier->getName();
			}
		}

		return $cache;
	}


	/**
	 * @return array<int, Supplier>
	 */
	public function getAll(): array
	{
		static $cache;
		if ($cache === null) {
			$cache = $this->entityManager->getRepository(Supplier::class)
				->createQueryBuilder('supplier')
				->orderBy('supplier.name', 'ASC')
				->getQuery()
				->getResult();
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


	public function removeSupplier(Supplier $supplier): void
	{
		$this->entityManager->remove($supplier);
		$this->entityManager->flush();
	}
}
