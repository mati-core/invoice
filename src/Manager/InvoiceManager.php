<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Company\Company;
use MatiCore\Constant\Exception\ConstantException;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\CurrencyManager;
use MatiCore\Email\EmailerAccessor;
use MatiCore\Email\EmailException;
use MatiCore\Invoice\Email\InvoiceEmail;
use MatiCore\Invoice\Email\InvoiceFixEmail;
use MatiCore\Invoice\Email\InvoicePayDocumentEmail;
use MatiCore\User\BaseUser;
use MatiCore\User\StorageIdentity;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\Security\User;
use Nette\Utils\DateTime;
use Nette\Utils\Validators;
use Tracy\Debugger;

/**
 * Class InvoiceManager
 * @package App\Model
 */
class InvoiceManager
{

	/**
	 * @var string
	 */
	private string $tempDir;

	/**
	 * @var array|null
	 */
	private ?array $acceptSetting;

	/**
	 * @var array
	 */
	private array $params;

	/**
	 * @var EntityManager
	 */
	private EntityManager $entityManager;

	/**
	 * @var User
	 */
	private User $user;

	/**
	 * @var CurrencyManager
	 */
	private CurrencyManager $currencyManager;

	/**
	 * @var SignatureManager
	 */
	private SignatureManager $signatureManager;

	/**
	 * @var LinkGenerator
	 */
	private LinkGenerator $linkGenerator;

	/**
	 * @var EmailerAccessor
	 */
	private EmailerAccessor $emailEngine;

	/**
	 * @var ExportManagerAccessor
	 */
	private ExportManagerAccessor $exportManager;

	/**
	 * InvoiceManager constructor.
	 * @param string $tempDir
	 * @param array $params
	 * @param EntityManager $entityManager
	 * @param User $user
	 * @param CurrencyManager $currencyManager
	 * @param SignatureManager $signatureManager
	 * @param LinkGenerator $linkGenerator
	 * @param EmailerAccessor $emailEngine
	 * @param ExportManagerAccessor $exportManager
	 */
	public function __construct(
		string $tempDir,
		array $params,
		EntityManager $entityManager,
		User $user,
		CurrencyManager $currencyManager,
		SignatureManager $signatureManager,
		LinkGenerator $linkGenerator,
		EmailerAccessor $emailEngine,
		ExportManagerAccessor $exportManager
	)
	{
		$this->tempDir = $tempDir;
		$this->params = $params;
		$this->acceptSetting = $params['settings']['accept'] ?? null;
		$this->entityManager = $entityManager;
		$this->user = $user;
		$this->currencyManager = $currencyManager;
		$this->signatureManager = $signatureManager;
		$this->linkGenerator = $linkGenerator;
		$this->emailEngine = $emailEngine;
		$this->exportManager = $exportManager;
	}

	/**
	 * @return array|null
	 */
	public function getAcceptSetting(): array|null
	{
		return $this->acceptSetting;
	}

