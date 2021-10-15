<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Tracy\Debugger;

class ExpenseManager
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
	public function getExpenseById(string $id): Expense
	{
		return $this->entityManager->getRepository(Expense::class)
			->createQueryBuilder('e')
			->select('e')
			->where('e.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @return ExpenseHistory[]
	 */
	public function getHistory(Expense $expense): array
	{
		return $this->entityManager->getRepository(ExpenseHistory::class)
				->createQueryBuilder('eh')
				->select('eh')
				->where('eh.expense = :id')
				->setParameter('id', $expense->getId())
				->orderBy('eh.date', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}


	public function getNextNumber(): string
	{
		$date = date('Y') . '-' . date('m') . '-01';
		try {
			$count = $this->entityManager->getRepository(Expense::class)
					->createQueryBuilder('e')
					->select('count(e)')
					->where('e.createDate > :date')
					->setParameter('date', $date)
					->getQuery()
					->getSingleScalarResult() ?? 0;
		} catch (NoResultException | NonUniqueResultException $e) {
			Debugger::log($e);
			$count = 0;
		}

		$count++;
		$countString = (string) $count;
		while (strlen($countString) < 4) {
			$countString = '0' . $countString;
		}

		return 'PF' . date('y') . date('m') . $countString;
	}
}
