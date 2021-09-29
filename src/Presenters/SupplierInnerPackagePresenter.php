<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;


use Baraja\Doctrine\EntityManagerException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Address\CountryManager;
use MatiCore\Address\CountryManagerAccessor;
use MatiCore\Currency\CurrencyException;
use MatiCore\Currency\CurrencyManagerAccessor;
use MatiCore\Form\FormFactoryTrait;
use MatiCore\Supplier\Supplier;
use MatiCore\Supplier\SupplierException;
use MatiCore\Supplier\SupplierManagerAccessor;
use Nette\Application\AbortException;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

/**
 * Class SupplierInnerPackagePresenter
 *
 * @package App\AdminModule\Presenters
 */
class SupplierInnerPackagePresenter extends BaseAdminPresenter
{

	/**
	 * @var SupplierManagerAccessor
	 * @inject
	 */
	public SupplierManagerAccessor $supplierManager;

	/**
	 * @var CurrencyManagerAccessor
	 * @inject
	 */
	public CurrencyManagerAccessor $currencyManager;

	/**
	 * @var CountryManagerAccessor
	 * @inject
	 */
	public CountryManagerAccessor $countryManager;

	use FormFactoryTrait;

	/**
	 * @var Supplier|null
	 */
	private Supplier|null $editedSupplier;


