<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class BankMovementManager
{
	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getById(string $id): BankMovement
	{
		return $this->entityManager->getRepository(BankMovement::class)
			->createQueryBuilder('bm')
			->where('bm.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}
}
