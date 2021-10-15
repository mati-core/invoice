<?php

declare(strict_types=1);


namespace MatiCore\Invoice\Command;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use MatiCore\Invoice\Invoice;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tracy\Debugger;

class InvoiceTaxListFixCommand extends Command
{
	private SymfonyStyle|null $io;


	public function __construct(
		private EntityManager $entityManager,
	) {
		parent::__construct();
	}


	protected function configure(): void
	{
		$this->setName('app:invoice:fixTaxList')
			->setDescription('Fix tax list in invoices.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		try {
			$this->io = new SymfonyStyle($input, $output);

			$output->writeln('==============================================');
			$output->writeln('           FIX INVOICE TAX LISTS              ');
			$output->writeln('');
			$output->writeln('');

			$output->writeln('loading invoices...');

			$invoiceList = $this->entityManager->getRepository(Invoice::class)->findAll();
			$count = count($invoiceList);

			$output->writeln('loading done (count: ' . $count . ')');

			$progress = new ProgressBar($output, $count);

			$i = 0;
			foreach ($invoiceList as $invoice) {
				$this->processFixInvoice($invoice);
				$i++;
				$progress->setProgress($i);
			}

			$output->writeln('');
			$output->writeln('Saving...');

			$this->entityManager->flush();

			$output->writeln('Saving done');

			$output->writeln('');
			$output->writeln('');
			$output->writeln('                   Finished                   ');
			$output->writeln('==============================================');
			$output->writeln('');
			$output->writeln('');

			return 0;
		} catch (\Throwable $e) {
			Debugger::log($e);
			$output->writeln('<error>' . $e->getMessage() . '</error>');

			return 1;
		}
	}


	/**
	 * @throws EntityManagerException
	 */
	private function processFixInvoice(Invoice $invoice): void
	{
		if (count($invoice->getTaxList()) === 0) {
			$taxTable = $invoice->getTaxTable();

			foreach ($taxTable as $invoiceTax) {
				$this->entityManager->persist($invoiceTax);
				$invoice->addTax($invoiceTax);
			}
		}
	}
}
