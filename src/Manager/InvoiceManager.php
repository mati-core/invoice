<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Url\Url;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\Company;
use MatiCore\Invoice\Email\InvoiceEmail;
use MatiCore\Invoice\Email\InvoiceFixEmail;
use MatiCore\Invoice\Email\InvoicePayDocumentEmail;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Nette\Application\LinkGenerator;
use Nette\Security\User;
use Nette\Utils\Validators;
use Tracy\Debugger;

class InvoiceManager
{
	/** @var array|null */
	private ?array $acceptSetting;


	/**
	 * @param array $params
	 */
	public function __construct(
		private string $tempDir,
		private array $params,
		private EntityManager $entityManager,
		private User $user,
		private CurrencyManager $currencyManager,
		private SignatureManager $signatureManager,
		private LinkGenerator $linkGenerator,
		private EmailerAccessor $emailEngine,
		private ExportManagerAccessor $exportManager
	) {
		$this->acceptSetting = $params['settings']['accept'] ?? null;
	}


	/**
	 * @return array|null
	 */
	public function getAcceptSetting(): array|null
	{
		return $this->acceptSetting;
	}


	/**
	 * @return Invoice[]
	 */
	public function getAllInvoices(): array
	{
		return $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->select('i')
				->andWhere('i.deleted = :false')
				->setParameter('false', false)
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}


	/**
	 * @return Invoice[]
	 */
	public function getInvoices(int $limit = 100, int $offset = 0): array
	{
		return $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->select('i')
				->andWhere('i.deleted = :false')
				->setParameter('false', false)
				->orderBy('i.number', 'DESC')
				->setMaxResults($limit)
				->setFirstResult($offset)
				->getQuery()
				->getResult() ?? [];
	}


	/**
	 * @return Invoice[]
	 */
	public function getInvoicesBetweenDates(\DateTime $startDate, \DateTime $stopDate): array
	{
		return $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->select('i')
				->where('i.taxDate >= :startDate')
				->andWhere('i.taxDate < :stopDate')
				->andWhere('i.deleted = :false')
				->andWhere('i.status != :status')
				->andWhere('i.acceptStatus1 = :status1')
				->andWhere('i.acceptStatus2 = :status2')
				->andWhere('i.type IN (:types)')
				->setParameter('startDate', $startDate->format('Y-m-d'))
				->setParameter('stopDate', $stopDate->format('Y-m-d'))
				->setParameter('false', false)
				->setParameter('status', Invoice::STATUS_CANCELLED)
				->setParameter('status1', Invoice::STATUS_ACCEPTED)
				->setParameter('status2', Invoice::STATUS_ACCEPTED)
				->setParameter('types', [
					Invoice::TYPE_REGULAR,
					Invoice::TYPE_PROFORMA,
					Invoice::TYPE_PAY_DOCUMENT,
				])
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}


