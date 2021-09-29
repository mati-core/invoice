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
	 * @param string $id
	 * @return BankMovement
	 * @throws NoResultException
	 * @throws NonUniqueResultException
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
