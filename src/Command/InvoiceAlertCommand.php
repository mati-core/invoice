<?php

declare(strict_types=1);


namespace MatiCore\Invoice\Command;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use MatiCore\Constant\Exception\ConstantException;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\Number;
use MatiCore\Email\EmailerAccessor;
use MatiCore\Email\EmailException;
use MatiCore\Invoice\Email\InvoiceAlertOneEmail;
use MatiCore\Invoice\Email\InvoiceAlertThreeEmail;
use MatiCore\Invoice\Email\InvoiceAlertTwoEmail;
use MatiCore\Invoice\ExportManagerAccessor;
use MatiCore\Invoice\Invoice;
use MatiCore\Invoice\InvoiceCore;
use MatiCore\Invoice\InvoiceHistory;
use MatiCore\Invoice\InvoiceProforma;
use MatiCore\Invoice\InvoiceStatus;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tracy\Debugger;

class InvoiceAlertCommand extends Command
{
	private string $tempDir;

	/**
	 * @var array
	 */
	private array $params;

	private EntityManager $entityManager;

	private EmailerAccessor $emailEngine;

	private ExportManagerAccessor $exportManager;

	private SymfonyStyle|null $io;


	/**
	 * @param array $params
	 */
	public function __construct(
		string $tempDir,
		array $params,
		EntityManager $entityManager,
		EmailerAccessor $emailEngine,
		ExportManagerAccessor $exportManager
	) {
		parent::__construct();
		$this->tempDir = $tempDir;
		$this->params = $params;
		$this->entityManager = $entityManager;
		$this->emailEngine = $emailEngine;
		$this->exportManager = $exportManager;
	}


	protected function configure(): void
	{
		$this->setName('app:invoice:alert')
			->setDescription('Send alert for unpaid invoices.');
	}


	/**
	 * @throws \Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->io = new SymfonyStyle($input, $output);
		$this->io->newLine(2);
		$this->io->writeln('Checking unpaid invoices (date: ' . date('Y-m-d') . ')...');

		/** @var InvoiceCore[] $invoices */
		$invoices = $this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('i')
				->select('i')
				->where('i.payDate IS NULL')
				->orderBy('i.number', 'ASC')
				->getQuery()
				->getResult() ?? [];

		$rows = [];

		foreach ($invoices as $invoice) {
			if ($invoice instanceof Invoice || $invoice instanceof InvoiceProforma) {
				$dueDate = $invoice->getDueDate();
				$nowDate = DateTime::from('NOW');

				$date1 = $nowDate->modifyClone($this->params['alertEmail']['firstAlert']['sendAt']);
				$date2 = $nowDate->modifyClone($this->params['alertEmail']['secondAlert']['sendAt']);
				$date3 = $nowDate->modifyClone($this->params['alertEmail']['thirdAlert']['sendAt']);

				if ($invoice->getAcceptStatus1() === InvoiceStatus::ACCEPTED && $invoice->getAcceptStatus2(
					) === InvoiceStatus::ACCEPTED) {
					$payStatus = $invoice->getPayAlertStatus();
					try {
						if (
							$dueDate <= $date1
							&& (
								$payStatus === InvoiceStatus::PAY_ALERT_NONE
								|| $payStatus === ''
							)
						) {
							$this->sendAlertOne($invoice);
							$status = '1. upomínka';
						} elseif (
							$dueDate <= $date2
							&& $payStatus === InvoiceStatus::PAY_ALERT_ONE
						) {
							$this->sendAlertTwo($invoice);
							$status = '2. upomínka';
						} elseif (
							$dueDate <= $date3
							&& $payStatus === InvoiceStatus::PAY_ALERT_TWO
						) {
							$this->sendAlertThree($invoice);
							$status = '3. upomínka';
						} else {
							$status = 'Čeká';
						}
					} catch (ConstantException | EmailException | EntityManagerException $e) {
						Debugger::log($e);
						$status = 'Chyba';
					}
				} else {
					$status = 'Neschváleno';
				}


				$rows[] = [
					$invoice->getNumber(),
					$invoice->getDueDate()->format('Y-m-d'),
					$status,
				];
			}
		}

		$table = new Table($output);
		$table->setHeaders(['Faktura', 'Splatnost', 'Stav']);
		$table->addRows($rows);
		$table->render();

		$this->io->newLine(4);