	/**
	 * @return Invoice[]
	 */
	public function getInvoicesUnpaid(): array
	{
		return $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->select('i')
				->where('i.payDate IS NULL')
				->andWhere('i.status != :status')
				->andWhere('i.deleted = :false')
				->setParameter('status', Invoice::STATUS_CANCELLED)
				->andWhere('i.acceptStatus1 = :status1')
				->setParameter('status1', Invoice::STATUS_ACCEPTED)
				->andWhere('i.acceptStatus2 = :status2')
				->setParameter('status2', Invoice::STATUS_ACCEPTED)
				->setParameter('false', false)
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getInvoiceById(string $id): Invoice
	{
		return $this->entityManager->getRepository(Invoice::class)
			->createQueryBuilder('i')
			->select('i')
			->where('i.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}


	/**
	 * @throws \Exception
	 */
	public function removeInvoice(Invoice $invoice, ?BaseUser $user = null): void
	{
		$invoice->setNumber('SMAZANA-' . $invoice->getNumber() . '-' . date('Y-m-d'));
		$invoice->setVariableSymbol('SMAZANA-' . $invoice->getNumber() . '-' . date('Y-m-d'));
		$invoice->setDeleted(true);
		$invoice->setStatus(Invoice::STATUS_CANCELLED);

		$entities = [$invoice];

		$history = new InvoiceHistory($invoice, 'Storno a odstranění faktury.');
		$history->setUser($user);

		$this->entityManager->persist($history);

		$invoice->addHistory($history);
		$entities[] = $history;

		if ($invoice->isRegular()) {
			$fixInvoice = $invoice->getFixInvoice();
			if ($fixInvoice !== null) {
				$fixInvoice->setNumber('SMAZANA-' . $fixInvoice->getNumber() . '-' . date('Y-m-d'));
				$fixInvoice->setVariableSymbol('SMAZANA-' . $fixInvoice->getNumber() . '-' . date('Y-m-d'));
				$fixInvoice->setDeleted(true);
				$fixInvoice->setStatus(Invoice::STATUS_CANCELLED);
				$entities[] = $fixInvoice;

				$history = new InvoiceHistory($invoice, 'Storno a odstranění dobropisu.');
				$history->setUser($user);

				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;
			}
		} elseif ($invoice->isProforma()) {
			$payDocument = $invoice->getParentInvoice();
			if ($payDocument !== null) {
				$payDocument->setNumber('SMAZANA-' . $payDocument->getNumber() . '-' . date('Y-m-d'));
				$payDocument->setVariableSymbol('SMAZANA-' . $payDocument->getNumber() . '-' . date('Y-m-d'));
				$payDocument->setDeleted(true);
				$payDocument->setStatus(Invoice::STATUS_CANCELLED);
				$entities[] = $payDocument;

				$history = new InvoiceHistory($invoice, 'Storno a odstranění potvrzení přijetí platby.');
				$history->setUser($user);

				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;
			}
		}
		$this->entityManager->flush();
	}


	/**
	 * @throws EntityManagerException|InvoiceException
	 */
	public function createPayDocumentFromInvoice(Invoice $invoice): Invoice
	{
		if (!$invoice->isReady()) {
			throw new InvoiceException(
				'Nelze vygenerovat fakturu, protože proforma faktury není odevzdána a schválena.'
			);
		}
		if (!$invoice->isPaid()) {
			throw new InvoiceException('Nelze vygenerovat fakturu na základě neuhrazené proformy.');
		}

		// Nastaveni meny
		$currencyTemp = $this->currencyManager->getCurrencyRateByDate($invoice->getCurrency(), $invoice->getPayDate());
		$currencyRate = $currencyTemp->getRate();
		$currencyDate = $currencyTemp->getLastUpdate();

		if ($invoice->isProforma()) {
			$invoice->setRateDate($currencyDate);
			$invoice->setRate($currencyRate);
		}

		$number = '77' . $invoice->getNumber();

		$pd = new Invoice($number, Invoice::TYPE_PAY_DOCUMENT);
		$pd->setSubInvoice($invoice);
		$pd->addDepositInvoice($invoice);
		$invoice->addDepositingInvoice($pd);

		$pd->setCompany($invoice->getCompany());

		/** @var BaseUser|null $user */
		$user = $invoice->getCreateUser();
		$pd->setCreateUser($user);
		$pd->setEditUser($user);
		$pd->setCreateDate(new \DateTime('NOW'));
		$pd->setEditDate(new \DateTime('NOW'));

		//Nastaveni spolecnosti
		$pd->setCompanyName($invoice->getCompanyName());
		$pd->setCompanyAddress($invoice->getCompanyAddress());
		$pd->setCompanyCity($invoice->getCompanyCity());
		$pd->setCompanyPostalCode($invoice->getCompanyPostalCode());
		$pd->setCompanyCountry($invoice->getCompanyCountry());
		$pd->setCompanyCin($invoice->getCompanyCin());
		$pd->setCompanyTin($invoice->getCompanyTin());
		$pd->setCompanyLogo($invoice->getCompanyLogo());

		//Nastaveni banky
		$pd->setBankAccount($invoice->getBankAccount());
		$pd->setBankCode($invoice->getBankCode());
		$pd->setBankName($invoice->getBankName());
		$pd->setIban($invoice->getIban());
		$pd->setSwift($invoice->getSwift());
		$pd->setVariableSymbol($number);

		$pd->setCurrency($invoice->getCurrency());
		$pd->setRate($currencyRate);
		$pd->setRateDate($currencyDate);

		//Nastaveni zakaznika
		$pd->setCustomerName($invoice->getCustomerName());
		$pd->setCustomerAddress($invoice->getCustomerAddress());
		$pd->setCustomerCity($invoice->getCustomerCity());
		$pd->setCustomerPostalCode($invoice->getCustomerPostalCode());
		$pd->setCustomerCountry($invoice->getCustomerCountry());
		$pd->setCustomerCin($invoice->getCustomerCin());
		$pd->setCustomerTin($invoice->getCustomerTin());

		//cisla
		$pd->setOrderNumber($invoice->getOrderNumber());
		$pd->setRentNumber($invoice->getRentNumber());
		$pd->setContractNumber($invoice->getContractNumber());

		//Nastaveni celkove ceny
		$pd->setTotalPrice(0.0);
		$pd->setTotalTax($invoice->getTotalTax());

		//Data
		$pd->setDate($invoice->getPayDate());
		$pd->setDueDate($invoice->getPayDate());
		$pd->setTaxDate($currencyDate);
		$pd->setPayDate($invoice->getPayDate());

		//platební metody
		$pd->setPayMethod($invoice->getPayMethod());

		//Podpis autora faktury
		$pd->setSignImage($this->signatureManager->getSignatureLink($invoice->getCreateUser()));

		//Poznamky
		if ($invoice->isTaxEnabled()) {
			$textBeforeItems = 'Vyúčtování DPH na základě přijetí zálohové platby č.: ' . $invoice->getVariableSymbol();
		} else {
			$textBeforeItems = 'Vyúčtování na základě přijetí zálohové platby č.: ' . $invoice->getVariableSymbol();
		}

		$pd->setTextBeforeItems($textBeforeItems);
		$pd->setTextAfterItems($invoice->getTextAfterItems());

		//Stav faktury
		$pd->setStatus(Invoice::STATUS_CREATED);
		$pd->setAcceptStatus1(Invoice::STATUS_ACCEPTED);
		$pd->setAcceptStatus2(Invoice::STATUS_ACCEPTED);
		$pd->setSubmitted(true);
		$pd->setClosed(true);

		//Persist
		$this->entityManager->persist($pd);

		//Přidaní položek
		foreach ($invoice->getItems() as $invoiceItem) {
			$item = new InvoiceItem(
				$pd,
				$invoiceItem->getDescription(),
				$invoiceItem->getQuantity(),
				$invoiceItem->getUnit(),
				$invoiceItem->getPricePerItem()
			);

			$item->setVat($invoiceItem->getVat());
			$item->setPosition($invoiceItem->getPosition());
			$item->setSaleDescription($invoiceItem->getSaleDescription());
			$item->setSale($invoiceItem->getSale());

			$this->entityManager->persist($item);
			$pd->addItem($item);
		}

		//DPH
		foreach ($invoice->getTaxList() as $invoiceTax) {
			$tax = new InvoiceTax($pd, $invoiceTax->getTax(), $invoiceTax->getPrice());
			$this->entityManager->persist($tax);
			$pd->addTax($tax);
		}

		//Záznam do historie
		$link = '/admin/invoice/show?id=' . $invoice->getId();

		$history = new InvoiceHistory(
			$pd,
			'Vytvoření dokladu o přijetí platby na základě proformy č.: <a href="' . $link . '">' . $invoice->getNumber(
			) . '</a>.'
		);
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$pd->addHistory($history);

		//Faktura
		$invoice->setParentInvoice($pd);

		$link = '/admin/invoice/show?id=' . $pd->getId();

		$history = new InvoiceHistory(
			$invoice, 'Vytvořen doklad o přijetí platby č.: <a href="' . $link . '">' . $pd->getNumber(
			) . '</a> na základě tohoto dokumentu.'
		);
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$invoice->addHistory($history);

		$this->entityManager->flush();

		try {
			$this->sendEmailToCompany($pd);
		} catch (EntityManagerException $e) {
			Debugger::log($e);
		}

		return $pd;
	}


	/**
	 * @return array
	 * @throws EntityManagerException
	 */
	public function sendEmailToCompany(Invoice $invoice): array
	{
		$emails = $this->getInvoiceEmails($invoice);
		if (count($emails) === 0) {
			return [
				'show' => true,
				'message' => 'Doklad nebyl odeslán. Neexistují žádné kontakty v databázi.',
				'type' => 'warning',
			];
		}

		if ($this->sendEmail($invoice, $emails)) {
			return [
				'show' => true,
				'message' => 'Doklad byl odeslána emailem.',
				'type' => 'success',
			];
		}

		return [
			'show' => true,
			'message' => 'Doklad se nepodařilo odeslat emailem.',
			'type' => 'danger',
		];
	}


	/**
	 * @return array
	 */
	public function getInvoiceEmails(Invoice $invoice): array
	{
		$emails = [];
		$company = $invoice->getCompany();
		if ($company !== null) {
			foreach ($company->getContacts() as $contact) {
				if ($contact->isSendInvoice() && $contact->getEmail() !== null) {
					$list = explode(';', $contact->getEmail());
					foreach ($list as $item) {
						$list2 = explode(',', $item);
						foreach ($list2 as $item2) {
							$email = trim($item2);

							if (Validators::isEmail($email) && !in_array($email, $emails, true)) {
								$emails[] = $email;
							}
						}
					}
				}
			}
		}

		foreach ($invoice->getEmailList() as $email) {
			if (Validators::isEmail($email) && !in_array($email, $emails, true)) {
				$emails[] = $email;
			}
		}

		return $emails;
	}


	/**
	 * @param array $emails
	 * @throws ConstantException
	 */
	public function sendEmail(Invoice $invoice, array $emails): bool
	{
		$production = (bool) ($this->params['invoiceEmail']['production'] ?? false);
		$sender = $this->params['invoiceEmail']['email'] ?? 'test@app-universe.cz';

		if ($production === false) {
			$emails = [
				'test@app-universe.cz',
			];
		}

		/**
		 * @var BaseUser $user
		 * @phpstan-ignore-next-line
		 */
		$user = $this->user->getIdentity()->getUser();
		$status = true;
		$attachments = [];

		// Faktura do prilohy
		$name = $this->exportManager->get()->getExportInvoiceFileName($invoice);

		try {
			$tmp = $this->tempDir . '/' . $name;
			$this->exportManager->get()->exportInvoiceToPdf($invoice, Destination::FILE, $tmp);
			$attachments[] = [
				'file' => $tmp,
				'name' => $name,
			];
		} catch (MpdfException | CurrencyException $e) {
			Debugger::log($e);

			return false;
		}

		if ($invoice->getCompany() !== null && $invoice->getCompany()->isSendInvoicesInOneFile()) {
			$files = [];
			foreach ($attachments as $file) {
				$files[] = $file['file'];
			}

			$name = $this->exportManager->get()->getExportInvoiceFileName($invoice);

			$tmp = $this->tempDir . '/' . $name;
			try {
				$this->exportManager->get()->mergePDF($files, Destination::FILE, $tmp);

				foreach ($attachments as $attachment) {
					if ($attachment['file'] !== $tmp && is_file($attachment['file'])) {
						unlink($attachment['file']);
					}
				}
			} catch (MpdfException $e) {
				Debugger::log($e);

				return false;
			}

			$attachments = [
				[
					'file' => $tmp,
					'name' => $name,
				],
			];
		}

		//Zaloha odeslanych dokumentu
		foreach ($this->params['invoiceEmail']['copy'] as $email) {
			$emails[] = $email;
		}


		$invoiceData = $this->getInvoiceTemplateData($invoice);
		$invoiceData['company'] = $this->params['company']['name'];
		$invoiceData['logo'] = $this->params['company']['logo'];
		$invoiceData['url'] = $this->params['company']['url'];
		foreach ($emails as $recipient) {
			try {
				$recipient = trim($recipient);
				if ($invoice->isFix()) {
					$email = $this->emailEngine->get()->getEmailServiceByType(
						InvoiceFixEmail::class, [
							'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
							'to' => $recipient,
							'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
							'subject' => 'Opravný daňový doklad č.: ' . $invoice->getNumber(),
							'invoice' => $invoice,
							'invoiceData' => $invoiceData,
						]
					);
				} elseif ($invoice->isPayDocument()) {
					$email = $this->emailEngine->get()->getEmailServiceByType(
						InvoicePayDocumentEmail::class, [
							'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
							'to' => $recipient,
							'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
							'subject' => 'Doklad o přijetí platby č.: ' . $invoice->getNumber(),
							'invoice' => $invoice,
							'invoiceData' => $invoiceData,
						]
					);
				} else {
					$email = $this->emailEngine->get()->getEmailServiceByType(
						InvoiceEmail::class, [
							'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
							'to' => $recipient,
							'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
							'subject' => 'Faktura č.: ' . $invoice->getNumber(),
							'invoice' => $invoice,
							'invoiceData' => $invoiceData,
						]
					);
				}

				foreach ($attachments as $attachment) {
					$email->getMessage()->addAttachmentPath($attachment['file'], $attachment['name']);
				}

				$email->send();

				if (!str_starts_with($recipient, 'backup') && !str_starts_with($recipient, 'zaloha')) {
					$ih = new InvoiceHistory($invoice, 'Doklad odeslán emailem na ' . $recipient);
					$ih->setUser($user);

					$this->entityManager->persist($ih);

					$invoice->addHistory($ih);
					$invoice->setStatus(Invoice::STATUS_SENT);
					$invoice->addEmail($recipient);
				}
				$this->entityManager->flush();
			} catch (ConstantException | EntityManagerException | EmailException $e) {
				Debugger::log($e);
				$ih = new InvoiceHistory(
					$invoice,
					'<span class="text-danger">Doklad se nepodařilo odeslat emailem na ' . $recipient . '</span>'
				);
				$ih->setUser($user);

				$this->entityManager->persist($ih);

				$invoice->addHistory($ih);
				$invoice->setStatus(Invoice::STATUS_SENT);

				$this->entityManager->flush();
				$status = false;
			}
		}

		foreach ($attachments as $attachment) {
			if (is_file($attachment['file'])) {
				unlink($attachment['file']);
			}
		}

		return $status;
	}


	public function createInvoiceFromInvoiceProforma(Invoice $proforma): Invoice
	{
		if (!$proforma->isReady()) {
			throw new InvoiceException(
				'Nelze vygenerovat fakturu, protože proforma faktury není odevzdána a schválena.'
			);
		}
		if (!$proforma->isPaid()) {
			throw new InvoiceException('Nelze vygenerovat fakturu na základě neuhrazené proformy.');
		}

		$number = $this->getNextInvoiceNumber();
		$invoice = new Invoice($number, Invoice::TYPE_REGULAR);
		$invoice->setProforma($proforma);
		$invoice->addDepositInvoice($proforma);
		$invoice->setCompany($proforma->getCompany());

		/** @var BaseUser|StorageIdentity|null $user */
		$user = $this->user->getIdentity();
		if ($user instanceof StorageIdentity) {
			$user = $user->getUser();
		}
		if (!$user instanceof BaseUser) {
			$user = null;
		}

		$invoice->setCreateUser($user ?? $proforma->getCreateUser());
		$invoice->setEditUser($user ?? $proforma->getCreateUser());
		$invoice->setCreateDate(new \DateTime('NOW'));
		$invoice->setEditDate(new \DateTime('NOW'));

		//Nastaveni spolecnosti
		$invoice->setCompanyName($proforma->getCompanyName());
		$invoice->setCompanyAddress($proforma->getCompanyAddress());
		$invoice->setCompanyCity($proforma->getCompanyCity());
		$invoice->setCompanyPostalCode($proforma->getCompanyPostalCode());
		$invoice->setCompanyCountry($proforma->getCompanyCountry());
		$invoice->setCompanyCin($proforma->getCompanyCin());
		$invoice->setCompanyTin($proforma->getCompanyTin());
		$invoice->setCompanyLogo($proforma->getCompanyLogo());

		//Nastaveni banky
		$invoice->setBankAccount($proforma->getBankAccount());
		$invoice->setBankCode($proforma->getBankCode());
		$invoice->setBankName($proforma->getBankName());
		$invoice->setIban($proforma->getIban());
		$invoice->setSwift($proforma->getSwift());
		$invoice->setVariableSymbol($number);

		//Nastaveni meny
		$currencyTemp = $this->currencyManager->getCurrencyRateByDate($proforma->getCurrency(), new \DateTime('NOW'));
		$currencyRate = $currencyTemp->getRate();
		$currencyDate = $currencyTemp->getLastUpdate();

		$invoice->setCurrency($proforma->getCurrency());
		$invoice->setRate($currencyRate);
		$invoice->setRateDate($currencyDate);

		//Nastaveni zakaznika
		$invoice->setCustomerName($proforma->getCustomerName());
		$invoice->setCustomerAddress($proforma->getCustomerAddress());
		$invoice->setCustomerCity($proforma->getCustomerCity());
		$invoice->setCustomerPostalCode($proforma->getCustomerPostalCode());
		$invoice->setCustomerCountry($proforma->getCustomerCountry());
		$invoice->setCustomerCin($proforma->getCustomerCin());
		$invoice->setCustomerTin($proforma->getCustomerTin());

		//cisla
		$invoice->setOrderNumber($proforma->getOrderNumber());
		$invoice->setRentNumber($proforma->getRentNumber());
		$invoice->setContractNumber($proforma->getContractNumber());

		//Nastaveni celkove ceny
		$invoice->setTotalPrice(0);
		$invoice->setTotalTax(0);

		//Data
		$invoice->setDate(new \DateTime('NOW'));
		$invoice->setDueDate(new \DateTime('NOW'));
		$invoice->setTaxDate($currencyDate);

		//platební metody
		$invoice->setPayMethod($proforma->getPayMethod());

		//Podpis autora faktury
		$invoice->setSignImage($this->signatureManager->getSignatureLink($proforma->getCreateUser()));

		//Poznamky
		$textBeforeItems = 'Vystavení daňového dokladu na základě přijetí zálohové platby č.: ' . $proforma->getVariableSymbol(
			);
		$invoice->setTextBeforeItems($textBeforeItems);
		$invoice->setTextAfterItems($proforma->getTextAfterItems());

		//vypnuti/zapnuti DPH
		$invoice->setTaxEnabled($proforma->isTaxEnabled());

		//Persist
		$this->entityManager->persist($invoice);

		//Přidaní položek
		foreach ($proforma->getItems() as $proformaItem) {
			$item = new InvoiceItem(
				$invoice,
				$proformaItem->getDescription(),
				$proformaItem->getQuantity(),
				$proformaItem->getUnit(),
				$proformaItem->getPricePerItem()
			);

			$item->setVat($proformaItem->getVat());
			$item->setPosition($proformaItem->getPosition());
			$item->setSaleDescription($proformaItem->getSaleDescription());
			$item->setSale($proformaItem->getSale());

			$this->entityManager->persist($item);
			$invoice->addItem($item);
		}

		//DPH
		foreach ($invoice->getTaxList() as $invoiceTax) {
			$tax = new InvoiceTax($invoice, $invoiceTax->getTax(), $invoiceTax->getPrice());
			$this->entityManager->persist($tax);
			$invoice->addTax($tax);
		}

		//odecteni zalohy (propojeni faktur)
		$invoice->addDepositInvoice($proforma);
		$proforma->setSubInvoice($invoice);
		$proforma->addDepositingInvoice($invoice);

		//Záznam do historie
		$link = Url::get()->getBaseUrl() . '/admin/invoice/detail?id=' . $proforma->getId();
		$history = new InvoiceHistory(
			$invoice,
			'Vytvoření faktury na základě proformy č.: <a href="' . $link . '">' . $proforma->getNumber() . '</a>.'
		);
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$invoice->addHistory($history);

		//Proforma
		$link = Url::get()->getBaseUrl() . '/admin/invoice/detail?id=' . $invoice->getId();

		$history = new InvoiceHistory(
			$proforma,
			'Vytvořena faktura č.: <a href="' . $link . '">' . $invoice->getNumber() . '</a> na základě této proformy.'
		);
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$proforma->addHistory($history);

		$this->entityManager->flush();

		return $invoice;
	}


	/**
	 * @throws InvoiceException
	 */
	public function getNextInvoiceNumber(?\DateTime $date = null): string
	{
		if ($date === null) {
			$year = date('y');
			$month = date('m');
		} else {
			$year = $date->format('y');
			$month = $date->format('m');
		}

		$date = new \DateTime($year . '-' . $month . '-01');
		$startDate = $date->modifyClone('-3 months');
		$stopDate = $date->modifyClone('+1 months');

		/** @var Invoice[] $invoices */
		$invoices = $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('i')
				->select('i.number')
				->where('i.taxDate >= :dateStart')
				->andWhere('i.taxDate < :dateStop')
				->setParameter('dateStart', $startDate)
				->setParameter('dateStop', $stopDate)
				->getQuery()
				->getScalarResult() ?? [];

		$numbers = [];
		foreach ($invoices as $invoice) {
			$numbers[] = $invoice['number'];
		}

		$count = 100;
		$limit = 9999;
		do {
			$count++;
			$limit--;
			$formattedCount = (string) $count;
			while (strlen($formattedCount) < 4) {
				$formattedCount = '0' . $formattedCount;
			}
			$number = $year . $month . $formattedCount;
		} while (in_array($number, $numbers, true) && $limit > 0);

		if (in_array($number, $numbers, true)) {
			throw new InvoiceException('Can not create invoice number. Out of limit');
		}

		return $number;
	}


	/**
	 * @throws NonUniqueResultException|NoResultException
	 */
	public function getInvoiceByCode(string $number): Invoice
	{
		return $this->entityManager->getRepository(Invoice::class)
			->createQueryBuilder('i')
			->select('i')
			->where('i.number = :number')
			->setParameter('number', $number)
			->getQuery()
			->getSingleResult();
	}


	public function getColorByInvoiceDocument(Invoice $invoice): string
	{
		return $this->exportManager->get()->getColorByInvoiceDocument($invoice);
	}


	/**
	 * @return array<string|null>
	 */
	public function getInvoiceTemplateData(Invoice $invoice): array
	{
		return $this->exportManager->get()->getInvoiceTemplateData($invoice);
	}


	/**
	 * @return array<int, Invoice>
	 */
	public function getInvoicesByCompany(Company $company): array
	{
		return $this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('ic')
				->select('ic')
				->where('ic.company = :companyId')
				->setParameter('companyId', $company->getId())
				->andWhere('ic.deleted = :f')
				->setParameter('f', false)
				->getQuery()
				->getResult();
	}
}
