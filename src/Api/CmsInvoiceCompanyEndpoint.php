<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;


use Baraja\Country\CountryManagerAccessor;
use Baraja\Doctrine\EntityManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use MatiCore\Company\Company;
use MatiCore\Company\CompanyContact;
use MatiCore\Company\CompanyException;
use MatiCore\Company\CompanyInvoiceStatisticsControl;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Company\CompanyStock;
use MatiCore\Invoice\Invoice;
use MatiCore\Invoice\InvoiceManagerAccessor;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Tracy\Debugger;

#[PublicEndpoint]
class CmsInvoiceCompanyEndpoint extends BaseEndpoint
{
	protected string $pageRight = 'page__company';

	private Data|null $aresData = null;

	private Company|null $editedCompany = null;

	private CompanyStock|null $editedStock = null;

	private CompanyContact|null $editedContact = null;

	private int $returnButton = 0;


	public function __construct(
		private EntityManager $entityManager,
		private CompanyManagerAccessor $companyManager,
		private CountryManagerAccessor $countryManager,
		private CurrencyManagerAccessor $currencyManager,
		private InvoiceManagerAccessor $invoiceManager,
		private CompanyInvoiceStatisticsControl $invoiceStatisticsControl,
	) {
	}


	public function actionDefault(): void
	{
		$companies = $this->companyManager->get()->getCompanies();

		$this->template->companyCount = count($companies);

		try {
			$this->template->stockCount = $this->entityManager->getRepository(CompanyStock::class)
					->createQueryBuilder('stock')
					->select('count(stock)')
					->getQuery()
					->getSingleScalarResult() ?? 0;
		} catch (NoResultException | NonUniqueResultException) {
			$this->template->stockCount = 0;
		}
		$this->template->companies = $companies;
	}


	public function actionCreate(string $ic = null): void
	{
		if ($ic !== null && $ic !== '') {
			try {
				$this->aresData = $this->companyManager->get()->getDataFromAres($ic);

				if ($this->aresData->active === false) {
					$this->flashMessage('Tato firma je vedena v datab??zi ARES jako neaktivn??.', 'warning');
				}
			} catch (IdentificationNumberNotFoundException) {
				$this->aresData = null;

				$this->flashMessage('Pod zadan??m I?? nebyla v datab??zi ARES nalezena ????dn?? firma.', 'warning');
			}
		}
	}


	public function actionEdit(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}