		return 0;
	}


	/**
	 * @throws ConstantException
	 * @throws EmailException
	 */
	private function sendAlert(int $numberOfAlert, Invoice|InvoiceProforma $invoice): void
	{
		$production = (bool) ($this->params['alertEmail']['production'] ?? false);
		$sender = $this->params['alertEmail']['email'] ?? 'faktury@app-universe.cz';
		$senderName = $this->params['alertEmail']['email'] ?? 'APP Universe';
		$replyTo = $this->params['alertEmail']['replyTo'] ?? $sender;

		if ($production === false) {
			$emails = [
				'info@app-univers.cz',
			];
		} else {
			$emails = $invoice->getEmailList();

			if (is_array($this->params['alertEmail']['copy'])) {
				foreach ($this->params['alertEmail']['copy'] as $email) {
					$emails[] = $email;
				}
			}
		}

		if ($numberOfAlert === 3) {
			$newDueDate = DateTime::from($invoice->getDueDate())->modify(
				$this->params['alertEmail']['thirdAlert']['dueDate']
			);
			$name = $this->params['export']['alertThree']['filename'] . $invoice->getNumber() . '.pdf';
			$emailType = InvoiceAlertThreeEmail::class;
		} elseif ($numberOfAlert === 2) {
			$newDueDate = DateTime::from($invoice->getDueDate())->modify(
				$this->params['alertEmail']['secondAlert']['dueDate']
			);
			$name = $this->params['export']['alertTwo']['filename'] . $invoice->getNumber() . '.pdf';
			$emailType = InvoiceAlertTwoEmail::class;
		} else {
			$newDueDate = DateTime::from($invoice->getDueDate())->modify(
				$this->params['alertEmail']['firstAlert']['dueDate']
			);
			$name = $this->params['export']['alertOne']['filename'] . $invoice->getNumber() . '.pdf';
			$emailType = InvoiceAlertOneEmail::class;
		}

		$attachments = [];

		// Upominka
		try {
			$tmp = $this->tempDir . '/' . $name;
			$this->exportManager->get()->exportInvoiceAlertToPDF(
				$numberOfAlert, $invoice, $newDueDate, Destination::FILE, $tmp
			);
			$attachments[] = [
				'file' => $tmp,
				'name' => $name,
			];
		} catch (MpdfException $e) {
			Debugger::log($e);

			return;
		}

		// Faktura do prilohy
		if ($invoice instanceof InvoiceProforma) {
			$name = $this->params['export']['proforma']['filename'] . $invoice->getNumber() . '.pdf';
		} else {
			$name = $this->params['export']['invoice']['filename'] . $invoice->getNumber() . '.pdf';
		}

		try {
			$tmp = $this->tempDir . '/' . $name;
			$this->exportManager->get()->exportInvoiceToPDF($invoice, Destination::FILE, $tmp);
			$attachments[] = [
				'file' => $tmp,
				'name' => $name,
			];
		} catch (MpdfException | CurrencyException $e) {
			Debugger::log($e);

			return;
		}

		foreach ($emails as $recipient) {
			$recipient = trim($recipient);
			if ($recipient !== null && $recipient !== '') {
				$email = $this->emailEngine->get()->getEmailServiceByType(
					$emailType, [
						'from' => $senderName . ' <' . $sender . '>',
						'to' => $recipient,
						'replyTo' => $replyTo,
						'subject' => $numberOfAlert . '. upomínka - Faktura č.: ' . $invoice->getNumber(),
						'number' => $invoice->getNumber(),
						'totalPrice' => str_replace(
							' ',
							'&nbsp;',
							Number::formatPrice($invoice->getTotalPrice(), $invoice->getCurrency(), 2)
						),
						'newDueDate' => $newDueDate->format('d.m.Y'),
					]
				);

				foreach ($attachments as $attachment) {
					$email->getMessage()->addAttachmentPath($attachment['file'], $attachment['name']);
				}

				$email->send();

				$ih = new InvoiceHistory($invoice, 'Odeslána ' . $numberOfAlert . '. upomínka na ' . $recipient);
				$ih->setUser(null);
				$this->entityManager->persist($ih);

				$invoice->addHistory($ih);
				$invoice->setStatus(InvoiceStatus::PAY_ALERT_ONE);
				$invoice->setPayAlertStatus(InvoiceStatus::PAY_ALERT_ONE);
				$this->entityManager->flush();
			}
		}
	}


	/**
	 * @throws ConstantException|EmailException
	 */
	private function sendAlertOne(Invoice|InvoiceProforma $invoice): void
	{
		$this->sendAlert(1, $invoice);
	}


	/**
	 * @throws ConstantException|EmailException
	 */
	private function sendAlertTwo(Invoice|InvoiceProforma $invoice): void
	{
		$this->sendAlert(2, $invoice);
	}


	/**
	 * @throws ConstantException|EmailException
	 */
	private function sendAlertThree(Invoice|InvoiceProforma $invoice): void
	{
		$this->sendAlert(3, $invoice);
	}
}