	/**
	 * @return InvoiceCore[]
	 */
	public function getAllInvoices(): array
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('i')
				->select('i')
				->andWhere('i.deleted = :false')
				->setParameter('false', false)
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return InvoiceCore[]
	 */
	public function getInvoices(int $limit = 100, int $offset = 0): array
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
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
	 * @param \DateTime $startDate
	 * @param \DateTime $stopDate
	 * @return InvoiceCore[]
	 */
	public function getInvoicesBetweenDates(\DateTime $startDate, \DateTime $stopDate): array
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('i')
				->select('i')
				->where('i.taxDate >= :startDate')
				->andWhere('i.taxDate < :stopDate')
				->andWhere('i.deleted = :false')
				->setParameter('startDate', $startDate->format('Y-m-d'))
				->setParameter('stopDate', $stopDate->format('Y-m-d'))
				->setParameter('false', false)
				->andWhere('i.status != :status')
				->setParameter('status', InvoiceStatus::CANCELLED)
				->andWhere('i.acceptStatus1 = :status1')
				->setParameter('status1', InvoiceStatus::ACCEPTED)
				->andWhere('i.acceptStatus2 = :status2')
				->setParameter('status2', InvoiceStatus::ACCEPTED)
				->andWhere('(i INSTANCE OF ' . Invoice::class . ' OR i INSTANCE OF ' . InvoiceProforma::class . ' OR i INSTANCE OF ' . InvoicePayDocument::class . ')')
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}

	/**
	 * @return InvoiceCore[]
	 */
	public function getInvoicesUnpaid(): array
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('i')
				->select('i')
				->where('i.payDate IS NULL')
				->andWhere('i.status != :status')
				->andWhere('i.deleted = :false')
				->setParameter('status', InvoiceStatus::CANCELLED)
				->andWhere('i.acceptStatus1 = :status1')
				->setParameter('status1', InvoiceStatus::ACCEPTED)
				->andWhere('i.acceptStatus2 = :status2')
				->setParameter('status2', InvoiceStatus::ACCEPTED)
				->setParameter('false', false)
				->orderBy('i.number', 'DESC')
				->getQuery()
				->getResult() ?? [];
	}

	/**
	 * @param string $id
	 * @return InvoiceCore
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function getInvoiceById(string $id): InvoiceCore
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
			->createQueryBuilder('i')
			->select('i')
			->where('i.id = :id')
			->setParameter('id', $id)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @param InvoiceCore $invoice
	 * @param BaseUser|null $user
	 * @throws \Exception
	 */
	public function removeInvoice(InvoiceCore $invoice, ?BaseUser $user = null): void
	{
		$invoice->setNumber('SMAZANA-' . $invoice->getNumber() . '-' . date('Y-m-d'));
		$invoice->setVariableSymbol('SMAZANA-' . $invoice->getNumber() . '-' . date('Y-m-d'));
		$invoice->setDeleted(true);
		$invoice->setStatus(InvoiceStatus::CANCELLED);

		$entities = [$invoice];

		$history = new InvoiceHistory($invoice, 'Storno a odstranění faktury.');
		$history->setUser($user);

		$this->entityManager->persist($history);

		$invoice->addHistory($history);
		$entities[] = $history;

		if ($invoice instanceof Invoice) {
			$fixInvoice = $invoice->getFixInvoice();
			if ($fixInvoice !== null) {
				$fixInvoice->setNumber('SMAZANA-' . $fixInvoice->getNumber() . '-' . date('Y-m-d'));
				$fixInvoice->setVariableSymbol('SMAZANA-' . $fixInvoice->getNumber() . '-' . date('Y-m-d'));
				$fixInvoice->setDeleted(true);
				$fixInvoice->setStatus(InvoiceStatus::CANCELLED);
				$entities[] = $fixInvoice;

				$history = new InvoiceHistory($invoice, 'Storno a odstranění dobropisu.');
				$history->setUser($user);

				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;
			}
		} elseif ($invoice instanceof InvoiceProforma) {
			$payDocument = $invoice->getPayDocument();
			if ($payDocument !== null) {
				$payDocument->setNumber('SMAZANA-' . $payDocument->getNumber() . '-' . date('Y-m-d'));
				$payDocument->setVariableSymbol('SMAZANA-' . $payDocument->getNumber() . '-' . date('Y-m-d'));
				$payDocument->setDeleted(true);
				$payDocument->setStatus(InvoiceStatus::CANCELLED);
				$entities[] = $payDocument;

				$history = new InvoiceHistory($invoice, 'Storno a odstranění potvrzení přijetí platby.');
				$history->setUser($user);

				$this->entityManager->persist($history);

				$invoice->addHistory($history);
				$entities[] = $history;
			}
		}

		$this->entityManager->getUnitOfWork()->commit($entities);
	}

	/**
	 * @param InvoiceCore $invoice
	 * @return InvoicePayDocument
	 * @throws EntityManagerException
	 * @throws InvoiceException
	 */
	public function createPayDocumentFromInvoice(InvoiceCore $invoice): InvoicePayDocument
	{
		if (!$invoice->isReady()) {
			throw new InvoiceException('Nelze vygenerovat fakturu, protože proforma faktury není odevzdána a schválena.');
		}

		if (!$invoice->isPaid()) {
			throw new InvoiceException('Nelze vygenerovat fakturu na základě neuhrazené proformy.');
		}

		//Nastaveni meny
		$currencyTemp = $this->currencyManager->getCurrencyRateByDate($invoice->getCurrency(), $invoice->getPayDate());
		$currencyRate = $currencyTemp->getRate();
		$currencyDate = $currencyTemp->getLastUpdate();

		if ($invoice instanceof InvoiceProforma) {
			$invoice->setRateDate($currencyDate);
			$invoice->setRate($currencyRate);
		}

		//$number = $this->getNextInvoiceNumber($currencyDate);
		$number = '77' . $invoice->getNumber();

		$pd = new InvoicePayDocument($number);
		$pd->setInvoice($invoice);
		$pd->addDepositInvoice($invoice);
		$invoice->addDepositingInvoice($pd);

		$pd->setCompany($invoice->getCompany());

		/** @var BaseUser|null $user */
		$user = $invoice->getCreateUser();
		$pd->setCreateUser($user);
		$pd->setEditUser($user);
		$pd->setCreateDate(DateTime::from('NOW'));
		$pd->setEditDate(DateTime::from('NOW'));

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
		$pd->setStatus(InvoiceStatus::CREATED);
		$pd->setAcceptStatus1(InvoiceStatus::ACCEPTED);
		$pd->setAcceptStatus2(InvoiceStatus::ACCEPTED);
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

		$history = new InvoiceHistory($pd, 'Vytvoření dokladu o přijetí platby na základě proformy č.: <a href="' . $link . '">' . $invoice->getNumber() . '</a>.');
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$pd->addHistory($history);

		//Faktura
		$invoice->setPayDocument($pd);

		$link = '/admin/invoice/show?id=' . $pd->getId();

		$history = new InvoiceHistory($invoice, 'Vytvořen doklad o přijetí platby č.: <a href="' . $link . '">' . $pd->getNumber() . '</a> na základě tohoto dokumentu.');
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
	 * @param InvoiceCore $invoice
	 * @return array
	 * @throws EntityManagerException
	 */
	public function sendEmailToCompany(InvoiceCore $invoice): array
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
	 * @param InvoiceCore $invoice
	 * @return array
	 */
	public function getInvoiceEmails(InvoiceCore $invoice): array
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
	 * @param InvoiceCore $invoice
	 * @param array $emails
	 * @return bool
	 * @throws ConstantException
	 */
	public function sendEmail(InvoiceCore $invoice, array $emails): bool
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
			$this->exportManager->get()->exportInvoiceToPDF($invoice, Destination::FILE, $tmp);
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
				if ($invoice instanceof FixInvoice) {
					$email = $this->emailEngine->get()->getEmailServiceByType(InvoiceFixEmail::class, [
						'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
						'to' => $recipient,
						'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
						'subject' => 'Opravný daňový doklad č.: ' . $invoice->getNumber(),
						'invoice' => $invoice,
						'invoiceData' => $invoiceData,
					]);
				} elseif ($invoice instanceof InvoicePayDocument) {
					$email = $this->emailEngine->get()->getEmailServiceByType(InvoicePayDocumentEmail::class, [
						'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
						'to' => $recipient,
						'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
						'subject' => 'Doklad o přijetí platby č.: ' . $invoice->getNumber(),
						'invoice' => $invoice,
						'invoiceData' => $invoiceData,
					]);
				} else {
					$email = $this->emailEngine->get()->getEmailServiceByType(InvoiceEmail::class, [
						'from' => ($this->params['invoiceEmail']['name'] ?? 'APP Universe') . ' <' . $sender . '>',
						'to' => $recipient,
						'replyTo' => $this->params['invoiceEmail']['replyTo'] ?? $sender,
						'subject' => 'Faktura č.: ' . $invoice->getNumber(),
						'invoice' => $invoice,
						'invoiceData' => $invoiceData,
					]);
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
					$invoice->setStatus(InvoiceStatus::SENT);
					$invoice->addEmail($recipient);

					$this->entityManager->flush([$invoice, $ih]);
				} else {
					$this->entityManager->flush([$invoice]);
				}
			} catch (ConstantException | EntityManagerException | EmailException $e) {
				Debugger::log($e);
				$ih = new InvoiceHistory($invoice, '<span class="text-danger">Doklad se nepodařilo odeslat emailem na ' . $recipient . '</span>');
				$ih->setUser($user);

				$this->entityManager->persist($ih);

				$invoice->addHistory($ih);
				$invoice->setStatus(InvoiceStatus::SENT);

				$this->entityManager->flush([$invoice, $ih]);
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

	/**
	 * @param InvoiceProforma $proforma
	 * @return Invoice
	 * @throws EntityManagerException
	 * @throws InvoiceException
	 */
	public function createInvoiceFromInvoiceProforma(InvoiceProforma $proforma): Invoice
	{
		if (!$proforma->isReady()) {
			throw new InvoiceException('Nelze vygenerovat fakturu, protože proforma faktury není odevzdána a schválena.');
		}

		if (!$proforma->isPaid()) {
			throw new InvoiceException('Nelze vygenerovat fakturu na základě neuhrazené proformy.');
		}

		$number = $this->getNextInvoiceNumber();

		$invoice = new Invoice($number);
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
		$invoice->setCreateDate(DateTime::from('NOW'));
		$invoice->setEditDate(DateTime::from('NOW'));

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
		$currencyTemp = $this->currencyManager->getCurrencyRateByDate($proforma->getCurrency(), DateTime::from('NOW'));
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
		$invoice->setDate(DateTime::from('NOW'));
		$invoice->setDueDate(DateTime::from('NOW'));
		$invoice->setTaxDate($currencyDate);

		//platební metody
		$invoice->setPayMethod($proforma->getPayMethod());

		//Podpis autora faktury
		$invoice->setSignImage($this->signatureManager->getSignatureLink($proforma->getCreateUser()));

		//Poznamky
		$textBeforeItems = 'Vystavení daňového dokladu na základě přijetí zálohové platby č.: ' . $proforma->getVariableSymbol();
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
		$proforma->setInvoice($invoice);
		$proforma->addDepositingInvoice($invoice);

		//Záznam do historie
		try {
			$link = $this->linkGenerator->link('Admin:Invoice:show', ['id' => $proforma->getId()]);
		} catch (InvalidLinkException $e) {
			$link = '#';
		}
		$history = new InvoiceHistory($invoice, 'Vytvoření faktury na základě proformy č.: <a href="' . $link . '">' . $proforma->getNumber() . '</a>.');
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$invoice->addHistory($history);

		//Proforma
		try {
			$link = $this->linkGenerator->link('Admin:Invoice:show', ['id' => $invoice->getId()]);
		} catch (InvalidLinkException $e) {
			$link = '#';
		}

		$history = new InvoiceHistory($proforma, 'Vytvořena faktura č.: <a href="' . $link . '">' . $invoice->getNumber() . '</a> na základě této proformy.');
		$history->setUser($user ?? null);
		$this->entityManager->persist($history);

		$proforma->addHistory($history);

		$this->entityManager->flush();

		return $invoice;
	}

	/**
	 * @param \DateTime|null $date
	 * @return string
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

		$date = DateTime::from($year . '-' . $month . '-01');
		$startDate = $date->modifyClone('-3 months');
		$stopDate = $date->modifyClone('+1 months');

		/** @var InvoiceCore[] $invoices */
		$invoices = $this->entityManager->getRepository(InvoiceCore::class)
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
	 * @param string $number
	 * @return InvoiceCore
	 * @throws NonUniqueResultException
	 * @throws NoResultException
	 */
	public function getInvoiceByCode(string $number): InvoiceCore
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
			->createQueryBuilder('i')
			->select('i')
			->where('i.number = :number')
			->setParameter('number', $number)
			->getQuery()
			->getSingleResult();
	}

	/**
	 * @param InvoiceCore $invoice
	 * @return string
	 */
	public function getColorByInvoiceDocument(InvoiceCore $invoice): string
	{
		return $this->exportManager->get()->getColorByInvoiceDocument($invoice);
	}

	/**
	 * @param InvoiceCore $invoice
	 * @return array<string|null>
	 */
	public function getInvoiceTemplateData(InvoiceCore $invoice): array
	{
		return $this->exportManager->get()->getInvoiceTemplateData($invoice);
	}

	/**
	 * @param Company $company
	 * @return array<Invoice|InvoiceProforma|InvoicePayDocument|FixInvoice>|Collection
	 */
	public function getInvoicesByCompany(Company $company): array|Collection
	{
		return $this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('ic')
				->select('ic')
				->where('ic.company = :companyId')
				->setParameter('companyId', $company->getId())
				->andWhere('ic.deleted = :f')
				->setParameter('f', false)
				->getQuery()
				->getResult() ?? [];
	}

}