	public function actionEditStock(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->get()->getCompanyStockById($id);
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
			$this->template->stock = $this->editedStock;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? pobo??ka neexistuje.', 'danger');

			$this->redirect('default');
		}
	}


	public function actionDetail(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionInvoice(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionDetailStock(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->get()->getCompanyStockById($id);
			$this->template->stock = $this->editedStock;
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? pobo??ka neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionCreateStock(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionContact(string $companyId, string $companyStockId = null): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($companyId);

			if ($companyStockId !== null) {
				$this->editedStock = $this->companyManager->get()->getCompanyStockById($companyStockId);
			}

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;

			if ($this->editedStock !== null) {
				$this->template->contactList = $this->editedStock->getContacts();
			} else {
				$this->template->contactList = $this->editedCompany->getContacts();
			}
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionCreateContact(string $companyId, string $companyStockId = null): void
	{
		try {
			$this->editedCompany = $this->companyManager->get()->getCompanyById($companyId);

			if ($companyStockId !== null) {
				$this->editedStock = $this->companyManager->get()->getCompanyStockById($companyStockId);
			}

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
			$this->redirect('default');
		}
	}


	public function actionEditContact(string $id): void
	{
		try {
			$this->editedContact = $this->companyManager->get()->getContactById($id);
			$this->editedCompany = $this->editedContact->getCompany();
			$this->editedStock = $this->editedContact->getCompanyStock();

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;
			$this->template->contact = $this->editedContact;
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? kontakt neexistuje', 'danger');

			$this->redirect('default');
		}
	}


	public function actionInvoicedItems(string $id): void
	{
		try {
			$company = $this->companyManager->get()->getCompanyById($id);

			$this->template->company = $company;
			$this->template->list = $this->companyManager->get()->getInvoicedItems($company);
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Firma nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}


	public function handleBlackList(string $id): void
	{
		try {
			$company = $this->companyManager->get()->getCompanyById($id);
			$company->setBlackList(!$company->isBlackList());
			$this->entityManager->flush();

			$this->redirect('detail', ['id' => $company->getId()]);
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}


	public function handleRemove(string $id): void
	{
		try {
			$company = $this->companyManager->get()->getCompanyById($id);
			$this->companyManager->get()->removeCompany($company);

			$this->flashMessage('Firma byla odebr??na ze seznamu.');
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? firma neexistuje.', 'danger');
		} catch (CompanyException $e) {
			$this->flashMessage($e->getMessage(), 'danger');
		}

		$this->redirect('default');
	}


	public function handleRemoveStock(string $id): void
	{
		try {
			$stock = $this->companyManager->get()->getCompanyStockById($id);
			$company = $stock->getCompany();
			$this->companyManager->get()->removeCompanyStock($stock);

			$this->flashMessage('Pobo??ka byla odebr??na ze seznamu.');

			$this->redirect('detail', ['id' => $company->getId()]);
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Po??adovan?? pobo??ka neexistuje.', 'danger');
		} catch (CompanyException $e) {
			$this->flashMessage($e->getMessage(), 'danger');
		}

		$this->redirect('default');
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function createComponentCreateForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'N??zev spole??nosti')
			->setDefaultValue($this->aresData->company ?? '')
			->setRequired('Zadejte n??zev spole??nosti.');

		$form->addText('ic', 'I??')
			->setDefaultValue($this->aresData->in ?? '');

		$form->addText('dic', 'DI??')
			->setDefaultValue($this->aresData->tin ?? '');

		$street = $this->aresData->street ?? '';
		$houseNumber = $this->aresData->house_number ?? '';

		$form->addText('street', 'Ulice, ??.p.')
			->setDefaultValue($street !== '' && $houseNumber !== '' ? $street . ' ' . $houseNumber : '')
			->setRequired('Zadejte ulici a ????slo popisn??');

		$form->addText('city', 'M??sto')
			->setDefaultValue($this->aresData->city ?? '')
			->setRequired('Zadejte m??sto');

		$form->addText('zipCode', 'PS??')
			->setDefaultValue($this->aresData->zip ?? '')
			->setRequired('Zadejte po??tovn?? sm??rovac?? ????slo');

		$form->addSelect('country', 'Zem??', $this->countryManager->get()->getCountriesForForm())
			->setDefaultValue($this->countryManager->get()->getCountryByIsoCode('CZE')->getId())
			->setRequired('Vyberte zemi');

		$form->addSelect('currency', 'M??na', $this->currencyManager->get()->getCurrenciesForForm())
			->setDefaultValue($this->currencyManager->get()->getDefaultCurrency()->getId())
			->setRequired('Vyberte m??nu');

		$form->addText('invoiceDuaDayCount', 'V??choz?? splatnost faktur')
			->setDefaultValue(14)
			->setRequired('Zadejte v??choz?? splatnost faktur');

		$form->addSelect('type', 'Typ z??kazn??ka', $this->companyManager->get()->getCompanyTypes())
			->setDefaultValue($this->companyManager->get()->getDefaultCompanyType())
			->setRequired('Vyberte typ z??kazn??ka');

		$form->addCheckbox('groupInvoices', 'Seskupovat PDF p??ed odesl??n??m');

		$form->addTextArea('note', 'Pozn??mka');

		$form->addSubmit('submit', 'P??idat');

		$form->onValidate[] = function (Form $form, ArrayHash $value): void
		{
			try {
				if ($value->ic !== null) {
					$this->companyManager->get()->getCompanyByCIN($value->ic);

					$form->addError('Tato firma je ji?? v syst??mu zavedena.');
				}
			} catch (NoResultException | NonUniqueResultException) {

			}
		};

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$invoiceAddress = new Address($values->street, $values->city);
				$invoiceAddress->setCompanyName($values->name);
				$invoiceAddress->setCin($values->ic === '' ? null : $values->ic);
				$invoiceAddress->setTin($values->dic === '' ? null : $values->dic);
				$invoiceAddress->setZipCode($values->zipCode);
				$invoiceAddress->setCountry($this->countryManager->get()->getCountryById($values->country));

				$this->entityManager->persist($invoiceAddress);

				$currency = $this->currencyManager->get()->getCurrencyById($values->currency);

				$company = new Company($invoiceAddress, $currency);
				$company->setType($values->type);
				$company->setNote($values->note);
				$company->setSendInvoicesInOneFile($values->groupInvoices);
				$company->setInvoiceDueDayCount((int) $values->invoiceDuaDayCount);

				$this->entityManager->persist($company);
				$this->entityManager->flush();

				$this->flashMessage('Firma byla ??sp????n?? p??id??na do seznamu.', 'success');
				$this->redirect('detail', ['id' => $company->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'danger');
			}
		};

		return $form;
	}


	public function createComponentEditForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'N??zev spole??nosti')
			->setDefaultValue($this->editedCompany->getName())
			->setRequired('Zadejte n??zev spole??nosti.');

		$form->addText('cin', 'I??')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getCin() ?? '');

		$form->addText('tin', 'DI??')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getTin() ?? '');

		$form->addText('street', 'Ulice, ??.p.')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getStreet())
			->setRequired('Zadejte ulici a ????slo popisn??');

		$form->addText('city', 'M??sto')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getCity())
			->setRequired('Zadejte m??sto');

		$form->addText('zipCode', 'PS??')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getZipCode())
			->setRequired('Zadejte po??tovn?? sm??rovac?? ????slo');

		$form->addSelect('country', 'Zem??', $this->countryManager->get()->getCountriesForForm())
			->setDefaultValue(
				$this->editedCompany->getInvoiceAddress()->getCountry() !== null
					? $this->editedCompany->getInvoiceAddress()->getCountry()->getId()
					: null
			)
			->setRequired('Vyberte zemi');

		$form->addSelect('currency', 'M??na', $this->currencyManager->get()->getCurrenciesForForm())
			->setDefaultValue($this->editedCompany->getCurrency()->getId())
			->setRequired('Vyberte m??nu');

		$form->addText('invoiceDuaDayCount', 'V??choz?? splatnost faktur')
			->setDefaultValue($this->editedCompany->getInvoiceDueDayCount())
			->setRequired('Zadejte v??choz?? splatnost faktur');

		$form->addSelect('type', 'Typ z??kazn??ka', $this->companyManager->get()->getCompanyTypes())
			->setDefaultValue($this->editedCompany->getType())
			->setRequired('Vyberte typ z??kazn??ka');

		$form->addCheckbox('groupInvoices', 'Seskupovat PDF p??ed odesl??n??m')
			->setDefaultValue($this->editedCompany->isSendInvoicesInOneFile());

		$form->addTextArea('note', 'Pozn??mka')
			->setDefaultValue($this->editedCompany->getNote());

		$form->addSubmit('submit', 'Ulo??it');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$invoiceAddress = $this->editedCompany->getInvoiceAddress();
				$invoiceAddress->setStreet($values->street);
				$invoiceAddress->setCity($values->city);
				$invoiceAddress->setCompanyName($values->name);
				$invoiceAddress->setCin($values->in === '' ? null : $values->in);
				$invoiceAddress->setTin($values->tin === '' ? null : $values->tin);
				$invoiceAddress->setZipCode($values->zipCode);
				$invoiceAddress->setCountry($this->countryManager->get()->getCountryById($values->country));

				$currency = $this->currencyManager->get()->getCurrencyById($values->currency);

				$this->editedCompany->setName($values->name);
				$this->editedCompany->setCurrency($currency);
				$this->editedCompany->setType($values->type);
				$this->editedCompany->setNote($values->note);
				$this->editedCompany->setSendInvoicesInOneFile($values->groupInvoices);
				$this->editedCompany->setInvoiceDueDayCount((int) $values->invoiceDuaDayCount);
				$this->entityManager->flush();

				$this->flashMessage('Zm??ny byly ??sp????n?? ulo??eny.', 'success');
				$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'danger');
			}
		};

		return $form;
	}


	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function createComponentCreateStockForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'N??zev pobo??ky')
			->setRequired('Zadejte n??zev pobo??ky.');

		$form->addText('street', 'Ulice, ??.p.')
			->setRequired('Zadejte ulici a ????slo popisn??');

		$form->addText('city', 'M??sto')
			->setRequired('Zadejte m??sto');

		$form->addText('zipCode', 'PS??')
			->setRequired('Zadejte po??tovn?? sm??rovac?? ????slo');

		$form->addSelect('country', 'Zem??', $this->countryManager->get()->getCountriesForForm())
			->setDefaultValue($this->countryManager->get()->getCountryByIsoCode('CZE')->getId())
			->setRequired('Vyberte zemi');

		$form->addTextArea('note', 'Pozn??mka');

		$form->addSubmit('submit', 'P??idat');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$address = new Address($values->street, $values->city);
				$address->setCompanyName($this->editedCompany->getInvoiceAddress()->getCompanyName());
				$address->setCin($this->editedCompany->getInvoiceAddress()->getCin());
				$address->setTin($this->editedCompany->getInvoiceAddress()->getTin());
				$address->setZipCode($values->zipCode);
				$address->setCountry($this->countryManager->get()->getCountryById($values->country));

				$this->entityManager->persist($address);

				$stock = new CompanyStock(
					$this->editedCompany,
					$values->name,
					$address
				);

				$stock->setNote($values->note === '' ? null : $values->note);

				$this->entityManager->persist($stock);
				$this->editedCompany->getStocks()->add($stock);
				$this->entityManager->flush();

				$this->flashMessage('Pobo??ka byla ??sp????n?? p??id??na.', 'success');

				$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'danger');
			}
		};

		return $form;
	}


	public function createComponentEditStockForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}
		if ($this->editedStock === null) {
			throw new CompanyException('Edited stock is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'N??zev pobo??ky')
			->setDefaultValue($this->editedStock->getName())
			->setRequired('Zadejte n??zev pobo??ky.');

		$form->addText('street', 'Ulice, ??.p.')
			->setDefaultValue($this->editedStock->getAddress()->getStreet())
			->setRequired('Zadejte ulici a ????slo popisn??');

		$form->addText('city', 'M??sto')
			->setDefaultValue($this->editedStock->getAddress()->getCity())
			->setRequired('Zadejte m??sto');

		$form->addText('zipCode', 'PS??')
			->setDefaultValue($this->editedStock->getAddress()->getZipCode())
			->setRequired('Zadejte po??tovn?? sm??rovac?? ????slo');

		$form->addSelect('country', 'Zem??', $this->countryManager->get()->getCountriesForForm())
			->setDefaultValue(
				$this->editedStock->getAddress()->getCountry() !== null
					? $this->editedStock->getAddress()->getCountry()->getId()
					: null
			)
			->setRequired('Vyberte zemi');

		$form->addTextArea('note', 'Pozn??mka')
			->setDefaultValue($this->editedStock->getNote() ?? '');

		$form->addSubmit('submit', 'Ulo??it');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$address = $this->editedStock->getAddress();
				$address->setStreet($values->street);
				$address->setCity($values->city);
				$address->setCompanyName($this->editedCompany->getInvoiceAddress()->getCompanyName());
				$address->setCin($this->editedCompany->getInvoiceAddress()->getCin());
				$address->setTin($this->editedCompany->getInvoiceAddress()->getTin());
				$address->setZipCode($values->zipCode);
				$address->setCountry($this->countryManager->get()->getCountryById($values->country));

				$this->editedStock->setName($values->name);
				$this->editedStock->setNote($values->note === '' ? null : $values->note);
				$this->entityManager->flush();

				$this->flashMessage('Zm??ny byly ??sp????n?? ulo??eny.', 'success');
				$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'danger');
			}
		};

		return $form;
	}


	public function createComponentCreateContactForm(): Form
	{
		$form = $this->formFactory->create();

		$stockList = [];
		foreach ($this->editedCompany->getStocks() as $stock) {
			$stockList[$stock->getId()] = $stock->getName();
		}

		$companyStock = $this->editedStock !== null ? $this->editedStock->getId() : null;

		$form->addSelect('companyStock', 'Pobo??ka', $stockList)
			->setPrompt('V??echny pobo??ky')
			->setDefaultValue($companyStock);

		$form->addText('firstName', 'Jm??no');

		$form->addText('lastName', 'P????jmen??')
			->setRequired('Zadejte p????jmen??');

		$form->addText('email', 'E-mail');

		$form->addText('role', 'Pozice');

		$form->addText('phone', 'Telefon');

		$form->addText('mobile', 'Mobil');

		$form->addText('note', 'Pozn??mka');

		$form->addCheckbox('sendInvoice', 'Zas??lat faktury');
		$form->addCheckbox('sendOffer', 'Zas??lat nab??dky');
		$form->addCheckbox('sendOrder', 'Zas??lat objedn??vky');
		$form->addCheckbox('sendMarketing', 'Zas??lat marketing');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			$companyStock = null;
			if ($values->companyStock !== null) {
				foreach ($this->editedCompany->getStocks() as $stock) {
					if ($stock->getId() === $values->companyStock) {
						$companyStock = $stock;
						break;
					}
				}
			}

			$contact = new CompanyContact($this->editedCompany, $values->lastName);

			$contact->setCompanyStock($companyStock);
			$contact->setFirstName($values->firstName !== '' ? $values->firstName : null);
			$contact->setEmail($values->email !== '' ? $values->email : null);
			$contact->setRole($values->role !== '' ? $values->role : null);
			$contact->setPhone($values->phone !== '' ? $values->phone : null);
			$contact->setMobilePhone($values->mobile !== '' ? $values->mobile : null);
			$contact->setNote($values->note !== '' ? $values->note : null);
			$contact->setSendInvoice($values->sendInvoice);
			$contact->setSendOffer($values->sendOffer);
			$contact->setSendOrder($values->sendOrder);
			$contact->setSendMarketing($values->sendMarketing);

			$this->entityManager->persist($contact);
			$this->entityManager->flush();

			$this->flashMessage('Kontakt byl ??sp????n?? vytvo??en.', 'success');

			if ($this->editedStock !== null) {
				$this->redirect(
					'contact',
					['companyId' => $this->editedCompany->getId(), 'companyStockId' => $this->editedStock->getId()]
				);
			} else {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId()]);
			}
		};

		return $form;
	}


	public function createComponentEditContactForm(): Form
	{
		$form = $this->formFactory->create();

		$stockList = [];
		foreach ($this->editedCompany->getStocks() as $stock) {
			$stockList[$stock->getId()] = $stock->getName();
		}

		$companyStock = $this->editedStock !== null ? $this->editedStock->getId() : null;

		$form->addSelect('companyStock', 'Pobo??ka', $stockList)
			->setPrompt('V??echny pobo??ky')
			->setDefaultValue($companyStock);

		$form->addText('firstName', 'Jm??no')
			->setDefaultValue($this->editedContact->getFirstName());

		$form->addText('lastName', 'P????jmen??')
			->setRequired('Zadejte p????jmen??')
			->setDefaultValue($this->editedContact->getLastName());

		$form->addText('email', 'E-mail')
			->setDefaultValue($this->editedContact->getEmail());

		$form->addText('role', 'Pozice')
			->setDefaultValue($this->editedContact->getRole() ?? '');

		$form->addText('phone', 'Telefon')
			->setDefaultValue($this->editedContact->getPhone() ?? '');

		$form->addText('mobile', 'Mobil')
			->setDefaultValue($this->editedContact->getMobilePhone() ?? '');

		$form->addText('note', 'Pozn??mka')
			->setDefaultValue($this->editedContact->getNote() ?? '');

		$form->addCheckbox('sendInvoice', 'Zas??lat faktury')
			->setDefaultValue($this->editedContact->isSendInvoice());

		$form->addCheckbox('sendOffer', 'Zas??lat nab??dky')
			->setDefaultValue($this->editedContact->isSendOffer());

		$form->addCheckbox('sendOrder', 'Zas??lat objedn??vky')
			->setDefaultValue($this->editedContact->isSendOrder());

		$form->addCheckbox('sendMarketing', 'Zas??lat marketing')
			->setDefaultValue($this->editedContact->isSendMarketing());

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			$companyStock = null;
			if ($values->companyStock !== null) {
				foreach ($this->editedCompany->getStocks() as $stock) {
					if ($stock->getId() === $values->companyStock) {
						$companyStock = $stock;
						break;
					}
				}
			}

			$this->editedContact->setFirstName($values->firstName !== '' ? $values->firstName : null);
			$this->editedContact->setLastName($values->lastName);
			$this->editedContact->setEmail($values->email !== '' ? $values->email : null);
			$this->editedContact->setCompanyStock($companyStock);
			$this->editedContact->setRole($values->role !== '' ? $values->role : null);
			$this->editedContact->setPhone($values->phone !== '' ? $values->phone : null);
			$this->editedContact->setMobilePhone($values->mobile !== '' ? $values->mobile : null);
			$this->editedContact->setNote($values->note !== '' ? $values->note : null);
			$this->editedContact->setSendInvoice($values->sendInvoice);
			$this->editedContact->setSendOffer($values->sendOffer);
			$this->editedContact->setSendOrder($values->sendOrder);
			$this->editedContact->setSendMarketing($values->sendMarketing);

			$this->entityManager->persist($this->editedContact);
			$this->entityManager->flush();

			$this->flashMessage('Zm??ny byly ??sp????n?? ulo??eny.', 'success');

			if ($this->editedStock !== null) {
				$this->redirect(
					'contact',
					['companyId' => $this->editedCompany->getId(), 'companyStockId' => $this->editedStock->getId()]
				);
			} else {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId()]);
			}
		};

		return $form;
	}


	public function handleDeleteContact(string $id): void
	{
		try {
			$contact = $this->companyManager->get()->getContactById($id);
			try {
				$this->entityManager->remove($contact);
				$this->entityManager->flush();
				$this->flashMessage('Kontak byl smaz??n.');
			} catch (EntityManagerException $e) {
				$this->flashMessage('P??i odstra??ov??n?? kontaktu nastala chyba.', 'error');
			}

			if ($contact->getCompanyStock() !== null) {
				$this->redirect(
					'contact', [
						'companyId' => $contact->getCompany()->getId(),
						'companyStockId' => $contact->getCompanyStock()->getId(),
					]
				);
			} else {
				$this->redirect(
					'contact', [
						'companyId' => $contact->getCompany()->getId(),
					]
				);
			}
		} catch (NoResultException | NonUniqueResultException) {
			$this->flashMessage('Po??adovan?? kontakt neexistuje.', 'error');
			$this->redirect('default');
		}
	}


	public function createComponentCompanyNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('note', 'Pozn??mka')
			->setDefaultValue($this->editedCompany->getNote() ?? '');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$this->editedCompany->setNote($values->note);
				$this->entityManager->flush();
				$this->flashMessage('Zm??ny byly ??sp????n?? ulo??eny.', 'success');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'error');
			}

			$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
		};

		return $form;
	}


	public function createComponentCompanyStockNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('note', 'Pozn??mka')
			->setDefaultValue($this->editedStock->getNote() ?? '');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$this->editedStock->setNote($values->note);
				$this->entityManager->flush();
				$this->flashMessage('Zm??ny byly ??sp????n?? ulo??eny.', 'success');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('P??i ukl??d??n?? do datab??ze nastala chyba.', 'error');
			}

			$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
		};

		return $form;
	}


	public function createComponentInvoiceTable(string $name): MatiDataGrid
	{
		$currency = $this->currencyManager->get()->getDefaultCurrency();

		$grid = new MatiDataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(Invoice::class)
				->createQueryBuilder('invoice')
				->where('invoice.deleted = :f')
				->setParameter('f', 0)
				->andWhere('invoice.company = :company')
				->setParameter('company', $this->editedCompany->getId())
				->andWhere('invoice.type IN (:types)')
				->setParameter('types', [
					Invoice::TYPE_REGULAR,
					Invoice::TYPE_PROFORMA,
				])
				->orderBy('invoice.number', 'DESC')
		);

		$grid->setRowCallback(
			static function (Invoice $invoice, Html $row): void
			{
				$status = $invoice->getStatus();
				if ($status === Invoice::STATUS_ACCEPTED) {
					$row->addClass('table-success');

					return;
				}

				if ($status === Invoice::STATUS_DENIED) {
					$row->addClass('table-danger');

					return;
				}

				if ($status === Invoice::STATUS_CREATED) {
					$row->addClass('table-warning');

					return;
				}

				if ($status === Invoice::STATUS_PAY_ALERT_THREE) {
					$row->addClass('table-danger');

					return;
				}
			}
		);

		$grid->addColumnText('number', '????slo')
			->setRenderer(
				function (Invoice $invoice): string
				{
					$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);

					return '<a href="' . $link . '">' . $invoice->getNumber() . '</a>'
						. '<br>'
						. '<small class="' . $invoice->getColor() . '">'
						. htmlspecialchars($invoice->getLabel())
						. '</small>';;
				}
			)
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(
				function (Invoice $invoice): string
				{
					if ($invoice->getCompany() !== null) {
						$link = $this->link('Company:detail', ['id' => $invoice->getCompany()->getId()]);

						$ret = '<a href="' . $link . '">' . Strings::truncate($invoice->getCustomerName(), 60) . '</a>';
					} else {
						$ret = '<span class="text-blue">' . $invoice->getCustomerName() . '</span>';
					}

					return $ret
						. '<br>'
						. '<small>'
						. $invoice->getCustomerAddress() . ', '
						. $invoice->getCustomerCity() . ', '
						. $invoice->getCustomerPostalCode()
						. '</small>';

				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Vystaveno')
			->setRenderer(
				static function (Invoice $invoiceCore): string
				{
					return $invoiceCore->getDate()->format('d.m.Y') . '<br><small>' . $invoiceCore->getCreatedByUserId()
							->getName() . '</small>';
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('taxDate', 'Da??. pln??n??')
			->setRenderer(
				function (Invoice $invoiceCore): string
				{
					if ($invoiceCore->isProforma()) {
						$invoice = $invoiceCore->getSubInvoice();
						if ($invoice !== null) {
							$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);
							$str = '<small><a href="' . $link . '" title="Faktura"><i class="fas fa-file-invoice"></i>&nbsp;' . $invoice->getNumber(
								) . '</a></small>';
						} else {
							$str = '&nbsp;';
						}

						return '<span class="text-info"><small>Z??loha</small></span><br>' . $str;
					}

					$str = '<small>&nbsp;</small>';

					/** @var Invoice $fixInvoice */
					$fixInvoice = $invoiceCore->getFixInvoice();
					if ($fixInvoice !== null) {
						$link = $this->link('Invoice:show', ['id' => $fixInvoice->getId()]);
						$str = '<small><a href="' . $link . '" title="Dobropis" style="color: rgb(194, 0, 64);">'
							. '<i class="fas fa-file-invoice"></i>&nbsp;'
							. $fixInvoice->getNumber();
						if (
							$fixInvoice->getAcceptStatus1() !== Invoice::STATUS_ACCEPTED
							|| $fixInvoice->getAcceptStatus2() !== Invoice::STATUS_ACCEPTED
						) {
							if ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_WAITING) {
								$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
							} elseif ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_DENIED) {
								$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
							} elseif ($fixInvoice->getAcceptStatus1() === Invoice::STATUS_ACCEPTED) {
								$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
							}

							if ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_WAITING) {
								$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
							} elseif ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_DENIED) {
								$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
							} elseif ($fixInvoice->getAcceptStatus2() === Invoice::STATUS_ACCEPTED) {
								$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
							}
						}
						$str .= '</a></small>';
					}

					return $invoiceCore->getTaxDate()->format('d.m.Y') . '<br>' . $str;
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('dueDate', 'Splatnost')
			->setRenderer(
				function (Invoice $invoiceCore): string
				{
					$ret = $invoiceCore->getDueDate()->format('d.m.Y');

					if ($invoiceCore->isPaid() && $invoiceCore->getPayDate() !== null) {
						$ret .= '<br><small class="text-success"><i class="fas fa-coins text-warning" title="Uhrazeno"></i>&nbsp;' . $invoiceCore->getPayDate(
							)->format('d.m.Y') . '</small>';

						if ($invoiceCore->isProforma()) {
							$payDocument = $invoiceCore->getPayDocument();
							if ($payDocument !== null) {
								$link = $this->link('Invoice:show', ['id' => $payDocument->getId()]);
								$ret .= '&nbsp;<small><a href="' . $link . '" style="color: rgb(75, 0, 150);" title="Doklad k p??ijat?? platb??"><i class="fas fa-file-invoice-dollar"></i></a></small>';
							}
						}
					} else {
						$ret .= '<br>';
						$diff = $invoiceCore->getPayDateDiff();
						if ($diff < -4) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' dn??</small>';
						} elseif ($diff < -1) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' dny</small>';
						} elseif ($diff < 0) {
							$ret .= '<small class="text-success">zb??v??&nbsp;' . -$diff . ' den</small>';
						} elseif ($diff === 0) {
							$ret .= '<small class="text-success">Dnes</small>';
						} elseif ($diff > 4) {
							$ret .= '<small class="text-danger">' . $diff . ' dn?? po splatnosti</small>';
						} elseif ($diff > 1) {
							$ret .= '<small class="text-danger">' . $diff . ' dny po splatnosti</small>';
						} else {
							$ret .= '<small class="text-danger">' . $diff . ' den po splatnosti</small>';
						}
					}

					return $ret;
				}
			)
			->setTemplateEscaping(false);

		$grid->addColumnText('price', '????stka')
			->setRenderer(
				static function (Invoice $invoiceCore) use ($currency): string
				{
					$totalPrice = $invoiceCore->getTotalPrice();
					if ($invoiceCore->isRegular()) {
						$fixInvoice = $invoiceCore->getFixInvoice();
						if ($fixInvoice !== null) {
							$totalPrice += $fixInvoice->getTotalPrice();
						}
					}

					if ($totalPrice < 0) {
						return '<b class="text-danger">'
							. Number::formatPrice($totalPrice, $invoiceCore->getCurrency(), 2)
							. '</b>'
							. '<br>'
							. '<small>'
							. Number::formatPrice($totalPrice * $invoiceCore->getRate(), $currency, 2)
							. '</small>';
					}

					return '<b>' . Number::formatPrice($totalPrice, $invoiceCore->getCurrency(), 2) . '</b>'
						. '<br>'
						. '<small>'
						. Number::formatPrice($totalPrice * $invoiceCore->getRate(), $currency, 2)
						. '</small>';
				}
			)
			->setAlign('right')
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addColumnText('accept', 'Schv??len??')
			->setRenderer(
				function (Invoice $invoiceCore): string
				{
					if ($invoiceCore->isSubmitted() === false) {
						return '<span class="text-warning">Editace</span>';
					}

					$ret = '';
					$link = $this->link('Invoice:show', ['id' => $invoiceCore->getId()]);

					if ($invoiceCore->getAcceptStatus1() === 'denied') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
					} elseif ($invoiceCore->getAcceptStatus1() === 'waiting') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
					} elseif ($invoiceCore->getAcceptStatus1() === 'accepted') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
					}

					$ret .= '&nbsp;';

					if ($invoiceCore->getAcceptStatus2() === 'denied') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
					} elseif ($invoiceCore->getAcceptStatus2() === 'waiting') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
					} elseif ($invoiceCore->getAcceptStatus2() === 'accepted') {
						$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
					}

					return $ret;
				}
			)
			->setAlign('center')
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(
				function (Invoice $invoiceCore)
				{
					$link = $this->link('Invoice:show', ['id' => $invoiceCore->getId()]);

					return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
				}
			);

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');

		//Cislo faktury
		$grid->addFilterText('number', '????slo:');

		//Stav
		$statusList = [
			'' => 'V??e',
			'paid' => 'Uhrazen??',
			'unpaid' => 'Neuhrazen??',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(
				static function (QueryBuilder $qb, string $status): QueryBuilder
				{
					if ($status === 'unpaid') {
						$qb->andWhere('invoice.payDate IS NULL');
					} elseif ($status === 'paid') {
						$qb->andWhere('invoice.payDate IS NOT NULL');
					}

					return $qb;
				}
			);

		$grid->setOuterFilterRendering();

		return $grid;
	}


	public function createComponentInvoiceStatistics(): CompanyInvoiceStatisticsControl
	{
		return $this->invoiceStatisticsControl;
	}
}
