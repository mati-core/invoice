<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;


use Baraja\Country\CountryManager;
use Baraja\Doctrine\EntityManagerException;
use Baraja\Shop\Currency\CurrencyManagerAccessor;
use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Supplier\Supplier;
use MatiCore\Supplier\SupplierManagerAccessor;
use Nette\Application\AbortException;
use Nette\Forms\Form;
use Nette\Utils\ArrayHash;
use Tracy\Debugger;

#[PublicEndpoint]
class CmsInvoiceSupplierEndpoint extends BaseEndpoint
{
	private ?Supplier $editedSupplier = null;


	public function __construct(
		private SupplierManagerAccessor $supplierManager,
		private CurrencyManagerAccessor $currencyManager,
		private CountryManager $countryManager,
		private EntityManagerInterface $entityManager,
	) {
	}


	public function actionDefault(): void
	{
		$this->countryManager->getByCode('CZE')->getId();
		$this->sendJson(
			[
				'suppliers' => $this->supplierManager->get()->getAll(),
			]
		);
	}


	/**
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
		}
		$this->sendOk();
	}


	/**
	 * @throws AbortException
	 */
	public function handleActive(string $id): void
	{
		try {
			$supplier = $this->supplierManager->get()->getSupplierById($id);
			$supplier->setActive(!$supplier->isActive());
			$this->entityManager->flush();
		} catch (NonUniqueResultException | NoResultException) {
			$this->flashMessage('Požadovaný dodavatel neexistuje.', 'error');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redirect('default');
	}


	/**
	 * @throws AbortException
	 */
	public function createComponentCreateForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název')
			->setRequired('Zadejte název dodavatele');

		$form->addText('deliveryCompany', 'Přepravní společnost');

		$form->addSelect('currency', 'Měna', $this->currencyManager->get()->getCurrenciesForForm())
			->setDefaultValue($this->currencyManager->get()->getMainCurrency()->getId());

		$form->addText('street', 'Ulice, č.p.');

		$form->addText('city', 'Město');

		$form->addText('zipCode', 'PSČ');

		$form->addText('cin', 'IČ');

		$form->addText('tin', 'DIČ');

		$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
			->setDefaultValue($this->countryManager->getByCode('CZE')->getId());

		$form->addSubmit('submit', 'Create');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				$currency = $this->currencyManager->get()->getCurrency($values->currency);
			} catch (NonUniqueResultException | NoResultException) {
				$currency = $this->currencyManager->get()->getMainCurrency();
			}
			try {
				$country = $this->countryManager->getById($values->country);
			} catch (NoResultException | NonUniqueResultException) {
				$country = $this->countryManager->getByCode('CZE');
			}

			$supplier = $this->supplierManager->get()->createSupplier(
				$values->name,
				$currency,
				$values->street ?? '',
				$values->city ?? '',
				$country,
			);
			$supplier->getAddress()->setCin($values->cin === '' ? null : $values->cin);
			$supplier->getAddress()->setTin($values->tin === '' ? null : $values->tin);
			$supplier->setDeliveryCompany(
				$values->deliveryCompany === ''
					? null
					: $values->deliveryCompany
			);
			$this->entityManager->flush();
			$this->flashMessage('Dodavatel ' . $supplier->getName() . ' byl úspěšně přidán do seznamu.', 'success');
			$this->redirect('default');
		};

		return $form;
	}


	public function createComponentEditForm(): Form
	{
		if ($this->editedSupplier === null) {
			throw new \LogicException('Edited Supplier is null!');
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

			$form->onSuccess[] = function (Form $form, ArrayHash $values): void
			{
				try {
					$currency = $this->currencyManager->get()->getCurrency($values->currency);
				} catch (NonUniqueResultException | NoResultException) {
					$currency = $this->currencyManager->get()->getMainCurrency();
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