	public function actionDefault(): void
	{
		try {
			$this->countryManager->get()->getCountryByIsoCode('CZE')->getId();
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage(
				'Seznam zemí není nainstalován. <a href="' . $this->link('Country:install') . '">Instalovat..</a>',
				'warning'
			);
		}

		$this->template->suppliers = $this->supplierManager->get()->getSuppliers();
	}


	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionDetail(string $id): void
	{
		try {
			$this->editedSupplier = $this->supplierManager->get()->getSupplierById($id);
			$this->template->supplier = $this->editedSupplier;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný dodavatel neexistuje.', 'error');
			$this->redirect('default');
		}
	}


	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleDelete(string $id): void
	{
		try {
			$supplier = $this->supplierManager->get()->getSupplierById($id);
			$this->supplierManager->get()->removeSupplier($supplier);

			$this->flashMessage('Dodavatel ' . $supplier->getName() . ' byl úspěšně odebrán ze seznamu.', 'info');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný dodavatel neexistuje.', 'error');
		} catch (SupplierException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}

		$this->redirect('default');
	}


	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleActive(string $id): void
	{
		try {
			$supplier = $this->supplierManager->get()->getSupplierById($id);
			$supplier->setActive(!$supplier->isActive());

			$this->entityManager->getUnitOfWork()->commit($supplier);
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný dodavatel neexistuje.', 'error');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redirect('default');
	}


	/**
	 * @return Form
	 * @throws AbortException
	 */
	public function createComponentCreateForm(): Form
	{
		try {
			$form = $this->formFactory->create();

			$form->addText('name', 'Název')
				->setRequired('Zadejte název dodavatele');

			$form->addText('deliveryCompany', 'Přepravní společnost');

			$form->addSelect('currency', 'Měna', $this->currencyManager->get()->getCurrenciesForForm())
				->setDefaultValue($this->currencyManager->get()->getDefaultCurrency()->getId());

			$form->addText('street', 'Ulice, č.p.');

			$form->addText('city', 'Město');

			$form->addText('zipCode', 'PSČ');

			$form->addText('cin', 'IČ');

			$form->addText('tin', 'DIČ');

			$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
				->setDefaultValue($this->countryManager->getCountryByIsoCode('CZE')->getId());

			$form->addSubmit('submit', 'Create');

			$form->onSuccess[] = function (Form $form, ArrayHash $values): void
			{

				try {
					$currency = $this->currencyManager->get()->getCurrencyById($values->currency);
				} catch (NonUniqueResultException | NoResultException $e) {
					$currency = $this->currencyManager->get()->getDefaultCurrency();
				}

				try {
					$country = $this->countryManager->get()->getCountryById($values->country);
				} catch (NoResultException | NonUniqueResultException $e) {
					$country = $this->countryManager->get()->getCountryByIsoCode('CZE');
				}

				$supplier = $this->supplierManager->get()->createSupplier(
					$values->name, $currency, $values->street ?? '', $values->city ?? '', $country
				);
				$supplier->getAddress()->setCin($values->cin === '' ? null : $values->cin);
				$supplier->getAddress()->setTin($values->tin === '' ? null : $values->tin);
				$supplier->setDeliveryCompany(
					$values->deliveryCompany === ''
						? null
						: $values->deliveryCompany
				);

				$this->entityManager->getUnitOfWork()->commit($supplier);

				$this->flashMessage('Dodavatel ' . $supplier->getName() . ' byl úspěšně přidán do seznamu.', 'success');

				$this->redirect('default');
			};

			return $form;
		} catch (CurrencyException $e) {
			$this->flashMessage($e->getMessage(), 'error');

			$this->redirect('Supplier:default');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Seznam států není nainstalován.', 'error');

			$this->redirect('Supplier:default');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Chyba při ukládání do databáze.', 'error');
			$this->redirect('Supplier:default');
		}
	}


	/**
	 * @return Form
	 * @throws AbortException
	 * @throws SupplierException
	 */
	public function createComponentEditForm(): Form
	{
		if ($this->editedSupplier === null) {
			throw new SupplierException('Edited Supplier is null!');
		}

		try {
			$form = $this->formFactory->create();

			$form->addText('name', 'Název')
				->setDefaultValue($this->editedSupplier->getName())
				->setRequired('Zadejte název dodavatele');

			$form->addText('deliveryCompany', 'Přepravní společnost')
				->setDefaultValue($this->editedSupplier->getDeliveryCompany() ?? '');

			$form->addSelect('currency', 'Měna', $this->currencyManager->get()->getCurrenciesForForm())
				->setDefaultValue($this->editedSupplier->getCurrency()->getId());

			$form->addText('street', 'Ulice, č.p.')
				->setDefaultValue($this->editedSupplier->getAddress()->getStreet() ?? '');

			$form->addText('city', 'Město')
				->setDefaultValue($this->editedSupplier->getAddress()->getCity() ?? '');

			$form->addText('zipCode', 'PSČ')
				->setDefaultValue($this->editedSupplier->getAddress()->getZipCode() ?? '');

			$form->addText('cin', 'IČ')
				->setDefaultValue($this->editedSupplier->getAddress()->getCin() ?? '');

			$form->addText('tin', 'DIČ')
				->setDefaultValue($this->editedSupplier->getAddress()->getTin() ?? '');

			$form->addSelect('country', 'Země', $this->countryManager->get()->getCountriesForForm())
				->setDefaultValue(
					$this->editedSupplier->getAddress()->getCountry()
						? $this->editedSupplier->getAddress()->getCountry()->getId()
						: ''
				);

			$form->addSubmit('submit', 'Save');

			/**
			 * @param Form $form
			 * @param ArrayHash $values
			 */
			$form->onSuccess[] = function (Form $form, ArrayHash $values): void
			{

				try {
					$currency = $this->currencyManager->get()->getCurrencyById($values->currency);
				} catch (NonUniqueResultException | NoResultException $e) {
					$currency = $this->currencyManager->get()->getDefaultCurrency();
				}

				$this->editedSupplier->setCurrency($currency);

				try {
					$country = $this->countryManager->get()->getCountryById($values->country);
				} catch (NoResultException | NonUniqueResultException $e) {
					$country = $this->countryManager->get()->getCountryByIsoCode('CZE');
				}

				$this->editedSupplier->getAddress()->setStreet($values->street === '' ? null : $values->street);
				$this->editedSupplier->getAddress()->setCity($values->city === '' ? null : $values->city);
				$this->editedSupplier->getAddress()->setZipCode($values->zipCode === '' ? null : $values->zipCode);
				$this->editedSupplier->getAddress()->setCin($values->cin === '' ? null : $values->cin);
				$this->editedSupplier->getAddress()->setTin($values->tin === '' ? null : $values->tin);
				$this->editedSupplier->getAddress()->setCountry($country);
				$this->editedSupplier->setDeliveryCompany(
					$values->deliveryCompany === ''
						? null
						: $values->deliveryCompany
				);

				$this->entityManager->flush();

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');

				$this->redirect('default');
			};

			return $form;
		} catch (CurrencyException $e) {
			$this->flashMessage($e->getMessage(), 'error');

			$this->redirect('Supplier:default');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Seznam států není nainstalován.', 'error');

			$this->redirect('Supplier:default');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Chyba při ukládání do databáze.', 'error');
		}
	}

}
