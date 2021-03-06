<?php

declare(strict_types=1);


namespace MatiCore\Invoice\Command;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Invoice\BankMailException;
use MatiCore\Invoice\BankMovement;
use MatiCore\Invoice\BankMovementCronLogAccessor;
use MatiCore\Invoice\Invoice;
use MatiCore\Invoice\InvoiceException;
use MatiCore\Invoice\InvoiceHistory;
use MatiCore\Invoice\InvoiceManagerAccessor;
use Nette\Application\LinkGenerator;
use Nette\Utils\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Tracy\Debugger;

class InvoicePayCheckCommand extends Command
{
	/** @var array */
	private array $allowedSenders;

	private SymfonyStyle $io;


	/**
	 * @param array $params
	 */
	public function __construct(
		private string $tempDir,
		private array $params,
		private EntityManager $entityManager,
		private CurrencyManagerAccessor $currencyManager,
		private LinkGenerator $linkGenerator,
		private InvoiceManagerAccessor $invoiceManager,
		private BankMovementCronLogAccessor $logger
	) {
		parent::__construct();
		$this->allowedSenders = $params['payEmail']['allowedSenders'] ?? [];
	}


	/**
	 * @return array
	 * @throws BankMailException
	 */
	public function parseDataEUR(string $content): array
	{
		$data = [];
		$lines = explode("\n", $content);
		if (preg_match('/^dne\s(\d+\.\d+\.\d{4})\sbyl\sna\súčtu\s(\d+)/u', $lines[0], $m) && isset($m[1], $m[2])) {
			$data['date'] = new \DateTime($m[1] . ' 00:00:00');
			$data['bankAccount'] = $m[2] . '/0300';
		} else {
			throw new BankMailException('Can not parse date and bank account from line 3.');
		}
		if (preg_match('/^Částka:\s\+([\d|\s]+,\d+)\s([A-Z]{3})/u', $lines[6], $m) && isset($m[1], $m[2])) {
			$price = str_replace(["\xc2\xa0", ','], ['', '.'], $m[1]);
			$data['price'] = (float) $price;
			$data['currencyCode'] = $m[2];
		} else {
			throw new BankMailException('Can not parse price and currency from line 9.');
		}
		if (
			preg_match(
				'/^Účet\sprotistrany:\s([A-Z]{2}\d{2}\s?\d{0,4}\s?\d{0,4}\s?\d{0,4}\s?\d{0,4}\s?\d{0,4})/u',
				$lines[7],
				$m
			) && isset($m[1])
		) {
			$data['customerBankAccount'] = $m[1];
		} else {
			throw new BankMailException(
				'Can not parse customer bank account from line 10. LINE:' . $lines[7] . '|' . $data['date']->format(
					'Y-m-d'
				)
			);
		}
		if (preg_match('/^Název\sprotistrany:\s(.*)/u', $lines[8], $m) && isset($m[1])) {
			$data['customerName'] = $m[1];
		} else {
			throw new BankMailException('Can not parse customer name from line 11.');
		}

		$continue = true;
		$limit = 10;
		$line = 9;

		do {
			if (preg_match('/^Číslo\stransakce\sČSOB:[\D]*(\d+)/u', $lines[$line], $m) && isset($m[1])) {
				$data['transactionID'] = $m[1];
			}
			if (preg_match('/^Účel\splatby:[\D]*(\d+)/u', $lines[$line], $m) && isset($m[1])) {
				$data['variableSymbol'] = $m[1];
				$continue = false;
			}

			$line++;
			$limit--;
		} while ($continue && $limit > 0 && isset($lines[$line]));

		if (!isset($data['variableSymbol'])) {
			$data['variableSymbol'] = $data['transactionID']
				?? md5(
					$data['date']
					. $data['price']
					. $data['currencyCode']
					. $data['customerBankAccount']
					. $data['customerName']
				);
		}

		return $data;
	}


