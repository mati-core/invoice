<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

class BankMovementManager
{
	private EntityManager $entityManager;


	public function __construct(EntityManager $entityManager)
	{
		$this->entityManager = $entityManager;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getById(string $id): BankMovement
	{
		return $this->entityManager->getRepository(BankMovement::class)
			->createQueryBuilder('bm')
			->select('bm')
			->where('bm.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}
}