	protected function configure(): void
	{
		$this->setName('invoice:pay')
			->setDescription('Check invoice paid.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->io = new SymfonyStyle($input, $output);
		try {
			$output->writeln('==============================================');
			$output->writeln('                Invoice Pay check             ');
			$output->writeln('');
			$output->writeln('');

			$server = '{' . $this->params['payEmail']['server'] . ':993/imap/ssl/novalidate-cert}INBOX';
			if (!is_dir($this->tempDir . '/imap')) {
				FileSystem::createDir($this->tempDir . '/imap');
			}

			$mailBox = new Mailbox(
				$server,
				$this->params['payEmail']['login'],
				$this->params['payEmail']['password'],
				$this->tempDir . '/imap',
				'UTF-8'
			);

			$output->writeln('Connecting...');

			$date = new \DateTime;
			$date->modify('-7 days');

			$mailsIds = $mailBox->searchMailbox('SINCE "' . $date->format('Y-m-d') . '"');

			$output->writeln('Connected');
			$output->writeln('Mail count: ' . count($mailsIds));

			if (count($mailsIds) > 0) {
				foreach ($mailsIds as $mailId) {
					try {
						$output->writeln('Email: ' . $mailId);
						$mail = $mailBox->getMail($mailId);
						$this->processEmail($mail);
						$output->writeln('Email: ' . $mailId . ' DONE');
					} catch (BankMailException $e) {
						Debugger::log($e);
						$this->io->error($e->getMessage());
					}
				}
			}

			$mailBox->disconnect();
			$output->writeln('Disconnected...');
			$output->writeln('');
			$output->writeln('');
			$output->writeln('                     DONE                    ');
			$output->writeln(' ==============================================');

			$this->logger->setLog(true);

			return 0;
		} catch (Throwable $e) {
			Debugger::log($e);
			$output->writeln('<error>' . $e->getMessage() . '</error>');

			$this->logger->setLog(false);

			return 1;
		}
	}


	private function processEmail(IncomingMail $mail): void
	{
		$from = $mail->fromAddress;

		if (!in_array($from, $this->allowedSenders, true)) {
			throw new BankMailException('Email address ' . $from . ' not allowed!');
		}

		$lines = explode("\n", $mail->textPlain);
		$payCount = 0;
		foreach ($lines as $key => $line) {
			if (preg_match('/^dne\s(\d+\.\d+\.\d{4})\sbyla\sna\súčtu\s(\d+)/u', $line, $m) && isset($m[1], $m[2])) {
				$payCount++;
				$content = $line;
				$lineNumber = $key;
				$lineNumber++;
				while (isset($lines[$lineNumber]) && !preg_match('/^Zůstatek/u', $lines[$lineNumber])) {
					$content .= "\n" . $lines[$lineNumber];
					$lineNumber++;
				}

				$data = $this->parseData($content);
				$data['messageId'] = sha1($mail->messageId);
				$this->addBankMovement($data);
			} elseif (preg_match(
					'/^dne\s(\d+\.\d+\.\d{4})\sbyl\sna\súčtu\s(\d+)/u', $line, $m
				) && isset($m[1], $m[2])) {
				$payCount++;
				$content = $line;
				$lineNumber = $key;
				$lineNumber++;
				while (isset($lines[$lineNumber]) && !preg_match('/^Zůstatek/u', $lines[$lineNumber])) {
					$content .= "\n" . $lines[$lineNumber];
					$lineNumber++;
				}

				$data = $this->parseDataEUR($content);
				$data['messageId'] = sha1($mail->messageId);
				$this->addBankMovement($data);
			}
		}

		$this->io->writeln('Pay count: ' . $payCount);
	}


	/**
	 * @return array
	 * @throws BankMailException
	 */
	private function parseData(string $content): array
	{
		$data = [];
		$data['bankAccountName'] = 'Unknown Bank name';

		$lines = explode("\n", $content);
		if (preg_match('/^dne\s(\d+\.\d+\.\d{4})\sbyla\sna\súčtu\s(\d+)/u', $lines[0], $m) && isset($m[1], $m[2])) {
			$data['date'] = new \DateTime($m[1] . ' 00:00:00');
			$data['bankAccount'] = $m[2] . '/0300';
		} else {
			throw new BankMailException('Can not parse date and bank account from line 3.');
		}

		if (preg_match('/^Částka:\s\+([\d|\s]+,\d+)\s([A-Z]{3})/u', $lines[6], $m) && isset($m[1], $m[2])) {
			$price = str_replace(["\xc2\xa0", ','], ['', '.'], $m[1]);
			$data['price'] = (float) $price;
			$data['currencyCode'] = $m[2];
		} else {
			throw new BankMailException(
				'Can not parse price and currency from line 9. | ' . $lines[6] . ' | ' . $data['date']->format('Y-m-d')
			);
		}

		if (preg_match('/^Účet\sprotistrany:\s(\d+\/\d+)/u', $lines[7], $m) && isset($m[1])) {
			$data['customerBankAccount'] = $m[1];
		} elseif (preg_match('/^Účet\sprotistrany:\s(\d+-\d+\/\d+)/u', $lines[7], $m) && isset($m[1])) {
			$data['customerBankAccount'] = $m[1];
		} else {
			throw new BankMailException('Can not parse customer bank account from line 10.');
		}

		if (preg_match('/^Název\sprotistrany:\s(.*)/u', $lines[8], $m) && isset($m[1])) {
			$data['customerName'] = $m[1];
		} else {
			throw new BankMailException('Can not parse customer name from line 11.');
		}

		if (preg_match('/^Variabilní\ssymbol:\s(\d+)/u', $lines[9], $m) && isset($m[1])) {
			$vs = $m[1];
			while ($vs[0] === '0') {
				$vs = substr($vs, 1);
			}
			$data['variableSymbol'] = $vs;
		} else {
			throw new BankMailException('Can not parse variable symbol from line 12.');
		}

		$line = 10;
		if (preg_match('/^Konstantní\ssymbol:\s(\d+)/u', $lines[10], $m) && isset($m[1])) {
			$data['constantSymbol'] = $m[1];
			$line = 11;
		}

		$continue = true;
		$limit = 10;
		$first = true;
		do {
			if ($first === true) {
				$first = false;
				if (preg_match('/^Zpráva\spříjemci:\s(.*)/u', $lines[$line], $m) && isset($m[1])) {
					$data['message'] = $m[1];
				} else {
					$continue = false;
				}
			} else {
				$txt = trim($lines[$line]);
				if ($txt === '') {
					$continue = false;
				} else {
					$data['message'] .= ' ' . $lines[$line];
				}
			}
			$line++;
			$limit--;
		} while ($continue && $limit > 0);

		return $data;
	}


	/**
	 * @param array $data
	 */
	private function addBankMovement(array $data): void
	{
		try {
			$this->entityManager->getRepository(BankMovement::class)
				->createQueryBuilder('bm')
				->where('bm.variableSymbol = :id')
				->setParameter('id', $data['variableSymbol'])
				->getQuery()
				->getSingleResult();

			$this->io->note('Skipped bank movement already exists.');
		} catch (NoResultException | NonUniqueResultException $e) {
			try {
				$currency = $this->currencyManager->get()->getCurrencyByIsoCode($data['currencyCode']);
			} catch (NoResultException | NonUniqueResultException $e) {
				$currency = $this->currencyManager->get()->getDefaultCurrency();
			}

			$bm = new BankMovement(
				$data['messageId'],
				$data['bankAccountName'],
				$data['bankAccount'],
				$data['currencyCode'],
				$currency,
				$data['customerBankAccount'],
				$data['variableSymbol'],
				$data['price'],
				$data['date']
			);

			$bm->setCustomerName($data['customerName']);
			$bm->setConstantSymbol($data['constantSymbol'] ?? null);
			$bm->setMessage($data['message'] ?? null);

			$this->entityManager->persist($bm);
			$this->entityManager->flush();

			$this->processBankMovement($bm);
		}
	}


	private function processBankMovement(BankMovement $bm): void
	{
		try {
			/** @var Invoice $invoice */
			$invoice = $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->where('i.variableSymbol = :vs')
				->setParameter('vs', $bm->getVariableSymbol())
				->getQuery()
				->getSingleResult();

			$bm->setInvoice($invoice);

			if ($invoice->isPaid()) {
				$bm->setStatus(BankMovement::STATUS_IS_PAID);
			} elseif ($invoice->getCurrency() !== $bm->getCurrency()) {
				$bm->setStatus(BankMovement::STATUS_BAD_CURRENCY);
			} elseif ($invoice->getBankAccount() . '/' . $invoice->getBankCode() !== $bm->getBankAccount()) {
				$bm->setStatus(BankMovement::STATUS_BAD_ACCOUNT);
			} elseif ($invoice->getTotalPrice() !== $bm->getPrice()) {
				$bm->setStatus(BankMovement::STATUS_BAD_PRICE);
			} else {
				$invoice->setPayDate($bm->getDate());
				$invoice->setClosed(true);
				$invoice->setStatus(Invoice::STATUS_PAID);

				$link = '/admin/invoice/detail-bank-movement?id=' . $bm->getId();

				$txt = 'Faktura uhrazena dne '
					. $bm->getDate()->format('d.m.Y')
					. ' <a href="' . $link . '">převodem</a>.';

				$ih = new InvoiceHistory($invoice, $txt);
				$this->entityManager->persist($ih);

				$invoice->addHistory($ih);
				$this->entityManager->flush();

				if ($invoice->isProforma()) {
					$this->invoiceManager->get()->createPayDocumentFromInvoice($invoice);
				}

				$bm->setStatus(BankMovement::STATUS_SUCCESS);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$bm->setStatus(BankMovement::STATUS_BAD_VARIABLE_SYMBOL);
		} catch (EntityManagerException | InvoiceException $e) {
			Debugger::log($e);
			$bm->setStatus(BankMovement::STATUS_SYSTEM_ERROR);
		}

		$this->entityManager->flush();
	}
}
