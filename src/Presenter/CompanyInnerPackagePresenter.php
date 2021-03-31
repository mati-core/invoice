<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Model\Company;
use App\Model\CompanyContact;
use App\Model\CompanyException;
use App\Model\CompanyManager;
use App\Model\CompanyPriceListItem;
use App\Model\CompanyStock;
use App\Model\CompanyWorkPriceType;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;
use MatiCore\Address\CountryManager;
use MatiCore\Address\Entity\Address;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Application\UI\InvalidLinkException;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 * Class CompanyInnerPackagePresenter
 * @package App\AdminModule\Presenters
 */
class CompanyInnerPackagePresenter extends BaseAdminPresenter

{

	/**
	 * @var string
	 */
	protected $pageRight = 'page__company';

	/**
	 * @var CompanyManager
	 * @inject
	 */
	public $companyManager;

	/**
	 * @var CartServiceManager
	 * @inject
	 */
	public $cartServiceManager;

	/**
	 * @var CountryManager
	 * @inject
	 */
	public $countryManager;

	/**
	 * @var CurrencyManager
	 * @inject
	 */
	public $currencyManager;

	/**
	 * @var BrandManager
	 * @inject
	 */
	public $brandManager;

	/**
	 * @var VehicleManager
	 * @inject
	 */
	public $vehicleManager;

	/**
	 * @var CartManager
	 * @inject
	 */
	public $cartManager;

	/**
	 * @var UnitManager
	 * @inject
	 */
	public $unitManager;

	/**
	 * @var ServiceUserManager
	 * @inject
	 */
	public $serviceUserManager;

	/**
	 * @var StockManager
	 * @inject
	 */
	public $stockManager;

	/**
	 * @var FormFactory
	 * @inject
	 */
	public $formFactory;

	/**
	 * @var InvoiceManager
	 * @inject
	 */
	public $invoiceManager;

	/**
	 * @var PdfExportManager
	 * @inject
	 */
	public $pdfExportManager;

	/**
	 * @var OfferManager
	 * @inject
	 */
	public $offerManager;

	/**
	 * @var ScheduleManager
	 * @inject
	 */
	public $scheduleManager;

	/**
	 * @var DocumentManager
	 * @inject
	 */
	public $documentManager;

	/**
	 * @var Data|null
	 */
	private $aresData;

	/**
	 * @var Company|null
	 */
	private $editedCompany;

	/**
	 * @var CompanyStock|null
	 */
	private $editedStock;

	/**
	 * @var Cart|null
	 */
	private $editedCart;

	/**
	 * @var CartService|null
	 */
	private $editedCartService;

	/**
	 * @var CartServiceReport|null
	 */
	private $editedCartServiceReport;

	/**
	 * @var CartServiceOperation|null
	 */
	private $editedCartServiceOperation;

	/**
	 * @var CartServiceItem|null
	 */
	private $editedCartServiceItem;

	/**
	 * @var User[]|null
	 */
	private $serviceUsers;

	/**
	 * @var StockItem|null
	 */
	private $usedStockItem;

	/**
	 * @var CompanyContact|null
	 */
	private $editedContact;

	/**
	 * @var CompanyPriceListItem
	 */
	private $editedPriceListItem;

	/**
	 * @var int
	 */
	private $returnButton = 0;

	/**
	 * @throws NonUniqueResultException
	 */
	public function actionDefault(): void
	{
		$companies = $this->companyManager->getCompanies();

		$this->template->companyCount = count($companies);
		$this->template->stockCount = $this->entityManager->getRepository(CompanyStock::class)
				->createQueryBuilder('stock')
				->select('count(stock)')
				->getQuery()
				->getSingleScalarResult() ?? 0;
		$this->template->cartCount = $this->entityManager->getRepository(Cart::class)
				->createQueryBuilder('stock')
				->select('count(stock)')
				->getQuery()
				->getSingleScalarResult() ?? 0;
		$this->template->serviceCount = $this->entityManager->getRepository(CartService::class)
				->createQueryBuilder('stock')
				->select('count(stock)')
				->getQuery()
				->getSingleScalarResult() ?? 0;
		$this->template->companies = $companies;
	}

	/**
	 * @param string|null $ic
	 */
	public function actionCreate(string $ic = null): void
	{
		if ($ic !== null && $ic !== '') {
			try {
				$this->aresData = $this->companyManager->getDataFromAres($ic);

				if ($this->aresData->active === false) {
					$this->flashMessage('Tato firma je vedena v databázi ARES jako neaktivní.', 'warning');
				}
			} catch (IdentificationNumberNotFoundException $e) {
				$this->aresData = null;

				$this->flashMessage('Pod zadaným IČ nebyla v databázi ARES nalezena žádná firma.', 'warning');
			}
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionEdit(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionEditStock(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->getCompanyStockById($id);
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
			$this->template->stock = $this->editedStock;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná pobočka neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionEditCart(string $id): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedCart->getCompanyStock()->getCompany();

			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný vozík neexistuje.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionMoveCart(string $id): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedStock->getCompany();

			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný vozík neexistuje.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionDetail(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionInvoice(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionOffer(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionOrder(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionDetailStock(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->getCompanyStockById($id);
			$this->template->stock = $this->editedStock;
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná pobočka neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionCartSort(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->getCompanyStockById($id);
			$this->template->stock = $this->editedStock;
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná pobočka neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @param string|null $ret
	 * @throws AbortException
	 */
	public function actionDetailCart(string $id, string $ret = null): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedCart->getCompanyStock()->getCompany();
			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
			$this->template->ret = $ret;

			$this->serviceUsers = $this->userManager->get()->getUsersByRole('servisni-technik');
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný vozík nebyl nalezen.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionDetailCartDocument(string $id): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedCart->getCompanyStock()->getCompany();
			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný vozík nebyl nalezen.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionEditCartDates(string $id): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedCart->getCompanyStock()->getCompany();
			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný vozík nebyl nalezen.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @param string|null $ret
	 * @throws AbortException
	 */
	public function actionCartTimeline(string $id, string $ret = null): void
	{
		try {
			$this->editedCart = $this->cartManager->getCartById($id);
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedCart->getCompanyStock()->getCompany();
			$this->template->cart = $this->editedCart;
			$this->template->stock = $this->editedStock;
			$this->template->company = $this->editedCompany;
			$this->template->timeLine = $this->getCartTimeline($this->editedCart);
			$this->template->ret = $ret;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný vozík nebyl nalezen.', 'error');

			$this->redirect('default');
		}
	}

	/**
	 * @param Cart $cart
	 * @return array
	 */
	private function getCartTimeline(Cart $cart): array
	{
		$items = [];

		foreach ($cart->getCartServices() as $cartService) {
			$items[] = [
				'date' => $cartService->getDate(),
				'entity' => $cartService,
				'type' => 'service',
			];
		}

		$offers = [];
		try {
			/** @var OfferItemTableCart[] $offers */
			$offers = $this->entityManager->getRepository(OfferItemTableCart::class)
				->createQueryBuilder('offer')
				->where('offer.cart = :id')
				->setParameter('id', $cart->getId())
				->getQuery()
				->getResult();
		} catch (NoResultException $e) {
			$offers = [];
		}

		foreach ($offers as $offer) {
			$rows = [];

			foreach ($offer->getRows() as $row) {
				if ($row instanceof OfferItemTableRowPrice) {
					$rows[] = [
						'description' => $row->getDescription(),
						'quantity' => $row->getQuantity() . ' ' . $row->getUnit()->getShortcut(),
					];
				}
			}

			$items[] = [
				'date' => $offer->getOffer()->getCreatedDate(),
				'entity' => $offer,
				'type' => 'offer',
				'rows' => $rows,
			];
		}

		if ($cart->getHandoverDate() !== null) {
			$items[] = [
				'date' => $cart->getHandoverDate(),
				'entity' => null,
				'type' => 'handover',
			];
		}

		if ($cart->getWarrantyDate() !== null) {
			$items[] = [
				'date' => $cart->getWarrantyDate(),
				'entity' => null,
				'type' => 'warranty',
			];
		}

		if ($cart->getManufacturingDate() !== null) {
			$items[] = [
				'date' => $cart->getManufacturingDate(),
				'entity' => null,
				'type' => 'manufacturing',
			];
		}

		usort($items, static function ($a, $b): int {
			if ($a['date'] > $b['date']) {
				return -1;
			}

			if ($a['date'] === $b['date']) {
				return 0;
			}

			return 1;
		});

		return $items;
	}

	/**
	 * @param string $id
	 * @param int $ret
	 * @throws AbortException
	 * @throws EntityManagerException
	 */
	public function actionDetailCartService(string $id, int $ret = 0): void
	{
		try {
			$this->editedCartService = $this->cartServiceManager->getCartServiceById($id);
			$this->editedCart = $this->editedCartService->getCart();
			$this->editedStock = $this->editedCart->getCompanyStock();
			$this->editedCompany = $this->editedStock->getCompany();
			$this->serviceUsers = $this->userManager->get()->getUsersByRole('servisni-technik');

			$editMode = false;
			if ($this->checkUserRight('page__company__cartServiceForceEdit')) {
				$editMode = true;
			} elseif (
				$this->editedCartService->isSubmitted() === false
				&& $this->getUser()->getIdentity()
				&& $this->editedCartService->getServiceUser()->getId() === $this->getUser()->getIdentity()->getId()
			) {
				$editMode = true;
			}
			$this->template->editMode = $editMode;

			$this->template->company = $this->editedCompany;
			$this->template->stock = $this->editedStock;
			$this->template->cart = $this->editedCart;
			$this->template->cartService = $this->editedCartService;
			$this->template->serviceUsers = $this->serviceUsers;
			$this->template->cartServiceUser = $this->serviceUserManager->getServiceUserByUser($this->editedCartService->getServiceUser());

			$this->template->offers = $this->offerManager->getOffersByCartService($this->editedCartService);

			$this->template->returnButton = $ret;
			$this->returnButton = $ret;

			if ($this->editedCartService->getTechnicalCheckProtocol() !== null) {
				$this->template->tk = $this->editedCartService->getTechnicalCheckProtocol();

				if ($this->template->tk->getManufacturingDate() === null && $this->template->cart->getManufacturingDate() !== null) {
					$this->template->tk->setManufacturingDate($this->template->cart->getManufacturingDate());

					$this->entityManager->flush($this->template->tk);
				}

				$su = $this->editedCartService->getTechnicalCheckProtocol()->getServiceUser();
				$this->template->tkServiceUser = $this->serviceUserManager->getServiceUserByUser($su);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná servisní zpráva neexistuje.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionCreateStock(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);
			$this->template->company = $this->editedCompany;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionCreateCart(string $id): void
	{
		try {
			$this->editedStock = $this->companyManager->getCompanyStockById($id);
			$this->editedCompany = $this->editedStock->getCompany();
			$this->template->company = $this->editedCompany;
			$this->template->stock = $this->editedStock;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná pobočka neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $companyId
	 * @param string|null $companyStockId
	 * @throws AbortException
	 */
	public function actionContact(string $companyId, string $companyStockId = null): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($companyId);

			if ($companyStockId !== null) {
				$this->editedStock = $this->companyManager->getCompanyStockById($companyStockId);
			}

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;

			if ($this->editedStock !== null) {
				$this->template->contactList = $this->editedStock->getContacts();
			} else {
				$this->template->contactList = $this->editedCompany->getContacts();
			}
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $companyId
	 * @param string|null $companyStockId
	 * @throws AbortException
	 */
	public function actionCreateContact(string $companyId, string $companyStockId = null): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($companyId);

			if ($companyStockId !== null) {
				$this->editedStock = $this->companyManager->getCompanyStockById($companyStockId);
			}

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionEditContact(string $id): void
	{
		try {
			$this->editedContact = $this->companyManager->getContactById($id);
			$this->editedCompany = $this->editedContact->getCompany();
			$this->editedStock = $this->editedContact->getCompanyStock();

			$this->template->company = $this->editedCompany;
			$this->template->companyStock = $this->editedStock;
			$this->template->contact = $this->editedContact;
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaný kontakt neexistuje', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionInvoicedItems(string $id): void
	{
		try {
			$company = $this->companyManager->getCompanyById($id);

			$this->template->company = $company;
			$this->template->list = $this->companyManager->getInvoicedItems($company);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Firma nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionPriceList(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);

			$this->template->company = $this->editedCompany;
			$this->template->list = $this->companyManager->getPriceListItems($this->editedCompany);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Firma nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionPriceListAdd(string $id): void
	{
		try {
			$this->editedCompany = $this->companyManager->getCompanyById($id);

			$this->template->company = $this->editedCompany;
			$this->template->operations = $this->cartServiceManager->getOperations();
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Firma nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $itemId
	 * @throws AbortException
	 */
	public function actionPriceListEdit(string $itemId): void
	{
		try {
			$this->editedPriceListItem = $this->companyManager->getPriceListItemById($itemId);
			$this->editedCompany = $this->editedPriceListItem->getCompany();

			$this->template->company = $this->editedCompany;
			$this->template->operations = $this->cartServiceManager->getOperations();
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Firma nebyla nalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 * @throws EntityManagerException
	 */
	public function handleBlackList(string $id): void
	{
		try {
			$company = $this->companyManager->getCompanyById($id);
			$company->setBlackList(!$company->isBlackList());

			$this->entityManager->flush($company);

			$this->redirect('detail', ['id' => $company->getId()]);
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');

			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleRemove(string $id): void
	{
		try {
			$company = $this->companyManager->getCompanyById($id);
			$this->companyManager->removeCompany($company);

			$this->flashMessage('Firma byla odebrána ze seznamu.', 'info');
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná firma neexistuje.', 'danger');
		} catch (CompanyException $e) {
			$this->flashMessage($e->getMessage(), 'danger');
		}

		$this->redirect('default');
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleRemoveStock(string $id): void
	{
		try {
			$stock = $this->companyManager->getCompanyStockById($id);
			$company = $stock->getCompany();
			$this->companyManager->removeCompanyStock($stock);

			$this->flashMessage('Pobočka byla odebrána ze seznamu.', 'info');

			$this->redirect('detail', ['id' => $company->getId()]);
		} catch (NonUniqueResultException | NoResultException $e) {
			$this->flashMessage('Požadovaná pobočka neexistuje.', 'danger');
		} catch (CompanyException $e) {
			$this->flashMessage($e->getMessage(), 'danger');
		}

		$this->redirect('default');
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleRemoveCartService(string $id): void
	{
		try {
			$cartService = $this->cartServiceManager->getCartServiceById($id);
			$this->cartServiceManager->removeCartService($cartService);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný servisní výkaz neexistuje.', 'error');
			$this->redirect('default');
		} catch (CartServiceException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}

		$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleRemoveCartServiceTK(string $id): void
	{
		try {
			$cartService = $this->cartServiceManager->getCartServiceById($id);
			$this->cartServiceManager->removeCartServiceTK($cartService);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný servisní výkaz neexistuje.', 'error');
			$this->redirect('default');
		} catch (CartServiceException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleReturnCartService(string $id): void
	{
		try {
			$cartService = $this->cartServiceManager->getCartServiceById($id);
			$cartService->setSubmitted(false);

			$this->entityManager->flush($cartService);

			$this->redirect('detailCartService', ['id' => $cartService->getId()]);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný servisní výkaz neexistuje.', 'error');
			$this->redirect('default');
		} catch (EntityManagerException $e) {
			$this->flashMessage('Při ukládání nastala chyba.', 'error');
		}

		$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
	}

	/**
	 * @return Form
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 * @throws CurrencyException
	 */
	public function createComponentCreateForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název společnosti')
			->setDefaultValue($this->aresData->company ?? '')
			->setRequired('Zadejte název společnosti.');

		$form->addText('ic', 'IČ')
			->setDefaultValue($this->aresData->in ?? '')
			->setRequired('Zadejte IČ společnosti.');

		$form->addText('dic', 'DIČ')
			->setDefaultValue($this->aresData->tin ?? '');

		$street = $this->aresData->street ?? '';
		$houseNumber = $this->aresData->house_number ?? '';

		$form->addText('street', 'Ulice, č.p.')
			->setDefaultValue($street !== '' && $houseNumber !== '' ? $street . ' ' . $houseNumber : '')
			->setRequired('Zadejte ulici a číslo popisné');

		$form->addText('city', 'Město')
			->setDefaultValue($this->aresData->city ?? '')
			->setRequired('Zadejte město');

		$form->addText('zipCode', 'PSČ')
			->setDefaultValue($this->aresData->zip ?? '')
			->setRequired('Zadejte poštovní směrovací číslo');

		$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
			->setDefaultValue($this->countryManager->getCountryByIsoCode('CZE')->getId())
			->setRequired('Vyberte zemi');

		$form->addSelect('currency', 'Měna', $this->currencyManager->getCurrenciesForForm())
			->setDefaultValue($this->currencyManager->getDefaultCurrency()->getId())
			->setRequired('Vyberte měnu');

		$form->addText('invoiceDuaDayCount', 'Výchozí splatnost faktur')
			->setDefaultValue(14)
			->setRequired('Zadejte výchozí splatnost faktur');

		$form->addSelect('type', 'Typ zákazníka', $this->companyManager->getCompanyTypes())
			->setDefaultValue($this->companyManager->getDefaultCompanyType())
			->setRequired('Vyberte typ zákazníka');

		$form->addCheckbox('groupInvoices', 'Seskupovat PDF před odesláním');

		$form->addTextArea('note', 'Poznámka');

		$form->addText('workPrice', 'Cena práce servisního technika')
			->setDefaultValue('0')
			->setRequired('Zadejte cenu práce servisního technika');

		$form->addSelect('workPriceType', 'Typ sazby', [
			CompanyWorkPriceType::HOURS => 'hodinová',
			CompanyWorkPriceType::UNITS => 'jednotková',
		])
			->setDefaultValue(CompanyWorkPriceType::HOURS)
			->setRequired('Vybete typ sazby');

		$form->addSubmit('submit', 'Přidat');

		/**
		 * @param Form $form
		 * @param ArrayHash $value
		 */
		$form->onValidate[] = function (Form $form, ArrayHash $value): void {
			try {
				$this->companyManager->getCompanyByIco($value->ic);

				$form->addError('Tato firma je již v systému zavedena.');
			} catch (NoResultException | NonUniqueResultException $e) {

			}
		};

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$invoiceAddress = new Address($values->street, $values->city);
				$invoiceAddress->setCompanyName($values->name);
				$invoiceAddress->setIc($values->ic);
				$invoiceAddress->setDic($values->dic);
				$invoiceAddress->setZipCode($values->zipCode);
				$invoiceAddress->setCountry($this->countryManager->getCountryById($values->country));

				$this->entityManager->persist($invoiceAddress);

				$currency = $this->currencyManager->getCurrencyById($values->currency);

				$company = new Company($invoiceAddress, $currency);
				$company->setType($values->type);
				$company->setNote($values->note);
				$company->setSendInvoicesInOneFile($values->groupInvoices);
				$company->setInvoiceDueDayCount((int) $values->invoiceDuaDayCount);

				$company->setWorkPrice((float) str_replace(',', '.', $values->workPrice));
				$company->setWorkPriceType($values->workPriceType);

				$this->entityManager->persist($company)->flush([$invoiceAddress, $company]);

				$this->flashMessage('Firma byla úspěšně přidána do seznamu.', 'success');

				$this->redirect('detail', ['id' => $company->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'danger');
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 * @throws CompanyException
	 */
	public function createComponentEditForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'Název společnosti')
			->setDefaultValue($this->editedCompany->getName())
			->setRequired('Zadejte název společnosti.');

		$form->addText('ic', 'IČ')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getIc())
			->setRequired('Zadejte IČ společnosti.');

		$form->addText('dic', 'DIČ')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getDic() ?? '');

		$form->addText('street', 'Ulice, č.p.')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getStreet())
			->setRequired('Zadejte ulici a číslo popisné');

		$form->addText('city', 'Město')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getCity())
			->setRequired('Zadejte město');

		$form->addText('zipCode', 'PSČ')
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getZipCode())
			->setRequired('Zadejte poštovní směrovací číslo');

		$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
			->setDefaultValue($this->editedCompany->getInvoiceAddress()->getCountry()->getId())
			->setRequired('Vyberte zemi');

		$form->addSelect('currency', 'Měna', $this->currencyManager->getCurrenciesForForm())
			->setDefaultValue($this->editedCompany->getCurrency()->getId())
			->setRequired('Vyberte měnu');

		$form->addText('invoiceDuaDayCount', 'Výchozí splatnost faktur')
			->setDefaultValue($this->editedCompany->getInvoiceDueDayCount())
			->setRequired('Zadejte výchozí splatnost faktur');

		$form->addSelect('type', 'Typ zákazníka', $this->companyManager->getCompanyTypes())
			->setDefaultValue($this->editedCompany->getType())
			->setRequired('Vyberte typ zákazníka');

		$form->addCheckbox('groupInvoices', 'Seskupovat PDF před odesláním')
			->setDefaultValue($this->editedCompany->isSendInvoicesInOneFile());

		$form->addTextArea('note', 'Poznámka')
			->setDefaultValue($this->editedCompany->getNote());

		$form->addText('workPrice', 'Cena práce servisního technika')
			->setDefaultValue($this->editedCompany->getWorkPrice())
			->setRequired('Zadejte cenu práce servisního technika');

		$form->addSelect('workPriceType', 'Typ sazby', [
			CompanyWorkPriceType::HOURS => 'hodinová',
			CompanyWorkPriceType::UNITS => 'jednotková',
		])
			->setDefaultValue($this->editedCompany->getWorkPriceType())
			->setRequired('Vybete typ sazby');

		$form->addSubmit('submit', 'Uložit');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$invoiceAddress = $this->editedCompany->getInvoiceAddress();
				$invoiceAddress->setStreet($values->street);
				$invoiceAddress->setCity($values->city);
				$invoiceAddress->setCompanyName($values->name);
				$invoiceAddress->setIc($values->ic);
				$invoiceAddress->setDic($values->dic);
				$invoiceAddress->setZipCode($values->zipCode);
				$invoiceAddress->setCountry($this->countryManager->getCountryById($values->country));

				$currency = $this->currencyManager->getCurrencyById($values->currency);

				$this->editedCompany->setName($values->name);
				$this->editedCompany->setCurrency($currency);
				$this->editedCompany->setType($values->type);
				$this->editedCompany->setNote($values->note);
				$this->editedCompany->setSendInvoicesInOneFile($values->groupInvoices);
				$this->editedCompany->setInvoiceDueDayCount((int) $values->invoiceDuaDayCount);

				$this->editedCompany->setWorkPrice((float) str_replace(',', '.', $values->workPrice));
				$this->editedCompany->setWorkPriceType($values->workPriceType);

				$this->entityManager->flush([$invoiceAddress, $this->editedCompany]);

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');

				$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'danger');
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 * @throws CompanyException
	 * @throws NoResultException
	 * @throws NonUniqueResultException
	 */
	public function createComponentCreateStockForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'Název pobočky')
			->setRequired('Zadejte název pobočky.');

		$form->addText('street', 'Ulice, č.p.')
			->setRequired('Zadejte ulici a číslo popisné');

		$form->addText('city', 'Město')
			->setRequired('Zadejte město');

		$form->addText('zipCode', 'PSČ')
			->setRequired('Zadejte poštovní směrovací číslo');

		$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
			->setDefaultValue($this->countryManager->getCountryByIsoCode('CZE')->getId())
			->setRequired('Vyberte zemi');

		$form->addSelect('travelRateType', 'Typ účtování výjezdu', TravelRateType::getTypes())
			->setDefaultValue(TravelRateType::getDefaultType())
			->setRequired('Vyberte typ sazby pro účtování výjezdu');

		$form->addText('travelPrice', 'Cena za dopravu')
			->setRequired('Zadejte ceny dopravy');

		$form->addText('travelDistance', 'Vzdálenost km');

		$form->addTextArea('note', 'Poznámka');

		$form->addSubmit('submit', 'Přidat');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$address = new Address($values->street, $values->city);
				$address->setCompanyName($this->editedCompany->getInvoiceAddress()->getCompanyName());
				$address->setIc($this->editedCompany->getInvoiceAddress()->getId());
				$address->setDic($this->editedCompany->getInvoiceAddress()->getDic());
				$address->setZipCode($values->zipCode);
				$address->setCountry($this->countryManager->getCountryById($values->country));

				$this->entityManager->persist($address);

				$stock = new CompanyStock(
					$this->editedCompany,
					$values->name,
					$address,
					$values->travelRateType,
					(float) str_replace(',', '.', $values->travelPrice)
				);

				$stock->setTravelDistance(($values->travelDistance === '' || $values->travelDistance === null) ? null : (int) $values->travelDistance);
				$stock->setNote($values->note === '' ? null : $values->note);

				$this->entityManager->persist($stock);

				$this->editedCompany->addStock($stock);

				$this->entityManager->flush([$address, $stock, $this->editedCompany]);

				$this->flashMessage('Pobočka byla úspěšně přidána.', 'success');

				$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'danger');
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 * @throws CompanyException
	 */
	public function createComponentEditStockForm(): Form
	{
		if ($this->editedCompany === null) {
			throw new CompanyException('Edited Company is null');
		}

		if ($this->editedStock === null) {
			throw new CompanyException('Edited stock is null');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'Název pobočky')
			->setDefaultValue($this->editedStock->getName())
			->setRequired('Zadejte název pobočky.');

		$form->addText('street', 'Ulice, č.p.')
			->setDefaultValue($this->editedStock->getAddress()->getStreet())
			->setRequired('Zadejte ulici a číslo popisné');

		$form->addText('city', 'Město')
			->setDefaultValue($this->editedStock->getAddress()->getCity())
			->setRequired('Zadejte město');

		$form->addText('zipCode', 'PSČ')
			->setDefaultValue($this->editedStock->getAddress()->getZipCode())
			->setRequired('Zadejte poštovní směrovací číslo');

		$form->addSelect('country', 'Země', $this->countryManager->getCountriesForForm())
			->setDefaultValue($this->editedStock->getAddress()->getCountry()->getId())
			->setRequired('Vyberte zemi');

		$form->addSelect('travelRateType', 'Typ účtování výjezdu', TravelRateType::getTypes())
			->setDefaultValue($this->editedStock->getTravelRateType())
			->setRequired('Vyberte typ sazby pro účtování výjezdu');

		$form->addText('travelPrice', 'Cena za dopravu')
			->setDefaultValue($this->editedStock->getTravelPrice())
			->setRequired('Zadejte ceny dopravy');

		$form->addText('travelDistance', 'Vzdálenost km')
			->setDefaultValue((string) ($this->editedStock->getTravelDistance() ?? ''));

		$form->addTextArea('note', 'Poznámka')
			->setDefaultValue($this->editedStock->getNote() ?? '');

		$form->addSubmit('submit', 'Uložit');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$address = $this->editedStock->getAddress();
				$address->setStreet($values->street);
				$address->setCity($values->city);
				$address->setCompanyName($this->editedCompany->getInvoiceAddress()->getCompanyName());
				$address->setIc($this->editedCompany->getInvoiceAddress()->getId());
				$address->setDic($this->editedCompany->getInvoiceAddress()->getDic());
				$address->setZipCode($values->zipCode);
				$address->setCountry($this->countryManager->getCountryById($values->country));

				$this->editedStock->setName($values->name);
				$this->editedStock->setTravelRateType($values->travelRateType);
				$this->editedStock->setTravelPrice((float) str_replace(',', '.', $values->travelPrice));
				$this->editedStock->setTravelDistance(($values->travelDistance === '' || $values->travelDistance === null) ? null : (int) $values->travelDistance);

				$this->editedStock->setNote($values->note === '' ? null : $values->note);

				$this->entityManager->flush([$address, $this->editedStock, $this->editedCompany]);

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');

				$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);

				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'danger');
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCreateContactForm(): Form
	{
		$form = $this->formFactory->create();

		$stockList = [];
		foreach ($this->editedCompany->getStocks() as $stock) {
			$stockList[$stock->getId()] = $stock->getName();
		}

		$companyStock = $this->editedStock !== null ? $this->editedStock->getId() : null;

		$form->addSelect('companyStock', 'Pobočka', $stockList)
			->setPrompt('Všechny pobočky')
			->setDefaultValue($companyStock);

		$form->addText('firstName', 'Jméno');

		$form->addText('lastName', 'Příjmení')
			->setRequired('Zadejte příjmení');

		$form->addText('email', 'E-mail');

		$form->addText('role', 'Pozice');

		$form->addText('phone', 'Telefon');

		$form->addText('mobile', 'Mobil');

		$form->addText('note', 'Poznámka');

		$form->addCheckbox('sendInvoice', 'Zasílat faktury');
		$form->addCheckbox('sendOffer', 'Zasílat nabídky');
		$form->addCheckbox('sendOrder', 'Zasílat objednávky');
		$form->addCheckbox('sendCartService', 'Zasílat servisní zprávy');
		$form->addCheckbox('sendMarketing', 'Zasílat marketing');
		$form->addCheckbox('showForServiceUser', 'Zobrazovat servisním technikům');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
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
			$contact->setSendCartService($values->sendCartService);
			$contact->setSendMarketing($values->sendMarketing);
			$contact->setShowForServiceUser($values->showForServiceUser);

			$this->entityManager->persist($contact)->flush($contact);

			$this->flashMessage('Kontakt byl úspěšně vytvořen.', 'success');

			if ($this->editedStock !== null) {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId(), 'companyStockId' => $this->editedStock->getId()]);
			} else {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId()]);
			}
		};

		return $form;
	}


	/**
	 * @return Form
	 */
	public function createComponentEditContactForm(): Form
	{
		$form = $this->formFactory->create();

		$stockList = [];
		foreach ($this->editedCompany->getStocks() as $stock) {
			$stockList[$stock->getId()] = $stock->getName();
		}

		$companyStock = $this->editedStock !== null ? $this->editedStock->getId() : null;

		$form->addSelect('companyStock', 'Pobočka', $stockList)
			->setPrompt('Všechny pobočky')
			->setDefaultValue($companyStock);

		$form->addText('firstName', 'Jméno')
			->setDefaultValue($this->editedContact->getFirstName());

		$form->addText('lastName', 'Příjmení')
			->setRequired('Zadejte příjmení')
			->setDefaultValue($this->editedContact->getLastName());

		$form->addText('email', 'E-mail')
			->setDefaultValue($this->editedContact->getEmail());

		$form->addText('role', 'Pozice')
			->setDefaultValue($this->editedContact->getRole() ?? '');

		$form->addText('phone', 'Telefon')
			->setDefaultValue($this->editedContact->getPhone() ?? '');

		$form->addText('mobile', 'Mobil')
			->setDefaultValue($this->editedContact->getMobilePhone() ?? '');

		$form->addText('note', 'Poznámka')
			->setDefaultValue($this->editedContact->getNote() ?? '');

		$form->addCheckbox('sendInvoice', 'Zasílat faktury')
			->setDefaultValue($this->editedContact->isSendInvoice());

		$form->addCheckbox('sendOffer', 'Zasílat nabídky')
			->setDefaultValue($this->editedContact->isSendOffer());

		$form->addCheckbox('sendOrder', 'Zasílat objednávky')
			->setDefaultValue($this->editedContact->isSendOrder());

		$form->addCheckbox('sendCartService', 'Zasílat servisní zprávy')
			->setDefaultValue($this->editedContact->isSendCartService());

		$form->addCheckbox('sendMarketing', 'Zasílat marketing')
			->setDefaultValue($this->editedContact->isSendMarketing());

		$form->addCheckbox('showForServiceUser', 'Zobrazovat servisním technikům')
			->setDefaultValue($this->editedContact->isShowForServiceUser());

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
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
			$this->editedContact->setSendCartService($values->sendCartService);
			$this->editedContact->setSendMarketing($values->sendMarketing);
			$this->editedContact->setShowForServiceUser($values->showForServiceUser);

			$this->entityManager->persist($this->editedContact)->flush($this->editedContact);

			$this->flashMessage('Změny byly úspěšně uloženy.', 'success');

			if ($this->editedStock !== null) {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId(), 'companyStockId' => $this->editedStock->getId()]);
			} else {
				$this->redirect('contact', ['companyId' => $this->editedCompany->getId()]);
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCreateCartForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Název vozíku');

		$form->addSelect('brand', 'Značka', $this->brandManager->getBrandsForForm())
			->setPrompt('Vyberte značku vozíku')
			->setRequired('Vyberte značku vozíku');

		$form->addSelect('cartType', 'Typ vozíku')
			->setPrompt('Nejdříve vyberte značku vozíku')
			->setRequired('Vyberte typ vozíku');

		$form->addText('serialNumber', 'Sériové číslo');

		$form->addText('manufacturingDate', 'Datum výroby');

		$form->addSelect('motor', 'Motor', $this->cartManager->getCartMotorsForForm())
			->setPrompt('Vyberte typ motoru')
			->setRequired('Vyberte typ motoru');

		$form->addText('motoHours', 'Motohodiny');
		$form->addText('motoHoursAlert', 'Motohodiny - alert');

		$form->addSelect('status', 'Stav vozíku', $this->cartManager->getCartStatusList())
			->setDefaultValue($this->cartManager->getDefaultCartStatus())
			->setRequired('Vyberte stav vozíku');

		$form->addDate('dateHandover', 'Datum předání');

		$form->addDate('warrantyDate', 'V záruce do (datum)');
		$form->addInteger('warrantyHour', 'V záruce do (mth)');

		$form->addSelect('serviceType', 'Pravidelný servis', CartServiceType::getList())
			->setDefaultValue(CartServiceType::NONE)
			->setRequired('Vyberte typ servisu');

		$form->addDate('dateTK', 'Datum poslední TK');

		$form->addDate('dateMotorRevision', 'Datum revize LPG');

		$form->addSelect('lastService', 'Typ posledniho servisu', CartServiceType::getTypes())
			->setPrompt('Žádný');

		$form->addDate('lastServiceDate', 'Datum posledniho servisu');

		$form->addCheckbox('doNotWatchTK', 'Nehlídat TK');

		$form->addCheckbox('refurbished', 'Repasovaný vozík');

		$form->addTextArea('note', 'Poznámka');

		$form->addSubmit('submit', 'Create');


		$form->onAnchor[] = function (Form $form): void {
			if ($form['brand']->getValue()) {
				try {
					$brand = $this->brandManager->getById($form['brand']->getValue());

					$cartTypes = [];
					foreach ($brand->getCartTypes() as $cartType) {
						$cartTypes[$cartType->getId()] = $cartType->getName();
					}

					$form['cartType']->setPrompt('Vyberte typ vozíku')
						->setItems($cartTypes);
				} catch (NoResultException | NonUniqueResultException $e) {
					$form['cartType']->setPrompt('Nejdříve vyberte značku vozíku')
						->setItems([]);
					$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
				}
			}
		};

		$form->onError[] = function (Form $form): void {
			foreach ($form->errors as $error) {
				$this->flashMessage($error, 'error');
			}
		};

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$brand = $this->brandManager->getById($values->brand);
				$cartType = $this->cartManager->getCartTypeById($values->cartType);
				$motor = $this->cartManager->getCartMotorById($values->motor);

				$cart = $this->cartManager->createCart($this->editedStock, $brand, $cartType);

				$cart->setName($values->name === '' ? null : $values->name);
				$cart->setStatus($values->status);
				$cart->setMotor($motor);
				$cart->setMotoHours($values->motoHours === '' ? null : (int) $values->motoHours);
				$cart->setMotoHoursAlert($values->motoHoursAlert === '' ? null : (int) $values->motoHoursAlert);
				$cart->setSerialNumber($values->serialNumber === '' ? null : $values->serialNumber);
				$cart->setManufacturingDate($values->manufacturingDate === '' ? null : DateTime::from($values->manufacturingDate . '-01-01'));
				$cart->setHandoverDate($values->dateHandover === '' ? null : DateTime::from($values->dateHandover));
				$cart->setServiceType($values->status === Cart::STATUS_OUT_OF_ORDER ? CartServiceType::NONE : $values->serviceType);
				$cart->setLastServiceType($values->lastService);
				$cart->setLastServiceDate($values->lastServiceDate === '' ? null : DateTime::from($values->lastServiceDate));
				$cart->setLastServiceDateTK($values->dateTK === '' ? null : DateTime::from($values->dateTK));
				$cart->setMotorRevisionDate($values->dateMotorRevision === '' ? null : DateTime::from($values->dateMotorRevision));
				$cart->setDoNotWatchTK($values->doNotWatchTK);
				$cart->setRefurbished($values->refurbished);
				$cart->setWarrantyDate($values->warrantyDate === '' ? null : DateTime::from($values->warrantyDate));
				$cart->setWarrantyHours((int) $values->warrantyHour === 0 ? null : (int) $values->warrantyHour);
				$cart->setNote($values->note === '' ? null : $values->note);

				$this->entityManager->flush($cart);

				$this->cartServiceManager->calculateNextServicesDates($cart);

				$this->flashMessage('Vozík byl úspěšně přidán do seznamu.', 'success');

				$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
			} catch (EntityManagerException $e) {
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}
		};

		return $form;
	}

	/**
	 * @return Form
	 * @throws CartException
	 */
	public function createComponentEditCartForm(): Form
	{
		if ($this->editedCart === null) {
			throw new CartException('Edited Cart is null!');
		}

		$form = $this->formFactory->create();

		$form->addText('name', 'Název vozíku')
			->setDefaultValue($this->editedCart->getName() ?? '');

		$form->addSelect('brand', 'Značka', $this->brandManager->getBrandsForForm())
			->setPrompt('Vyberte značku vozíku')
			->setDefaultValue($this->editedCart->getBrand()->getId())
			->setRequired('Vyberte značku vozíku');

		$form->addSelect('cartType', 'Typ vozíku');

		$form->addText('serialNumber', 'Sériové číslo')
			->setDefaultValue($this->editedCart->getSerialNumber() ?? '');

		$form->addText('manufacturingDate', 'Datum výroby')
			->setDefaultValue(
				$this->editedCart->getManufacturingDate() === null
					? null
					: $this->editedCart->getManufacturingDate()->format('Y')
			);

		$form->addSelect('motor', 'Motor', $this->cartManager->getCartMotorsForForm())
			->setPrompt('Vyberte typ motoru')
			->setDefaultValue($this->editedCart->getMotor()->getId())
			->setRequired('Vyberte typ motoru');

		$form->addText('motoHours', 'Motohodiny')
			->setDefaultValue($this->editedCart->getMotoHours() ?? '');

		$form->addText('motoHoursAlert', 'Motohodiny - alert')
			->setDefaultValue($this->editedCart->getMotoHoursAlert() ?? '');

		$form->addSelect('status', 'Stav vozíku', $this->cartManager->getCartStatusList())
			->setDefaultValue($this->editedCart->getStatus())
			->setRequired('Vyberte stav vozíku');

		$form->addDate('dateHandover', 'Datum předání')
			->setDefaultValue(
				$this->editedCart->getHandoverDate() === null
					? null
					: $this->editedCart->getHandoverDate()->format('d.m.Y')
			);

		$form->addDate('warrantyDate', 'V záruce do (datum)')
			->setDefaultValue(
				$this->editedCart->getWarrantyDate() === null
					? null
					: $this->editedCart->getWarrantyDate()->format('d.m.Y')
			);

		$form->addInteger('warrantyHour', 'V záruce do (mth)')
			->setDefaultValue($this->editedCart->getWarrantyHours());

		$form->addSelect('serviceType', 'Pravidelný servis', CartServiceType::getList())
			->setDefaultValue($this->editedCart->getServiceType())
			->setRequired('Vyberte typ servisu');

		$form->addDate('dateTK', 'Datum poslední TK')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateTK() === null
					? null
					: $this->editedCart->getLastServiceDateTK()->format('d.m.Y')
			);

		$form->addDate('dateMotorRevision', 'Datum revize LPG')
			->setDefaultValue(
				$this->editedCart->getMotorRevisionDate() === null
					? null
					: $this->editedCart->getMotorRevisionDate()->format('d.m.Y')
			);

		$form->addSelect('lastService', 'Typ posledniho servisu', CartServiceType::getTypes())
			->setPrompt('Žádný')
			->setDefaultValue(
				$this->editedCart->getLastServiceType()
			);

		$form->addDate('lastServiceDate', 'Datum posledniho servisu')
			->setDefaultValue(
				$this->editedCart->getLastServiceDate() === null
					? null
					: $this->editedCart->getLastServiceDate()->format('d.m.Y')
			);

		$form->addCheckbox('doNotWatchTK', 'Nehlídat TK')
			->setDefaultValue(
				$this->editedCart->isDoNotWatchTK()
			);

		$form->addCheckbox('refurbished', 'Repasovaný vozík')
			->setDefaultValue(
				$this->editedCart->isRefurbished()
			);

		$form->addTextArea('note', 'Poznámka')
			->setDefaultValue($this->editedCart->getNote() ?? '');

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 */
		$form->onAnchor[] = function (Form $form): void {
			if ($form['brand']->getValue()) {
				try {
					$brand = $this->brandManager->getById($form['brand']->getValue());

					$cartTypes = [];
					foreach ($brand->getCartTypes() as $cartType) {
						$cartTypes[$cartType->getId()] = $cartType->getName();
					}

					$form['cartType']
						->setPrompt('Vyberte typ vozíku')
						->setItems($cartTypes);

					if ($this->editedCart !== null && $this->editedCart->getBrand()->getId() === $brand->getId()) {
						$form['cartType']->setDefaultValue($this->editedCart->getCartType()->getId());
					}
				} catch (NoResultException | NonUniqueResultException $e) {
					$form['cartType']
						->setPrompt('Nejdříve vyberte značku vozíku')
						->setItems([]);
					$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
				}
			}
		};

		/**
		 * @param Form $form
		 */
		$form->onError[] = function (Form $form): void {
			foreach ($form->errors as $error) {
				$this->flashMessage($error, 'error');
			}
		};

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$brand = $this->brandManager->getById($values->brand);
				$cartType = $this->cartManager->getCartTypeById($values->cartType);
				$motor = $this->cartManager->getCartMotorById($values->motor);

				$cart = $this->editedCart;

				$cart->setBrand($brand);
				$cart->setType($cartType);

				$cart->setName($values->name === '' ? null : $values->name);
				$cart->setStatus($values->status);
				$cart->setMotor($motor);
				$cart->setMotoHours($values->motoHours === '' ? null : (int) $values->motoHours);
				$cart->setMotoHoursAlert($values->motoHoursAlert === '' ? null : (int) $values->motoHoursAlert);
				$cart->setSerialNumber($values->serialNumber === '' ? null : $values->serialNumber);
				$cart->setManufacturingDate($values->manufacturingDate === '' ? null : DateTime::from($values->manufacturingDate . '-01-01'));
				$cart->setHandoverDate($values->dateHandover === '' ? null : DateTime::from($values->dateHandover));
				$cart->setServiceType($values->status === Cart::STATUS_OUT_OF_ORDER ? CartServiceType::NONE : $values->serviceType);
				$cart->setLastServiceType($values->lastService);
				$cart->setLastServiceDate($values->lastServiceDate === '' ? null : DateTime::from($values->lastServiceDate));
				$cart->setLastServiceDateTK($values->dateTK === '' ? null : DateTime::from($values->dateTK));
				$cart->setDoNotWatchTK($values->doNotWatchTK);
				$cart->setRefurbished($values->refurbished);
				$cart->setMotorRevisionDate($values->dateMotorRevision === '' ? null : DateTime::from($values->dateMotorRevision));
				$cart->setWarrantyDate($values->warrantyDate === '' ? null : DateTime::from($values->warrantyDate));
				$cart->setWarrantyHours((int) $values->warrantyHour === 0 ? null : (int) $values->warrantyHour);
				$cart->setNote($values->note === '' ? null : $values->note);

				$this->entityManager->flush($cart);

				$this->cartServiceManager->calculateNextServicesDates($cart);

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');

				$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
			} catch (EntityManagerException $e) {
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}
		};

		return $form;
	}

	public function createComponentEditCartDatesForm(): Form
	{
		if ($this->editedCart === null) {
			throw new CartException('Edited Cart is null!');
		}

		$form = $this->formFactory->create();

		$form->addDate('dateLastTK', 'Datum poslední TK')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateTK() === null
					? null
					: $this->editedCart->getLastServiceDateTK()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextTK', 'Datum přístí TK')
			->setDefaultValue(
				$this->editedCart->getNextServiceDateTK() === null
					? null
					: $this->editedCart->getNextServiceDateTK()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('dateLastLPG', 'Datum poslední LPG')
			->setDefaultValue(
				$this->editedCart->getMotorRevisionDate() === null
					? null
					: $this->editedCart->getMotorRevisionDate()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextLPG', 'Datum přístí LPG')
			->setDefaultValue(
				$this->editedCart->getNextMotorRevisionDate() === null
					? null
					: $this->editedCart->getNextMotorRevisionDate()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('dateLastM12', 'Datum poslední M12')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateM12() === null
					? null
					: $this->editedCart->getLastServiceDateM12()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextM12', 'Datum přístí M12')
			->setDefaultValue(
				$this->editedCart->getNextServiceDateM12() === null
					? null
					: $this->editedCart->getNextServiceDateM12()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('dateLastM6', 'Datum poslední M6')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateM6() === null
					? null
					: $this->editedCart->getLastServiceDateM6()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextM6', 'Datum přístí M6')
			->setDefaultValue(
				$this->editedCart->getNextServiceDateM6() === null
					? null
					: $this->editedCart->getNextServiceDateM6()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('dateLastM3', 'Datum poslední M3')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateM3() === null
					? null
					: $this->editedCart->getLastServiceDateM3()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextM3', 'Datum přístí M3')
			->setDefaultValue(
				$this->editedCart->getNextServiceDateM3() === null
					? null
					: $this->editedCart->getNextServiceDateM3()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('dateLastM2', 'Datum poslední M2')
			->setDefaultValue(
				$this->editedCart->getLastServiceDateM2() === null
					? null
					: $this->editedCart->getLastServiceDateM2()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neprovedeno');

		$form->addDate('dateNextM2', 'Datum přístí M2')
			->setDefaultValue(
				$this->editedCart->getNextServiceDateM2() === null
					? null
					: $this->editedCart->getNextServiceDateM2()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Nenaplánováno');

		$form->addDate('startDate', 'Počáteční datum pro plánování servisu (stejné jako poslední TK)')
			->setDefaultValue(
				$this->editedCart->getStartServiceDate() === null
					? null
					: $this->editedCart->getStartServiceDate()->format('d.m.Y')
			)
			->setHtmlAttribute('placeholder', 'Neurčeno');

		$form->addSubmit('submit', 'Uložit');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values) {
			$this->editedCart->setLastServiceDateTK($values->dateLastTK === '' ? null : DateTime::from($values->dateLastTK));
			$this->editedCart->setNextServiceDateTK($values->dateNextTK === '' ? null : DateTime::from($values->dateNextTK));
			$this->editedCart->setMotorRevisionDate($values->dateLastLPG === '' ? null : DateTime::from($values->dateLastLPG));
			$this->editedCart->setNextMotorRevisionDate($values->dateNextLPG === '' ? null : DateTime::from($values->dateNextLPG));
			$this->editedCart->setLastServiceDateM12($values->dateLastM12 === '' ? null : DateTime::from($values->dateLastM12));
			$this->editedCart->setNextServiceDateM12($values->dateNextM12 === '' ? null : DateTime::from($values->dateNextM12));
			$this->editedCart->setLastServiceDateM6($values->dateLastM6 === '' ? null : DateTime::from($values->dateLastM6));
			$this->editedCart->setNextServiceDateM6($values->dateNextM6 === '' ? null : DateTime::from($values->dateNextM6));
			$this->editedCart->setLastServiceDateM3($values->dateLastM3 === '' ? null : DateTime::from($values->dateLastM3));
			$this->editedCart->setNextServiceDateM3($values->dateNextM3 === '' ? null : DateTime::from($values->dateNextM3));
			$this->editedCart->setLastServiceDateM2($values->dateLastM2 === '' ? null : DateTime::from($values->dateLastM2));
			$this->editedCart->setNextServiceDateM2($values->dateNextM2 === '' ? null : DateTime::from($values->dateNextM2));
			$this->editedCart->setStartServiceDate($values->startDate === '' ? null : DateTime::from($values->startDate));

			try {
				$this->entityManager->flush($this->editedCart);
				$this->flashMessage('Termíny servisů byly úspěšně aktualizovány.', 'success');
				$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
			} catch (EntityManagerException $e) {
				$this->flashMessage($e->getMessage(), 'error');
			}
		};

		return $form;
	}

	/**
	 * @param $value
	 */
	public function handleBrandSelectChange($value): void
	{
		if ($value !== '' && $value !== null) {
			try {
				$brand = $this->brandManager->getById($value);

				$cartTypes = [];
				foreach ($brand->getCartTypes() as $cartType) {
					$cartTypes[$cartType->getId()] = $cartType->getName();
				}

				$this['createCartForm']['cartType']->setPrompt('Vyberte typ vozíku')
					->setItems($cartTypes);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this['createCartForm']['cartType']->setPrompt('Nejdříve vyberte značku vozíku')
					->setItems([]);
				$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
			}
		} else {
			$this['createCartForm']['cartType']->setPrompt('Nejdříve vyberte značku vozíku')
				->setItems([]);
		}

		$this->redrawControl('createCartFormSnippetArea');
		$this->redrawControl('cartTypeSelectSnippet');
		$this->redrawControl('flashes');
	}

	/**
	 * @param $value
	 */
	public function handleEditBrandSelectChange($value): void
	{
		if ($value !== '' && $value !== null) {
			try {
				$brand = $this->brandManager->getById($value);

				$cartTypes = [];
				foreach ($brand->getCartTypes() as $cartType) {
					$cartTypes[$cartType->getId()] = $cartType->getName();
				}

				$this['editCartForm']['cartType']->setPrompt('Vyberte typ vozíku')
					->setItems($cartTypes);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this['editCartForm']['cartType']->setPrompt('Nejdříve vyberte značku vozíku')
					->setItems([]);
				$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
			}
		} else {
			$this['editCartForm']['cartType']->setPrompt('Nejdříve vyberte značku vozíku')
				->setItems([]);
		}

		$this->redrawControl('editCartFormSnippetArea');
		$this->redrawControl('cartTypeSelectSnippet');
		$this->redrawControl('flashes');
	}

	/**
	 * @param $value
	 */
	public function handlePriceListBrandSelectChange($value): void
	{
		if ($value !== '' && $value !== null) {
			try {
				$brand = $this->brandManager->getById($value);

				$cartTypes = [];
				foreach ($brand->getCartTypes() as $cartType) {
					$cartTypes[$cartType->getId()] = $cartType->getName();
				}

				$this['priceItemAddForm']['cartType']->setPrompt('Neomezovat')
					->setItems($cartTypes);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this['priceItemAddForm']['cartType']->setPrompt('Neomezovat')
					->setItems([]);
				$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
			}
		} else {
			$this['priceItemAddForm']['cartType']->setPrompt('Neomezovat')
				->setItems([]);
		}

		$this->redrawControl('addPriceListSnippetArea');
		$this->redrawControl('cartTypeSelectSnippet');
		$this->redrawControl('flashes');
	}


	/**
	 * @param $value
	 */
	public function handlePriceListEditBrandSelectChange($value): void
	{
		if ($value !== '' && $value !== null) {
			try {
				$brand = $this->brandManager->getById($value);

				$cartTypes = [];
				foreach ($brand->getCartTypes() as $cartType) {
					$cartTypes[$cartType->getId()] = $cartType->getName();
				}

				$this['priceItemEditForm']['cartType']->setPrompt('Neomezovat')
					->setItems($cartTypes);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this['priceItemEditForm']['cartType']->setPrompt('Neomezovat')
					->setItems([]);
				$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
			}
		} else {
			$this['priceItemEditForm']['cartType']->setPrompt('Neomezovat')
				->setItems([]);
		}

		$this->redrawControl('editPriceListSnippetArea');
		$this->redrawControl('cartTypeSelectSnippet');
		$this->redrawControl('flashes');
	}

	/**
	 * @param $value
	 */
	public function handleMoveCartSelectChange($value): void
	{
		if ($value !== '' && $value !== null) {
			try {
				$company = $this->companyManager->getCompanyById($value);

				$stocks = [];
				foreach ($company->getStocks() as $stock) {
					$stocks[$stock->getId()] = $stock->getName();
				}

				$this['moveCartForm']['companyStock']->setPrompt('Vyberte pobočku')
					->setItems($stocks);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this['moveCartForm']['companyStock']->setPrompt('Nejdříve vyberte firmu')
					->setItems([]);
				$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
			}
		} else {
			$this['moveCartForm']['companyStock']->setPrompt('Nejdříve vyberte firmu')
				->setItems([]);
		}

		$this->redrawControl('moveCartFormSnippetArea');
		$this->redrawControl('companyStockSelectSnippet');
		$this->redrawControl('flashes');
	}

	/**
	 * @return Form
	 */
	public function createComponentCreateCartServiceForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addDate('date', 'Datum servisu')
			->setRequired('Zadejte datum servisu');

		$users = [];

		/** @var User $loggedUser */
		$loggedUser = $this->getUser()->getIdentity();

		try {
			$serviceUser = $this->serviceUserManager->getServiceUserByUser($loggedUser);
			$users[$loggedUser->getId()] = $loggedUser->getName();
		} catch (NoResultException | NonUniqueResultException $e) {
			$users = $this->userManager->get()->getUsersByRole('servisni-technik');

			foreach ($users as $user) {
				$users[$user->getId()] = $user->getName();
			}
		}

		$form->addSelect('serviceUser', 'Servisní technik', $users)
			->setRequired('Vyberte jednoho ze servisních techniků');

		$form->addSubmit('submit', 'Přidat');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$serviceUser = $this->userManager->get()->getUserById($values->serviceUser);
				/** @var User $user */
				$user = $this->user->getIdentity();

				$cartService = $this->cartServiceManager->createCartService($this->editedCart, $serviceUser, $user, $values->date);

				$this->redirect('detailCartService', ['id' => $cartService->getId()]);
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}

		};

		return $form;
	}

	/**
	 * @param string|null $type
	 * @param string|null $value
	 * @throws EntityManagerException
	 */
	public function handleCartServiceChangeValue(string $type = null, string $value = null, bool $checked = null): void
	{
		if ($type === 'signName') {
			$this->editedCartService->setSignName($value);
			$this->entityManager->flush($this->editedCartService);

			$this->redrawControl('cartServiceCardSign');

			return;
		}

		if ($type === 'signDate') {
			$this->editedCartService->setSignDate(
				$value !== ''
					? DateTime::from($value)
					: $this->editedCartService->getDate()
			);
			$this->entityManager->flush($this->editedCartService);

			$this->redrawControl('cartServiceCardSign');
			$this->redrawControl('cartServiceCardSign2');

			return;
		}

		if ($type === 'signPlace') {
			if (trim($value) === '') {
				$value = $this->editedCartService->getCart()->getCompanyStock()->getAddress()->getCity();
			}

			$this->editedCartService->setSignPlace(trim($value));
			$this->entityManager->flush($this->editedCartService);

			$this->redrawControl('cartServiceSignPlace');

			return;
		}

		if ($type === 'carSPZ') {
			if ($value !== null && trim($value) === '') {
				$value = null;
			}

			$this->editedCartService->setCarSPZ(trim($value));
			$this->entityManager->flush($this->editedCartService);

			return;
		}

		if ($type === 'note') {
			if ($value !== null && trim($value) === '') {
				$value = null;
			} else {
				$value = trim($value);
			}

			$this->editedCartService->setNote($value);
			$this->entityManager->flush($this->editedCartService);

			return;
		}

		if ($type === 'motoHours') {
			$success = true;

			if ($value !== null) {
				$motoHours = (int) $value;
			} else {
				$motoHours = null;
			}

			if ($motoHours === null) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Počet motohodin musí být celé číslo!',
				];

				return;
			}

			if ($this->checkUserRight('page__company__cartServiceRemove')) {
				$this->editedCartService->getCart()->setMotoHours($motoHours);
			} elseif ($motoHours < $this->editedCart->getMotoHours()) {
				$motoHours = $this->editedCart->getMotoHours();
				$success = false;
			}

			$this->editedCartService->setMotoHours($motoHours);
			$this->entityManager->flush();

			$this->redrawControl('cartData');

			if ($success === false) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Počet motohodin nemůže být menší než při předchozím servisu.',
				];
			}

			return;
		}

		if ($type === 'manufactureDate') {
			$cart = $this->editedCartService->getCart();

			$date = DateTime::from($value . '-01-01 00:00:00');
			$cart->setManufacturingDate($date);

			try {
				$this->entityManager->flush($cart);

				$tk = $this->editedCartService->getTechnicalCheckProtocol();
				if ($tk !== null) {
					$tk->setManufacturingDate($cart->getManufacturingDate());

					$this->entityManager->flush($tk);

					$this->redrawControl('tkManufacturingDate');
				}

				$this->payload->toastr = [
					'type' => 'success',
					'msg' => 'Změny byly uloženy.',
				];
			} catch (EntityManagerException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			}

			$this->redrawControl('cartData');

			return;
		}

		if ($type === 'serviceUser') {
			try {
				$user = $this->userManager->get()->getUserById($value);

				$this->editedCartService->setServiceUser($user);

				$serviceNumber = $this->cartServiceManager->getServiceNumber($user, (int) $this->editedCartService->getDate()->format('Y'));
				$this->editedCartService->setServiceNumber($serviceNumber);

				$vehicle = $this->vehicleManager->getVehicleByUser($user);
				$this->editedCartService->setCarSPZ($vehicle->getSpz());

				$this->entityManager->flush($this->editedCartService);

				$this->redrawControl('carSPZ');
				$this->redrawControl('serviceNumber');
				$this->redrawControl('cartServiceCardSignServiceUser');

			} catch (NoResultException | NonUniqueResultException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Servisní technik nenalezen.',
				];
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			} catch (CartServiceException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => $e->getMessage(),
				];
			}
		}

		if ($type === 'contractType') {
			if ($checked === true) {
				$this->editedCartService->addContractType($value);
			} else {
				$this->editedCartService->removeContractType($value);
			}

			$this->entityManager->flush($this->editedCartService);
		}

		if ($type === 'contractStatus') {
			if ($checked === true) {
				$this->editedCartService->addContractStatus($value);
			} else {
				$this->editedCartService->removeContractStatus($value);
			}

			$this->entityManager->flush($this->editedCartService);
		}

		//TK

		if ($type === 'technicalCheckMainStatus') {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();
				if ($tk !== null) {
					$tk->setStatus($value);

					$date = DateTime::from($tk->getDate()->getTimestamp());

					$status = $tk->getStatus();
					if ($status === 'A' || $status === 'O') {
						$date2 = $date->modifyClone('+1 year');
					} elseif ($status === 'B') {
						$date2 = $date->modifyClone('+30 days');
					} else {
						$date2 = DateTime::from($date->getTimestamp());
					}

					$tk->setNextTechnicalControl($date2);

					$this->entityManager->flush($tk);

					$this->redrawControl('tkNextCheckControlDate');
				} else {
					$this->payload->toastr = [
						'type' => 'danger',
						'msg' => 'U této servisní zprávy neexistuje protokol o TK',
					];
				}
			} catch (EntityManagerException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => $e->getMessage(),
				];
			}
		}

		//change TK service user
		if ($type === 'tkServiceUser') {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();

				if ($tk !== null) {
					$user = $this->userManager->get()->getUserById($value);
					$tk->setServiceUser($user);

					$this->entityManager->flush($tk);

					$this->template->tk = $tk;
					$this->template->tkServiceUser = $this->serviceUserManager->getServiceUserByUser($user);

					$this->redrawControl('tkServiceUserSignSnippet');
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Servisní technik nenalezen.',
				];
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			} catch (CartServiceException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => $e->getMessage(),
				];
			}
		}

		//tk datum
		if ($type === 'tkDate') {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();

				if ($tk !== null) {
					$date = DateTime::from($value);
					$tk->setDate($date);

					$status = $tk->getStatus();
					if ($status === 'A' || $status === 'O') {
						$date2 = $date->modifyClone('+1 year');
					} elseif ($status === 'B') {
						$date2 = $date->modifyClone('+30 days');
					} else {
						$date2 = DateTime::from($date->getTimestamp());
					}

					$tk->setNextTechnicalControl($date2);

					$this->entityManager->flush($tk);

					$this->redrawControl('tkNextCheckControlDate');
				}

				$this->redrawControl('tkServiceUserSignSnippet');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			}
		}

		// tk datum podpisu
		if ($type === 'tkSignDate') {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();

				if ($tk !== null) {
					$date = DateTime::from($value);
					$tk->setSignDate($date);

					$this->entityManager->flush($tk);
				}

				$this->redrawControl('tkServiceUserSignSnippet');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			}
		}

		// tk jmeno podepisujiciho
		if ($type === 'tkSignName') {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();

				if ($tk !== null) {
					$tk->setSignName($value);
					$this->entityManager->flush($tk);
				}

				$this->redrawControl('tkServiceUserSignSnippet');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'Při ukládání do databáze nastala chyba.',
				];
			}
		}

		//TK formular
		if (strpos($type, 'tk_') === 0) {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();
				if ($tk !== null) {
					$name = 'set' . str_replace('tk_', '', $type);
					$tk->$name($value);
					$this->entityManager->flush($tk);
				} else {
					$this->payload->toastr = [
						'type' => 'danger',
						'msg' => 'U této servisní zprávy neexistuje protokol o TK',
					];
				}
			} catch (EntityManagerException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => $e->getMessage(),
				];
			}
		}
	}

	public function handleTkSignServiceUser(): void
	{
		try {
			$tk = $this->editedCartService->getTechnicalCheckProtocol();
			if ($tk !== null) {
				$tk->setSignedServiceUser(true);
				$this->entityManager->flush($tk);
			} else {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'U této servisní zprávy neexistuje protokol o TK',
				];
			}
		} catch (EntityManagerException $e) {
			$this->payload->toastr = [
				'type' => 'danger',
				'msg' => $e->getMessage(),
			];
		}

		$this->redrawControl('tkServiceUserSignSnippet');
	}

	public function handleTkSign(): void
	{
		try {
			$tk = $this->editedCartService->getTechnicalCheckProtocol();
			if ($tk !== null) {
				$tk->setSigned(true);

				if ($tk->getSignDate() === null) {
					$tk->setSignDate($tk->getDate());
				}

				$this->entityManager->flush($tk);
			} else {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => 'U této servisní zprávy neexistuje protokol o TK',
				];
			}
		} catch (EntityManagerException $e) {
			$this->payload->toastr = [
				'type' => 'danger',
				'msg' => $e->getMessage(),
			];
		}

		$this->redrawControl('tkServiceUserSignSnippet');
	}

	/**
	 * @return Form
	 */
	public function createComponentTechnicalCheckFailureForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('description', 'Text závady')
			->setRequired('Zadejte popis závady');

		$form->addSubmit('submit', 'Uložit');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$tk = $this->editedCartService->getTechnicalCheckProtocol();
				if ($tk !== null) {
					$position = count($tk->getFaults()) + 1;
					$fault = new TechnicalCheckProtocolFault($tk, $values->description, $position);
					$this->entityManager->persist($fault);

					$tk->addFault($fault);
					$this->entityManager->flush([$tk, $fault]);
				} else {
					$this->payload->toastr = [
						'type' => 'danger',
						'msg' => 'U této servisní zprávy neexistuje protokol o TK',
					];
				}
			} catch (EntityManagerException $e) {
				$this->payload->toastr = [
					'type' => 'danger',
					'msg' => $e->getMessage(),
				];
			}

			$form->reset();
			$this->redrawControl('tkFaultList');
		};

		return $form;
	}

	public function createComponentCartServiceReportForm(): Form
	{
		$form = $this->formFactory->create();

		if ($this->editedCartServiceReport === null) {
			$form->addHidden('reportId', 'ID')
				->setDefaultValue('NEW');

			$form->addText('date', 'Datum')
				->setDefaultValue($this->editedCartService->getDate()->format('d.m.Y'))
				->setRequired('Zadejte datum');

			$form->addText('hours', 'Hodiny')
				->setRequired('Zadejte počet hodin v pracovní dny.');

			$form->addText('description', 'Description');

			$form->addCheckbox('usePausal', 'Pausal')
				->setDefaultValue(true);

			$form->addInteger('distance', 'Ujete km')
				->setDefaultValue((string) ($this->editedCartService->getCart()->getCompanyStock()->getTravelDistance() ?? ''));
		} else {
			$form->addHidden('reportId', 'ID')
				->setDefaultValue($this->editedCartServiceReport->getId());

			$form->addText('date', 'Datum')
				->setDefaultValue($this->editedCartServiceReport->getDate()->format('d.m.Y'))
				->setRequired('Zadejte datum');

			$hours = $this->editedCartServiceReport->getHoursWeekDay() + $this->editedCartServiceReport->getHoursWeekendDay();

			$form->addText('hours', 'Hodiny')
				->setDefaultValue($hours)
				->setRequired('Zadejte počet hodin v pracovní dny.');

			$form->addText('description', 'Description')
				->setDefaultValue($this->editedCartServiceReport->getDescription())
				->setRequired('Zadejte popis');

			$form->addCheckbox('usePausal', 'Pausal')
				->setDefaultValue($this->editedCartServiceReport->getDistance() > 0);

			$form->addInteger('distance', 'Ujete km')
				->setDefaultValue($this->editedCartServiceReport->getDistance() ?? 0);
		}

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$date = DateTime::from($values->date . $this->editedCartService->getDate()->format(' 00:00:00'));
				$day = (int) $date->format('N');
				if ($day === 6 || $day === 7) {
					$weekHours = 0;
					$weekendHours = (float) str_replace(',', '.', $values->hours);
				} else {
					$weekHours = (float) str_replace(',', '.', $values->hours);
					$weekendHours = 0;
				}

				$description = (
				($values->description === null || $values->description === '')
					? 'Mechanická'
					: $values->description
				);

				if ($values->reportId === 'NEW' || $values->reportId === '') {
					$report = new CartServiceReport(
						$this->editedCartService,
						$date,
						$weekHours,
						$weekendHours,
						$description
					);
				} else {
					$report = $this->cartServiceManager->getCartServiceReportById($values->reportId);
					$report->setDate($date);
					$report->setHoursWeekDay($weekHours);
					$report->setHoursWeekendDay($weekendHours);
					$report->setDescription($description);
				}

				if ($this->editedCartService->getTravelRateType() === TravelRateType::DISTANCE) {
					$report->setDistance($values->distance ?? 0);
				} else {
					$report->setDistance($values->usePausal ? 1 : 0);
				}

				$this->entityManager->persist($report);

				$this->editedCartService->addReport($report);

				$this->entityManager->flush([$this->editedCartService, $report]);

				$form->reset();
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			} catch (NoResultException | NonUniqueResultException $e) {
				bdump($values->reportId);
				$this->flashMessage('Z databáze nelze načíst požadovaný záznam.', 'error');
			}

			$this->redrawControl('reportList');
			$this->redrawControl('flashes');
			$this->redrawControl('buttonArea');
		};

		return $form;
	}

	/**
	 * @throws EntityManagerException
	 */
	public function handleCartServiceSign(): void
	{
		$this->editedCartService->setSigned(true);
		$this->entityManager->flush($this->editedCartService);

		$this->redrawControl('cartServiceCardSign');
	}

	/**
	 * @throws EntityManagerException
	 */
	public function handleCartServiceSignServiceUser(): void
	{
		$this->editedCartService->setSignedServiceUser(true);
		$this->entityManager->flush($this->editedCartService);

		$this->redrawControl('cartServiceCardSignServiceUser');
	}

	/**
	 * @param string $reportId
	 */
	public function handleRemoveCartServiceReport(string $reportId): void
	{
		try {
			foreach ($this->editedCartService->getReports() as $report) {
				if ($report->getId() === $reportId) {
					$this->editedCartService->removeReport($report);
					$this->entityManager->remove($report)->flush();
				}
			}
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redrawControl('reportList');
		$this->redrawControl('flashes');
	}

	/**
	 * @param string $faultId
	 */
	public function handleRemoveTechnicalCheckProtocolFault(string $faultId): void
	{
		try {
			$tk = $this->editedCartService->getTechnicalCheckProtocol();

			if ($tk !== null) {
				foreach ($tk->getFaults() as $fault) {
					if ($fault->getId() === $faultId) {
						$tk->removeFault($fault);

						$this->entityManager->remove($fault)->flush();
					}
				}
			}

		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redrawControl('tkFaultList');
		$this->redrawControl('flashes');
	}

	/**
	 * @param string $reportId
	 */
	public function handleEditCartServiceReport(string $reportId): void
	{
		try {
			$this->editedCartServiceReport = $this->cartServiceManager->getCartServiceReportById($reportId);

			$this->template->reportEdit = true;

			$this->redrawControl('reportList');
			$this->redrawControl('flashes');
		} catch (NoResultException | NonUniqueResultException $e) {

		}
	}

	/**
	 * @return Form
	 * @throws EntityManagerException
	 * @throws UnitException
	 */
	public function createComponentCartServiceItemForm(): Form
	{
		$form = $this->formFactory->create();

		if ($this->editedCartServiceItem === null) {
			$form->addHidden('editedId', 'id')
				->setDefaultValue('new');

			if ($this->usedStockItem !== null) {
				$form->addHidden('stockItemId')
					->setDefaultValue($this->usedStockItem->getId());
				$form->addHidden('name')
					->setDefaultValue($this->usedStockItem->getName())
					->setRequired('Zadejte název položky');
				$form->addHidden('unit', 'Unit')
					->setDefaultValue($this->usedStockItem->getUnit()->getId());
			} else {
				$form->addHidden('stockItemId');
				$form->addText('name', 'Název')
					->setRequired('Zadejte název položky');
				$form->addSelect('unit', 'Unit', $this->unitManager->getUnitsForForm())
					->setDefaultValue($this->unitManager->getDefaultUnit()->getId());
			}

			$form->addText('quantity', 'Množství')
				->setDefaultValue(1)
				->setRequired('Zadejte množství');
		} else {
			$form->addHidden('editedId', 'id')
				->setDefaultValue($this->editedCartServiceItem->getId());

			if ($this->usedStockItem !== null) {
				$form->addHidden('stockItemId')
					->setDefaultValue($this->usedStockItem->getId());
				$form->addHidden('name')
					->setDefaultValue($this->usedStockItem->getName())
					->setRequired('Zadejte název položky');
				$form->addHidden('unit', 'Unit')
					->setDefaultValue($this->usedStockItem->getUnit()->getId());
			} else {
				$form->addHidden('stockItemId');
				$form->addText('name', 'Název')
					->setDefaultValue($this->editedCartServiceItem->getName())
					->setRequired('Zadejte název položky');
				$form->addSelect('unit', 'Unit', $this->unitManager->getUnitsForForm())
					->setDefaultValue($this->editedCartServiceItem->getUnit()->getId());
			}

			$form->addText('quantity', 'Množství')
				->setDefaultValue($this->editedCartServiceItem->getCount())
				->setRequired('Zadejte množství');
		}

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$quantity = (float) str_replace(',', '.', $values->quantity);

				if ($quantity <= 0) {
					$quantity = 1.0;
				}

				$unit = $this->unitManager->getById($values->unit);

				if ($values->editedId !== 'new' && $values->editedId !== '' && $values->editedId !== null) {
					$item = $this->cartServiceManager->getCartServiceItemById($values->editedId);

					if (isset($form['stockItemId']) && $values->stockItemId !== null && $values->stockItemId !== '') {
						try {
							$stockItem = $this->stockManager->getStockItemById($values->stockItemId);
							$item->setItem($stockItem);
							$item->setName($stockItem->getName());
						} catch (NoResultException | NonUniqueResultException $e) {
							$this->flashMessage('Požadovaná skladová položka nenalezena.', 'error');
							$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
						}
					} else {
						$item->setName($values->name);
					}
					$item->setCount($quantity);
					$item->setUnit($unit);
				} else {
					$item = null;

					if (isset($form['stockItemId']) && $values->stockItemId !== null && $values->stockItemId !== '') {
						try {
							$stockItem = $this->stockManager->getStockItemById($values->stockItemId);

							$item = new CartServiceItem(
								$this->editedCartService,
								$stockItem->getName(),
								$quantity,
								$unit
							);

							$item->setItem($stockItem);
						} catch (NoResultException | NonUniqueResultException $e) {
							$this->flashMessage('Požadovaná skladová položka nenalezena.', 'error');
							$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
						}
					} else {
						$item = new CartServiceItem(
							$this->editedCartService,
							$values->name,
							$quantity,
							$unit
						);
					}

					$position = count($this->editedCartService->getItems()) + 1;
					$item->setPosition($position);

					$this->entityManager->persist($item);

					$this->editedCartService->addItem($item);
				}

				$this->entityManager->flush($item);

				$form->reset();

				$form['unit']->setDefaultValue($this->unitManager->getDefaultUnit()->getId());

				$this->redrawControl('itemsList');
				$this->redrawControl('searchedItems');
				$this->redrawControl('flashes');
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->flashMessage('Požadovaná servisní operace neexistuje.', 'error');

				$this->redrawControl('flashes');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');

				$this->redrawControl('flashes');
			}
		};

		return $form;
	}

	/**
	 * @param string $stockItemId
	 */
	public function handleCartServiceUseStockItem(string $stockItemId): void
	{
		try {
			$this->usedStockItem = $this->stockManager->getStockItemById($stockItemId);
			$this->template->usedStockItem = $this->usedStockItem;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->usedStockItem = null;
		}

		$this->redrawControl('itemsList');
	}

	/**
	 * @param string $search
	 */
	public function handleCartServiceSearchItem(string $search = ''): void
	{
		$this->template->items = $this->cartServiceManager->searchItems($search);

		$this->redrawControl('searchedItems');
	}

	/**
	 * @param string $itemId
	 */
	public function handleCartServiceRemoveItem(string $itemId): void
	{
		try {
			if ($this->editedCartService !== null) {
				$item = $this->cartServiceManager->getCartServiceItemById($itemId);
				$this->editedCartService->removeItem($item);

				$this->entityManager->remove($item)->flush();
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná položka neexistuje.', 'error');

			$this->redrawControl('flashes');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');

			$this->redrawControl('flashes');
		}

		$this->redrawControl('itemsList');
	}

	/**
	 * @param string|null $itemId
	 */
	public function handleCartServiceEditItem(string $itemId = null): void
	{
		try {
			$this->editedCartServiceItem = $this->cartServiceManager->getCartServiceItemById($itemId);

			if ($this->editedCartServiceItem->getItem() !== null) {
				$this->usedStockItem = $this->editedCartServiceItem->getItem();
			}

			$this->template->editingCartServiceItem = true;
			$this->template->usedStockItem = $this->usedStockItem;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->editedCartServiceItem = null;
		}

		$this->redrawControl('itemsList');
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceOperationForm(): Form
	{
		$form = $this->formFactory->create();

		if ($this->editedCartServiceOperation === null) {
			$form->addHidden('editedId', 'id')
				->setDefaultValue('new');

			$form->addText('name', 'Název')
				->setRequired('Zadejte název operace.');
		} else {
			$form->addHidden('editedId', 'id')
				->setDefaultValue($this->editedCartServiceOperation->getId());

			$form->addText('name', 'Název')
				->setDefaultValue($this->editedCartServiceOperation->getName())
				->setRequired('Zadejte název operace.');
		}

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				if ($values->editedId !== 'new' && $values->editedId !== '' && $values->editedId !== null) {
					$operation = $this->cartServiceManager->getCartServiceOperationById($values->editedId);
					$operation->setName($values->name);
				} else {
					$operation = new CartServiceOperation($this->editedCartService, $values->name);

					$position = count($this->editedCartService->getOperations()) + 1;
					$operation->setPosition($position);

					$this->entityManager->persist($operation);
					$this->editedCartService->addOperation($operation);
				}

				$this->entityManager->flush([$operation, $this->editedCartService]);

				$form->reset();

				$this->redrawControl('operationList');
				$this->redrawControl('operationQuickBtn');
				$this->redrawControl('searchedOperations');
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->flashMessage('Požadovaná servisní operace neexistuje.', 'error');

				$this->redrawControl('flashes');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');

				$this->redrawControl('flashes');
			}
		};

		return $form;
	}

	/**
	 * @param string $search
	 */
	public function handleCartServiceSearchOperation(string $search = ''): void
	{
		$this->template->operations = $this->cartServiceManager->searchOperations($search);

		$this->redrawControl('searchedOperations');
	}

	/**
	 * @param string $operationId
	 */
	public function handleCartServiceAddOperation(string $operationId): void
	{
		try {
			$o = $this->cartServiceManager->getOperationById($operationId);

			$operation = new CartServiceOperation($this->editedCartService, $o->getName());
			$operation->setPrice($o->getPrice());
			$operation->setOperation($o);

			$position = count($this->editedCartService->getOperations()) + 1;

			$operation->setPosition($position);

			$this->entityManager->persist($operation);

			$this->editedCartService->addOperation($operation);

			$this->entityManager->flush([$operation, $this->editedCartService]);

			$this->redrawControl('operationList');
			$this->redrawControl('operationQuickBtn');
			$this->redrawControl('searchedOperations');
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná servisní operace neexistuje.', 'error');

			$this->redrawControl('flashes');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');

			$this->redrawControl('flashes');
		}
	}

	/**
	 * @param string $operationId
	 */
	public function handleCartServiceRemoveOperation(string $operationId): void
	{
		try {
			if ($this->editedCartService !== null) {
				$operation = $this->cartServiceManager->getCartServiceOperationById($operationId);
				$this->editedCartService->removeOperation($operation);

				$this->entityManager->remove($operation)->flush();
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná servisní operace neexistuje.', 'error');

			$this->redrawControl('flashes');
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');

			$this->redrawControl('flashes');
		}

		$this->redrawControl('operationQuickBtn');
		$this->redrawControl('operationList');
	}

	/**
	 * @param string|null $operationId
	 */
	public function handleCartServiceEditOperation(string $operationId = null): void
	{
		try {
			$this->editedCartServiceOperation = $this->cartServiceManager->getCartServiceOperationById($operationId);
			$this->template->editingCartServiceOperation = true;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->editedCartServiceOperation = null;
		}

		$this->redrawControl('operationQuickBtn');
		$this->redrawControl('operationList');
	}

	/**
	 * @throws AbortException
	 * @throws EntityManagerException
	 * @throws NonUniqueResultException
	 */
	public function handleAddTechnicalCheckProtocol(): void
	{
		$this->cartServiceManager->addTechnicalCheckProtocol($this->editedCartService);
		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
	}

	/**
	 * @throws AbortException
	 */
	public function handleCartServiceSubmit(): void
	{
		try {
			$this->editedCartService->setSubmitted(true);
			$this->entityManager->flush($this->editedCartService);
			$this->cartServiceManager->updateCartByCartService($this->editedCartService);

			$this->flashMessage('Servisní zpráva byla úspěšně odevzdána.', 'success');

			$email = $this->cartServiceManager->sendCartServiceEmail($this->editedCartService);

			if ($email === true) {
				$this->flashMessage('Servisní zpráva byla odeslána emailem.', 'info');
			}

			$this->scheduleManager->checkIssueByCartService($this->editedCartService);
		} catch (EntityManagerException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function actionExportCartService(string $id): void
	{
		try {
			$cartService = $this->cartServiceManager->getCartServiceById($id);

			$this->pdfExportManager->exportCartServiceToPDF($cartService);

			return;
		} catch (NoResultException | NonUniqueResultException | MpdfException $e) {
			$this->flashMessage('Servisní zpráva nenalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 * @throws MpdfException
	 */
	public function actionExportTechnicalCheckProtocol(string $id): void
	{
		try {
			$cartService = $this->cartServiceManager->getCartServiceById($id);

			if ($cartService->getTechnicalCheckProtocol() !== null) {
				$this->pdfExportManager->exportTechnicalCheckProtocolToPDF($cartService);

				return;
			}

			$this->flashMessage('Servisní zpráva neobsahuje protokol TK', 'error');
			$this->redirect('detailCartService', ['id' => $cartService->getId()]);

			return;
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Servisní zpráva nenalezena.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $id
	 * @throws AbortException
	 */
	public function handleDeleteContact(string $id): void
	{
		try {
			$contact = $this->companyManager->getContactById($id);
			try {
				$this->entityManager->remove($contact)->flush();
				$this->flashMessage('Kontak byl smazán.', 'info');
			} catch (EntityManagerException $e) {
				$this->flashMessage('Při odstraňování kontaktu nastala chyba.', 'error');
			}

			if ($contact->getCompanyStock() !== null) {
				$this->redirect('contact', [
					'companyId' => $contact->getCompany()->getId(),
					'companyStockId' => $contact->getCompanyStock()->getId(),
				]);
			} else {
				$this->redirect('contact', [
					'companyId' => $contact->getCompany()->getId(),
				]);
			}
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaný kontakt neexistuje.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceInvoiceForm(): Form
	{
		$form = $this->formFactory->create();

		$date = $this->editedCartService->getInvoiceDate();

		$userList = [];
		$invoiceUser = null;
		if ($this->checkUserRight('page__company__invoiceChangeUser')) {
			foreach ($this->userManager->get()->getUsersByRole('faktury') as $u) {
				$userList[$u->getId()] = $u->getName();
			}

			$invoiceUser = $this->editedCartService->getInvoiceUser();
			if ($invoiceUser !== null && !isset($userList[$invoiceUser->getId()])) {
				$userList[$invoiceUser->getId()] = $invoiceUser->getName();
			}
		}

		$form->addText('invoiceNumber', 'Číslo faktury')
			->setDefaultValue($this->editedCartService->getInvoiceNumber() ?? '');

		$form->addDate('invoiceDate', 'Datum fakturace')
			->setDefaultValue($date !== null ? $date->format('d.m.Y') : '');

		$form->addSelect('invoiceUser', 'Fakturovat', $userList)
			->setPrompt('Nezadáno')
			->setDefaultValue($invoiceUser !== null ? $invoiceUser->getId() : null);

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			$number = $values->invoiceNumber === '' ? null : $values->invoiceNumber;

			if ($number !== null) {
				try {
					$invoice = $this->invoiceManager->getInvoiceByCode((string) $number);
					$this->editedCartService->setInvoice($invoice);
				} catch (NoResultException | NonUniqueResultException $e) {
					$this->editedCartService->setInvoiced(true);
					$this->editedCartService->setInvoiceNumber($number);
					$this->editedCartService->setInvoiceDate($values->invoiceDate === '' ? null : $values->invoiceDate);

					$invoiceUser = null;
					if ($this->checkUserRight('page__company__invoiceChangeUser')) {
						try {
							if ($values->invoiceUser !== null) {
								$invoiceUser = $this->userManager->get()->getUserById($values->invoiceUser);
							}
						} catch (NoResultException | NonUniqueResultException $e) {
							$invoiceUser = null;
						}
					} else {
						$invoiceUser = $this->getUser()->getIdentity();
					}

					$this->editedCartService->setInvoiceUser($invoiceUser);
				}
			} else {
				$this->editedCartService->setInvoiced(true);
				$this->editedCartService->setInvoiceNumber(null);
				$this->editedCartService->setInvoiceDate($values->invoiceDate === '' ? null : $values->invoiceDate);

				$invoiceUser = null;
				if ($this->checkUserRight('page__company__invoiceChangeUser')) {
					try {
						if ($values->invoiceUser !== null) {
							$invoiceUser = $this->userManager->get()->getUserById($values->invoiceUser);
						}
					} catch (NoResultException | NonUniqueResultException $e) {
						$invoiceUser = null;
					}
				} else {
					$invoiceUser = $this->getUser()->getIdentity();
				}

				$this->editedCartService->setInvoiceUser($invoiceUser);
			}

			try {
				$this->entityManager->flush($this->editedCartService);

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}

			$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceCoordinatorForm(): Form
	{
		$userList = [];
		foreach ($this->userManager->get()->getUsersByRole('servisni-koordinator') as $user) {
			$userList[$user->getId()] = $user->getName();
		}

		$form = $this->formFactory->create();

		$form->addSelect('coordinatorUser', 'Koordinátor', $userList)
			->setPrompt('Nepřiřazeno')
			->setDefaultValue(
				(
					$this->editedCartService !== null
					&& $this->editedCartService->isCoordinatorUser()
				)
					? $this->editedCartService->getCoordinatorUser()->getId()
					: null
			);

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			if ($this->editedCartService !== null) {
				try {
					if ($values->coordinatorUser !== null) {
						try {
							$user = $this->userManager->get()->getUserById($values->coordinatorUser);
							$this->editedCartService->setCoordinatorUser($user);
							$this->flashMessage('Změny byly uloženy.', 'success');
						} catch (NoResultException | NonUniqueResultException $e) {
							$this->flashMessage('Požadovaný uživatel nenalezen.', 'error');
						}
					} else {
						$this->editedCartService->setCoordinatorUser(null);
						$this->flashMessage('Změny byly uloženy.', 'success');
					}

					$this->entityManager->flush($this->editedCartService);
				} catch (EntityManagerException $e) {
					Debugger::log($e);
					$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
				}

				$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
			}

			$this->redirect('default');
		};

		return $form;
	}

	/**
	 * @throws AbortException
	 */
	public function handleCartServiceOfferCreated(): void
	{
		try {
			$this->editedCartService->removeContractStatus('create-offer');
			$this->editedCartService->addContractStatus('send-offer');
			$this->entityManager->flush($this->editedCartService);
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
	}

	/**
	 * @throws AbortException
	 */
	public function handleCartServiceOfferFinished(): void
	{
		try {
			$this->editedCartService->removeContractStatus('send-offer');
			$this->entityManager->flush($this->editedCartService);
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
	}

	/**
	 * @throws AbortException
	 */
	public function handleCartServiceFinished(): void
	{
		try {
			$this->editedCartService->removeContractStatus('editing');
			$this->editedCartService->addContractStatus('finished');
			$this->entityManager->flush($this->editedCartService);
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
	}

	/**
	 * @param string $type
	 * @throws AbortException
	 */
	public function handleGenerateInvoice(string $type = 'invoice'): void
	{
		if ($this->editedCartService === null) {
			$this->flashMessage('Neznámá servisní zpráva.', 'error');
			$this->redirect('default');
		}

		try {
			$invoice = $this->cartServiceManager->createInvoice([$this->editedCartService->getId()], $type);

			$this->redirect('Invoice:detail', ['id' => $invoice->getId()]);
		} catch (CartServiceException | InvoiceException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}

		$this->redirect('detailCartService', ['id' => $this->editedCartService->getId()]);
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceCartNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('serviceNote', 'Poznámka')
			->setDefaultValue($this->editedCart->getServiceNote() ?? '');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {

			if ($this->editedCart !== null) {
				try {
					$this->editedCart->setServiceNote($values->serviceNote !== '' ? $values->serviceNote : null);
					$this->entityManager->flush($this->editedCart);
					$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
				} catch (EntityManagerException $e) {
					Debugger::log($e);
					$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
				}

				$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
			}

			$this->redirect('default');
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceServiceNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('serviceNote', 'Servisní poznámka')
			->setDefaultValue($this->editedCartService->getServiceNote() ?? '');

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {

			if ($this->editedCartService !== null) {
				try {
					$this->editedCartService->setServiceNote($values->serviceNote !== '' ? $values->serviceNote : null);
					$this->entityManager->flush($this->editedCartService);
					$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
				} catch (EntityManagerException $e) {
					Debugger::log($e);
					$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
				}

				$this->redirect('detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
			}

			$this->redirect('default');
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCompanyNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('note', 'Poznámka')
			->setDefaultValue($this->editedCompany->getNote() ?? '');

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$this->editedCompany->setNote($values->note);
				$this->entityManager->flush($this->editedCompany);
				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}

			$this->redirect('detail', ['id' => $this->editedCompany->getId()]);
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentCompanyStockNoteForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addTextArea('note', 'Poznámka')
			->setDefaultValue($this->editedStock->getNote() ?? '');

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			try {
				$this->editedStock->setNote($values->note);
				$this->entityManager->flush($this->editedStock);
				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
			} catch (EntityManagerException $e) {
				Debugger::log($e);
				$this->flashMessage('Při ukládání do databáze nastala chyba.', 'error');
			}

			$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
		};

		return $form;
	}

	/**
	 * @throws AbortException
	 * @throws EntityManagerException
	 * @throws InvalidLinkException
	 */
	public function handleCartServiceResetInvoice(): void
	{
		if ($this->editedCartService === null) {
			$this->flashMessage('Neznámá servisná zpráva.', 'error');
			$this->redirect('default');
		}

		$entities = [];
		$entities[] = $this->editedCartService;

		$invoice = $this->editedCartService->getInvoice();
		if ($invoice !== null) {
			$link = $this->link('Company:detailCartService', ['id' => $this->editedCartService->getId()]);

			$history = new InvoiceHistory(
				$invoice,
				'Odebrána servisní zpráva: <a href="' . $link . '">' . $this->editedCartService->getServiceNumber() . '</a>'
			);

			$user = $this->getUser()->getIdentity();
			if ($user && $user instanceof User) {
				$history->setUser($user);
			}

			$this->entityManager->persist($history);

			$invoice->addHistory($history);

			$entities[] = $invoice;
			$entities[] = $history;
		}

		$this->editedCartService->setInvoice(null);
		$this->editedCartService->setInvoiceDate(null);
		$this->editedCartService->setInvoiceUser(null);
		$this->editedCartService->setInvoiced(false);
		$this->editedCartService->setInvoiceNumber(null);

		try {
			$this->entityManager->flush($entities);

			$this->flashMessage('Fakturace servisní zprávy byla zrušena.', 'info');
			$this->redirect('detailCartService', [
				'id' => $this->editedCartService->getId(),
				'ret' => $this->returnButton,
			]);
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->flashMessage('Při ukládání nastala chyba.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @param string $name
	 * @return DataGrid
	 * @throws CurrencyException
	 * @throws DataGridException
	 */
	public function createComponentInvoiceTable(string $name): DataGrid
	{
		$currency = $this->currencyManager->getDefaultCurrency();

		$grid = new DataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(InvoiceCore::class)
				->createQueryBuilder('invoice')
				->select('invoice')
				->where('invoice.deleted = :f')
				->setParameter('f', 0)
				->andWhere('invoice.company = :company')
				->setParameter('company', $this->editedCompany->getId())
				->andWhere('invoice INSTANCE OF ' . Invoice::class . ' OR invoice INSTANCE OF ' . InvoiceProforma::class)
				->orderBy('invoice.number', 'DESC')
		);

		$grid->setTemplateFile(__DIR__ . '/datagrid/tableTemplate.latte');

		$grid->setRowCallback(static function (InvoiceCore $invoice, Html $row): void {
			$status = $invoice->getStatus();
			if ($status === InvoiceStatus::ACCEPTED) {
				$row->addClass('table-success');

				return;
			}

			if ($status === InvoiceStatus::DENIED) {
				$row->addClass('table-danger');

				return;
			}

			if ($status === InvoiceStatus::CREATED) {
				$row->addClass('table-warning');

				return;
			}

			if ($status === InvoiceStatus::PAY_ALERT_THREE) {
				$row->addClass('table-danger');

				return;
			}
		});

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(function (InvoiceCore $invoice): string {
				$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);

				return '<a href="' . $link . '">' . $invoice->getNumber() . '</a>'
					. '<br>'
					. '<small class="'
					. InvoiceStatus::getColorByStatus($invoice->getStatus())
					. '">'
					. InvoiceStatus::getNameByStatus($invoice->getStatus())
					. '</small>';;
			})
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(function (InvoiceCore $invoice): string {
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

			})
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Vystaveno')
			->setRenderer(static function (InvoiceCore $invoiceCore): string {
				return $invoiceCore->getDate()->format('d.m.Y') . '<br><small>' . $invoiceCore->getCreateUser()->getName() . '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('taxDate', 'Daň. plnění')
			->setRenderer(function (InvoiceCore $invoiceCore): string {
				if ($invoiceCore->isProforma()) {
					$invoice = $invoiceCore->getInvoice();
					if ($invoice !== null) {
						$link = $this->link('Invoice:show', ['id' => $invoice->getId()]);
						$str = '<small><a href="' . $link . '" title="Faktura"><i class="fas fa-file-invoice"></i>&nbsp;' . $invoice->getNumber() . '</a></small>';
					} else {
						$str = '&nbsp;';
					}
					return '<span class="text-info"><small>Záloha</small></span><br>' . $str;
				}

				$str = '<small>&nbsp;</small>';

				/** @var FixInvoice $fixInvoice */
				$fixInvoice = $invoiceCore->getFixInvoice();
				if ($fixInvoice !== null) {
					$link = $this->link('Invoice:show', ['id' => $fixInvoice->getId()]);
					$str = '<small><a href="' . $link . '" title="Dobropis" style="color: rgb(194, 0, 64);"><i class="fas fa-file-invoice"></i>&nbsp;' . $fixInvoice->getNumber();
					if (
						$fixInvoice->getAcceptStatus1() !== InvoiceStatus::ACCEPTED
						|| $fixInvoice->getAcceptStatus2() !== InvoiceStatus::ACCEPTED
					) {
						if ($fixInvoice->getAcceptStatus1() === InvoiceStatus::WAITING) {
							$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
						} elseif ($fixInvoice->getAcceptStatus1() === InvoiceStatus::DENIED) {
							$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
						} elseif ($fixInvoice->getAcceptStatus1() === InvoiceStatus::ACCEPTED) {
							$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
						}

						if ($fixInvoice->getAcceptStatus2() === InvoiceStatus::WAITING) {
							$str .= '&nbsp;<i class="fas fa-clock text-warning"></i>';
						} elseif ($fixInvoice->getAcceptStatus2() === InvoiceStatus::DENIED) {
							$str .= '&nbsp;<i class="fas fa-times text-danger"></i>';
						} elseif ($fixInvoice->getAcceptStatus2() === InvoiceStatus::ACCEPTED) {
							$str .= '&nbsp;<i class="fas fa-check text-success"></i>';
						}
					}
					$str .= '</a></small>';
				}

				return $invoiceCore->getTaxDate()->format('d.m.Y') . '<br>' . $str;
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('dueDate', 'Splatnost')
			->setRenderer(function (InvoiceCore $invoiceCore): string {
				$ret = $invoiceCore->getDueDate()->format('d.m.Y');

				if ($invoiceCore->isPaid() && $invoiceCore->getPayDate() !== null) {
					$ret .= '<br><small class="text-success"><i class="fas fa-coins text-warning" title="Uhrazeno"></i>&nbsp;' . $invoiceCore->getPayDate()->format('d.m.Y') . '</small>';

					if ($invoiceCore instanceof InvoiceProforma) {
						$payDocument = $invoiceCore->getPayDocument();
						if ($payDocument !== null) {
							$link = $this->link('Invoice:show', ['id' => $payDocument->getId()]);
							$ret .= '&nbsp;<small><a href="' . $link . '" style="color: rgb(75, 0, 150);" title="Doklad k přijaté platbě"><i class="fas fa-file-invoice-dollar"></i></a></small>';
						}
					}
				} else {
					$ret .= '<br>';
					$diff = $invoiceCore->getPayDateDiff();
					if ($diff < -4) {
						$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' dní</small>';
					} elseif ($diff < -1) {
						$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' dny</small>';
					} elseif ($diff < 0) {
						$ret .= '<small class="text-success">zbývá&nbsp;' . -$diff . ' den</small>';
					} elseif ($diff === 0) {
						$ret .= '<small class="text-success">Dnes</small>';
					} elseif ($diff > 4) {
						$ret .= '<small class="text-danger">' . $diff . ' dní po splatnosti</small>';
					} elseif ($diff > 1) {
						$ret .= '<small class="text-danger">' . $diff . ' dny po splatnosti</small>';
					} else {
						$ret .= '<small class="text-danger">' . $diff . ' den po splatnosti</small>';
					}
				}

				return $ret;
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('price', 'Částka')
			->setRenderer(static function (InvoiceCore $invoiceCore) use ($currency): string {
				$totalPrice = $invoiceCore->getTotalPrice();
				if ($invoiceCore instanceof Invoice) {
					$fixInvoice = $invoiceCore->getFixInvoice();

					if ($fixInvoice !== null) {
						$totalPrice += $fixInvoice->getTotalPrice();
					}
				}

				if ($totalPrice < 0) {
					return '<b class="text-danger">' . Number::formatPrice($totalPrice, $invoiceCore->getCurrency(), 2) . '</b>'
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
			})
			->setAlign('right')
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addColumnText('accept', 'Schválení')
			->setRenderer(function (InvoiceCore $invoiceCore): string {
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
			})
			->setAlign('center')
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(function (InvoiceCore $invoiceCore) {
				$link = $this->link('Invoice:show', ['id' => $invoiceCore->getId()]);

				return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
			});

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');

		//Cislo faktury
		$grid->addFilterText('number', 'Číslo:');

		//Stav
		$statusList = [
			'' => 'Vše',
			'paid' => 'Uhrazené',
			'unpaid' => 'Neuhrazené',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(static function (QueryBuilder $qb, string $status): QueryBuilder {
				if ($status === 'unpaid') {
					$qb->andWhere('invoice.payDate IS NULL');
				} elseif ($status === 'paid') {
					$qb->andWhere('invoice.payDate IS NOT NULL');
				}

				return $qb;
			});

		$grid->setOuterFilterRendering();

		return $grid;
	}

	/**
	 * @param string $name
	 * @return DataGrid
	 * @throws DataGridException
	 */
	public function createComponentCartDocumentTable(string $name): DataGrid
	{
		$grid = new DataGrid($this, $name);

		$grid->setTemplateFile(__DIR__ . '/datagrid/tableTemplate.latte');

		$grid->setDataSource(
			$this->documentManager->getDocumentsByCartForTable($this->editedCart)
		);

		$grid->setRowCallback(static function (DocumentCore $document, Html $row): void {
			$status = $document->getStatus();

			if ($status === DocumentStatus::DENIED) {
				$row->class('table-danger');

				return;
			}

			if ($status === DocumentStatus::EDIT) {
				$row->class('table-info');

				return;
			}
		});

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(function (DocumentCore $document): string {
				$link = $this->link('Documents:show', ['id' => $document->getId()]);

				return '<a href="' . $link . '">' . $document->getNumber() . '</a>'
					. '<br>'
					. '<small class="'
					. DocumentStatus::getColorByStatus($document->getStatus())
					. '">'
					. DocumentStatus::getNameByStatus($document->getStatus())
					. '</small>';
			})
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('type', 'Dokument')
			->setRenderer(function (DocumentCore $document): string {
				$link = $this->link('Documents:show', ['id' => $document->getId()]);

				$type = $document->getName();

				if ($document instanceof PurchaseContractDocument) {
					$type = 'Kupní smlouva<br><small>'
						. $document->getBuyCompanyName()
						. ' - ' . $document->getBrand()->getName()
						. ' - ' . $document->getCartType()->getName()
						. '</small>';
				} elseif ($document instanceof CartDocument) {
					$type = 'Dokumentace ke stroji<br><small>'
						. $document->getCart()->getCompanyStock()->getCompany()->getName()
						. ' - ' . $document->getCart()->getBrand()->getName()
						. ' - ' . $document->getCart()->getCartType()->getName()
						. ' (' . $document->getCart()->getName(true) . ')'
						. '</small>';
				}

				return '<a href="' . $link . '">' . $type . '</a>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('date', 'Vytvořeno')
			->setRenderer(static function (DocumentCore $document): string {
				return $document->getCreateDate()->format('d.m.Y') . '<br><small>' . $document->getCreateUser()->getName() . '</small>';
			})
			->setTemplateEscaping(false)
			->setFitContent();

		$grid->addColumnText('accept', 'Schválení')
			->setRenderer(function (DocumentCore $document): string {
				if ($document->isSubmitted() === false) {
					return '<span class="text-warning">Editace</span>';
				}

				$ret = '';
				$link = $this->link('Documents:show', ['id' => $document->getId()]);

				if ($document->getAcceptStatus() === DocumentStatus::DENIED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
				} elseif ($document->getAcceptStatus() === DocumentStatus::WAITING_FOR_ACCEPT) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
				} elseif ($document->getAcceptStatus() === DocumentStatus::ACCEPTED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
				}

				return $ret;
			})
			->setAlign('center')
			->setFitContent()
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(function (DocumentCore $document) {
				$link = $this->link('Documents:show', ['id' => $document->getId()]);

				return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
			});

		return $grid;
	}

	/**
	 * @param string $name
	 * @return DataGrid
	 * @throws DataGridException
	 */
	public function createComponentOfferTable(string $name): DataGrid
	{
		$grid = new DataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(Offer::class)
				->createQueryBuilder('offer')
				->select('offer')
				->andWhere('offer.company = :company')
				->setParameter('company', $this->editedCompany->getId())
				->orderBy('offer.createdDate', 'DESC')
		);

		$grid->setTemplateFile(__DIR__ . '/datagrid/tableTemplate.latte');

		$grid->setRowCallback(static function (Offer $offer, Html $row): void {
			$status = $offer->getStatus();

			if ($status === OfferStatus::DENIED) {
				$row->addClass('table-danger');

				return;
			}

			if ($status === OfferStatus::ACCEPTED) {
				$row->addClass('table-warning');

				return;
			}

			if ($status === OfferStatus::EDIT) {
				$row->addClass('table-info');

				return;
			}
		});

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(function (Offer $offer): string {
				$link = $this->link('Offer:show', ['id' => $offer->getId()]);

				return '<a href="' . $link . '">' . $offer->getNumber() . '</a>'
					. '<br>'
					. '<small class="'
					. OfferStatus::getColorByStatus($offer->getStatus())
					. '">'
					. OfferStatus::getNameByStatus($offer->getStatus())
					. '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(function (Offer $offer): string {
				$link = $this->link('Company:detail', ['id' => $offer->getCompany()->getId()]);
				$ret = '<a href="' . $link . '">' . Strings::truncate($offer->getCompany()->getName(), 60) . '</a>';

				$address = $offer->getCompany()->getInvoiceAddress();

				return $ret
					. '<br>'
					. '<small>'
					. $address->getStreet() . ', '
					. $address->getCity() . ', '
					. $address->getZipCode()
					. '</small>';

			})
			->setTemplateEscaping(false);

		$grid->addColumnText('companyStock', 'Pobočka')
			->setRenderer(function (Offer $offer): string {
				$link = $this->link('Company:detailStock', ['id' => $offer->getCompanyStock()->getId()]);
				$ret = '<a href="' . $link . '">' . Strings::truncate($offer->getCompanyStock()->getName(), 60) . '</a>';

				$address = $offer->getCompanyStock()->getAddress();

				return $ret
					. '<br>'
					. '<small>'
					. $address->getStreet() . ', '
					. $address->getCity() . ', '
					. $address->getZipCode()
					. '</small>';

			})
			->setTemplateEscaping(false);

		$grid->addColumnText('cart', 'Vozík')
			->setRenderer(static function (Offer $offer): string {
				$ret = '<small>';

				$count = 0;
				$ids = [];
				foreach ($offer->getItems() as $item) {
					if ($item instanceof OfferItemTableCart) {
						$cart = $item->getCart();

						if (!in_array($cart->getId(), $ids, true)) {
							$count++;
							$ids[] = $cart->getId();
							$ret .= $cart->getName(true) . '<br>';

							if ($count >= 3) {
								return '<small class="text-warning">Více vozíků</small>';
							}
						}
					}
				}

				return $ret . '</small>';
			})
			->setTemplateEscaping(false)
			->setFitContent(true);

		$grid->addColumnText('date', 'Vytvořeno')
			->setRenderer(static function (Offer $offer): string {
				return $offer->getCreatedDate()->format('d.m.Y') . '<br><small>' . $offer->getUserCreated()->getName() . '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('accept', 'Schválení')
			->setRenderer(function (Offer $offer): string {
				if ($offer->isSubmitted() === false) {
					return '<span class="text-warning">Editace</span>';
				}

				$ret = '';
				$link = $this->link('Offer:show', ['id' => $offer->getId()]);

				if ($offer->getAcceptStatus() === OfferStatus::DENIED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
				} elseif ($offer->getAcceptStatus() === OfferStatus::WAITING_FOR_ACCEPT) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
				} elseif ($offer->getAcceptStatus() === OfferStatus::ACCEPTED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
				}

				return $ret;
			})
			->setAlign('center')
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(function (Offer $offer) {
				$link = $this->link('Offer:show', ['id' => $offer->getId()]);

				return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
			});

		//filtr

		//Datum
		$grid->addFilterDateRange('date', 'Datum:');

		//Cislo nabídky
		$grid->addFilterText('number', 'Číslo:');

		//Vytvořil
		$offerUserList = [
			'' => 'Vše',
		];
		foreach ($this->userManager->get()->getUsersByRole('servisni-koordinator') as $user) {
			$offerUserList[$user->getId()] = $user->getName();
		}
		$grid->addFilterSelect('userCreated', 'Vytvořil:', $offerUserList);

		//Stav
		$statusList = [
			'' => 'Vše',
			'send' => 'Odeslané',
			'customer_accepted' => 'Přijaté',
			'customer_denied' => 'Odmítnuté',
			'edit' => 'Rozpracované',
			'accepted' => 'Schválené',
			'notAccepted' => 'Neschválené',
			'denied' => 'Zamítnuté',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(static function (QueryBuilder $qb, string $status): QueryBuilder {
				if ($status === 'send') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::SEND);
				} elseif ($status === 'customer_accepted') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::CUSTOMER_ACCEPTED);
				} elseif ($status === 'customer_denied') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::CUSTOMER_DENIED);
				} elseif ($status === 'edit') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::EDIT);
				} elseif ($status === 'accepted') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::ACCEPTED);
				} elseif ($status === 'notAccepted') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::WAITING_FOR_ACCEPT);
				} elseif ($status === 'denied') {
					$qb->andWhere('offer.status = :status1')
						->setParameter('status1', OfferStatus::DENIED);
				}

				return $qb;
			});

		$grid->setOuterFilterRendering();

		return $grid;
	}

	/**
	 * @param string $name
	 * @return DataGrid
	 * @throws CurrencyException
	 * @throws DataGridException
	 */
	public function createComponentOrderTable(string $name): DataGrid
	{
		$defaultCurrency = $this->currencyManager->getDefaultCurrency();

		$grid = new DataGrid($this, $name);

		$grid->setDataSource(
			$this->entityManager->getRepository(Order::class)
				->createQueryBuilder('o')
				->select('o')
				->andWhere('o.company = :company')
				->setParameter('company', $this->editedCompany->getId())
				->orderBy('o.createdDate', 'DESC')
		);

		$grid->setTemplateFile(__DIR__ . '/datagrid/tableTemplate.latte');

		$grid->setRowCallback(static function (Order $order, Html $row): void {
			$status = $order->getStatus();

			if ($status === OrderStatus::DENIED) {
				$row->addClass('table-danger');

				return;
			}

			if ($status === OrderStatus::EDIT) {
				$row->addClass('table-info');

				return;
			}
		});

		$grid->addColumnText('number', 'Číslo')
			->setRenderer(function (Order $order): string {
				$link = $this->link('Order:show', ['id' => $order->getId()]);

				return '<a href="' . $link . '">' . $order->getNumber() . '</a>'
					. '<br>'
					. '<small class="'
					. OrderStatus::getColorByStatus($order->getStatus())
					. '">'
					. OrderStatus::getNameByStatus($order->getStatus())
					. '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('company', 'Firma')
			->setRenderer(function (Order $order): string {
				if ($order->getCompany() !== null) {
					$link = $this->link('Company:detail', ['id' => $order->getCompany()->getId()]);
					$ret = '<a href="' . $link . '">' . Strings::truncate($order->getCompany()->getName(), 60) . '</a>';

					$address = $order->getCompany()->getInvoiceAddress();

					return $ret
						. '<br>'
						. '<small>'
						. $address->getStreet() . ', '
						. $address->getCity() . ', '
						. $address->getZipCode()
						. '</small>';
				}
				return '<span class="text-orange">' . $order->getCompanyName() . '</span>'
					. '<br>'
					. '<small>'
					. $order->getCompanyAddress() . ', '
					. $order->getCompanyCity() . ', '
					. $order->getCompanyZipCode()
					. '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('companyStock', 'Pobočka')
			->setRenderer(function (Order $order): string {
				if ($order->getCompanyStock() !== null) {
					$link = $this->link('Company:detailStock', ['id' => $order->getCompanyStock()->getId()]);
					$ret = '<a href="' . $link . '">' . Strings::truncate($order->getCompanyStock()->getName(), 60) . '</a>';

					$address = $order->getCompanyStock()->getAddress();

					return $ret
						. '<br>'
						. '<small>'
						. $address->getStreet() . ', '
						. $address->getCity() . ', '
						. $address->getZipCode()
						. '</small>';
				}

				return '&nbsp;<br><small>&nbsp;</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('cart', 'Vozík')
			->setRenderer(static function (Order $order): string {
				$ret = '<small>';

				$count = 0;
				$ids = [];

				foreach ($order->getItems() as $item) {
					if ($item instanceof OrderItemTableCart) {
						$cart = $item->getCart();

						if (!in_array($cart->getId(), $ids, true)) {
							$count++;
							$ids[] = $cart->getId();
							$ret .= $cart->getName(true) . '<br>';

							if ($count >= 3) {
								return '<small class="text-warning">Více vozíků</small>';
							}
						}
					}
				}

				return $ret . '</small>';
			})
			->setTemplateEscaping(false)
			->setFitContent(true);

		$grid->addColumnText('date', 'Vytvořeno')
			->setRenderer(static function (Order $order): string {
				return $order->getCreatedDate()->format('d.m.Y') . '<br><small>' . $order->getUserCreated()->getName() . '</small>';
			})
			->setTemplateEscaping(false);

		$grid->addColumnText('totalPrice', 'Částka')
			->setRenderer(static function (Order $order) use ($defaultCurrency): string {
				return '<b>' . Number::formatPrice($order->getTotalPrice(), $order->getCurrency(), 2) . '</b>'
					. '<br><small>' . Number::formatPrice($order->getTotalPriceCZK(), $defaultCurrency, 2) . '</small>';
			})
			->setAlign('right')
			->setTemplateEscaping(false);

		$grid->addColumnText('accept', 'Schválení')
			->setRenderer(function (Order $order): string {
				if ($order->isSubmitted() === false) {
					return '<span class="text-warning">Editace</span>';
				}

				$ret = '';
				$link = $this->link('Order:show', ['id' => $order->getId()]);

				if ($order->getAcceptStatus() === OrderStatus::DENIED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-danger">
								<i class="fas fa-times fa-fw text-white"></i>
							</a>';
				} elseif ($order->getAcceptStatus() === OrderStatus::WAITING_FOR_ACCEPT) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-warning">
								<i class="fas fa-clock fa-fw text-white"></i>
							</a>';
				} elseif ($order->getAcceptStatus() === OrderStatus::ACCEPTED) {
					$ret .= '<a href="' . $link . '" class="btn btn-xs btn-success">
								<i class="fas fa-check fa-fw text-white"></i>
							</a>';
				}

				return $ret;
			})
			->setAlign('center')
			->setTemplateEscaping(false);

		$grid->addAction('detail', 'Detail')
			->setRenderer(function (Order $order) {
				$link = $this->link('Order:show', ['id' => $order->getId()]);

				return '<a class="btn btn-info btn-xs" href="' . $link . '">
							<i class="fas fa-eye fa-fw"></i>
						</a>';
			});

		//filtr

		//Datum
		$grid->addFilterDateRange('createdDate', 'Datum:');

		//Cislo nabídky
		$grid->addFilterText('number', 'Číslo:');

		//Vytvořil
		$orderUserList = [
			'' => 'Vše',
		];
		foreach ($this->userManager->get()->getUsersByRole('servisni-koordinator') as $user) {
			$orderUserList[$user->getId()] = $user->getName();
		}
		$grid->addFilterSelect('userCreated', 'Vytvořil:', $orderUserList);

		//Stav
		$statusList = [
			'' => 'Vše',
			'send' => 'Odeslané',
			'customer_accepted' => 'Přijaté',
			'customer_denied' => 'Odmítnuté',
			'edit' => 'Rozpracované',
			'accepted' => 'Schválené',
			'notAccepted' => 'Neschválené',
			'denied' => 'Zamítnuté',
		];
		$grid->addFilterSelect('status', 'Stav:', $statusList, 'status')
			->setCondition(static function (QueryBuilder $qb, string $status): QueryBuilder {
				if ($status === 'send') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::SEND);
				} elseif ($status === 'customer_accepted') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::CUSTOMER_ACCEPTED);
				} elseif ($status === 'customer_denied') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::CUSTOMER_DENIED);
				} elseif ($status === 'edit') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::EDIT);
				} elseif ($status === 'accepted') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::ACCEPTED);
				} elseif ($status === 'notAccepted') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::WAITING_FOR_ACCEPT);
				} elseif ($status === 'denied') {
					$qb->andWhere('o.status = :status1')
						->setParameter('status1', OrderStatus::DENIED);
				}

				return $qb;
			});

		$grid->setOuterFilterRendering();

		return $grid;
	}

	/**
	 * @throws AbortException
	 */
	public function handleDeleteCart(): void
	{
		if ($this->editedCart === null) {
			$this->flashMessage('Neznámý vozík.', 'error');
			$this->redirect('default');
		}

		try {
			$this->entityManager->remove($this->editedCart)->flush();
			$this->flashMessage('Vozík byl odstraněn z databáze.', 'info');
			$this->redirect('detailStock', ['id' => $this->editedStock->getId()]);
		} catch (EntityManagerException $e) {
			$this->flashMessage('Při mazání z databáze nastala chyba.', 'error');
			$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
		}
	}

	/**
	 * @param string $operationType
	 */
	public function handleQuickAddOperation(string $operationType): void
	{
		try {
			$operation = $this->cartServiceManager->getOperationByCode($operationType);

			$co = new CartServiceOperation($this->editedCartService, $operation->getName());
			$co->setOperation($operation);
			$co->setPrice($operation->getPrice());
			$co->setPosition(count($this->editedCartService->getOperations()) + 1);

			$this->entityManager->persist($co);
			$this->editedCartService->addOperation($co);

			$this->entityManager->flush([$this->editedCartService, $co]);
		} catch (NoResultException | NonUniqueResultException $e) {
			Debugger::log($e);
			$this->payload->toastr = [
				'type' => 'danger',
				'msg' => 'Požadovaný servis nebyl nalezen.',
			];
		} catch (EntityManagerException $e) {
			Debugger::log($e);
			$this->payload->toastr = [
				'type' => 'danger',
				'msg' => 'Při ukládání do databáze nastala chyba.',
			];
		}

		$this->redrawControl('operationList');
		$this->redrawControl('operationQuickBtn');
	}

	/**
	 * @return Form
	 */
	public function createComponentCartServiceEmailForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addEmail('email', 'Email');

		$form->addSubmit('submit', 'Save');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			$email = $values->email === '' ? null : $values->email;

			if ($email === null) {
				$email = $this->cartServiceManager->sendCartServiceEmail($this->editedCartService);
				if ($email === true) {
					$this->flashMessage('Servisní zpráva byla odeslána emailem.', 'info');
				}
			} elseif ($this->cartServiceManager->sendEmail($this->editedCartService, [$email])) {
				$this->flashMessage('Servisní zpráva byla odeslána emailem na ' . $email . '.', 'info');
			} else {
				$this->flashMessage('Servisní zprávu se nepodařilo odeslat.', 'warning');
			}

			$this->redirect('Company:detailCartService', ['id' => $this->editedCartService->getId(), 'ret' => $this->returnButton]);
		};

		return $form;
	}

	public function createComponentCartPriceReportForm(): Form
	{
		$form = $this->formFactory->create();

		$date = DateTime::from('NOW');
		$date->modify('-1 month');

		$form->addText('year', 'Rok')
			->setDefaultValue($date->format('Y'))
			->setRequired('Zadejte rok')
			->addRule(Form::PATTERN, 'Špatný formát roku.', '^\d{4}$');

		$months = [
			'01' => 'leden',
			'02' => 'únor',
			'03' => 'březen',
			'04' => 'duben',
			'05' => 'květen',
			'06' => 'červen',
			'07' => 'červenec',
			'08' => 'srpen',
			'09' => 'září',
			'10' => 'říjen',
			'11' => 'listopad',
			'12' => 'prosinec',
		];

		$form->addSelect('month', 'Měsíc', $months)
			->setDefaultValue($date->format('m'));

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			$dateStart = DateTime::from($values->year . '-' . $values->month . '-01');
			$dateStop = $dateStart->modifyClone('+1 month');

			$this->pdfExportManager->cartPriceReport($this->editedCompany, $dateStart, $dateStop);
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentMoveCartForm(): Form
	{
		$form = $this->formFactory->create();

		$companyList = [];

		foreach ($this->companyManager->getCompanies() as $company) {
			$companyList[$company->getId()] = $company->getName();
		}

		$form->addSelect('company', 'Firma', $companyList)
			->setPrompt('Vyberte...')
			->setRequired('Vyberte firmu');

		$form->addSelect('companyStock', 'Pobočka', [])
			->setPrompt('Nejdříve vyberte firmu')
			->setRequired('Vyberte pobočku');

		$form->addSubmit('submit', 'Přesunout');

		/**
		 * @param Form $form
		 */
		$form->onAnchor[] = function (Form $form): void {
			if ($form['company']->getValue()) {
				try {
					$company = $this->companyManager->getCompanyById($form['company']->getValue());

					$companyStocks = [];
					foreach ($company->getStocks() as $stock) {
						$companyStocks[$stock->getId()] = $stock->getName();
					}

					$form['companyStock']->setPrompt('Vyberte pobočku')
						->setItems($companyStocks);
				} catch (NoResultException | NonUniqueResultException $e) {
					$form['companyStock']->setPrompt('Nejdříve vyberte firmu')
						->setItems([]);
					$this->flashMessage('Vybranou firmu se nepodařilo načíst.', 'error');
				}
			}
		};

		/**
		 * @param Form $form
		 */
		$form->onError[] = function (Form $form): void {
			foreach ($form->errors as $error) {
				$this->flashMessage($error, 'error');
			}
		};

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values) {
			try {
				$stock = $this->companyManager->getCompanyStockById($values->companyStock);

				$this->editedCart->setCompanyStock($stock);

				$this->entityManager->flush($this->editedCart);
				$this->flashMessage('Vozík byl úspěšně přesunut!', 'success');

				$form->reset();
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->flashMessage('Požadovaná pobočka nebyla nalezena!', 'error');
			} catch (EntityManagerException $e) {
				$this->flashMessage('Při ukládání nastala chyba!', 'error');
			}

			$this->redirect('detailCart', ['id' => $this->editedCart->getId()]);
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentPriceItemAddForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Položka')
			->setRequired('Zadejte název položky')
			->setHtmlId('inputName');

		$form->addText('code', 'Kód')
			->setHtmlId('inputCode');

		$brands = $this->brandManager->getBrandsForForm();

		$form->addSelect('brand', 'Značka', $brands)
			->setPrompt('Neomezovat');

		$types = [];

		$form->addSelect('cartType', 'Typ', $types)
			->setPrompt('Neomezovat');

		$motors = $this->cartManager->getCartMotorsForForm();

		$form->addSelect('motor', 'Motor', $motors)
			->setPrompt('Neomezovat');

		$form->addText('priceFront', 'Cena (čelní)')
			->setHtmlId('inputPriceFront');
		$form->addText('priceRetrak', 'Cena (retrak)')
			->setHtmlId('inputPriceRetrak');
		$form->addText('priceFork', 'Cena (vysokozdvih)')
			->setHtmlId('inputPriceFork');
		$form->addText('priceLow', 'Cena (nízkozdvih)')
			->setHtmlId('inputPriceLow');
		$form->addText('pricePallet', 'Cena (paleťák)')
			->setHtmlId('inputPricePallet');
		$form->addText('priceSpecial', 'Cena (special)')
			->setHtmlId('inputPriceSpecial');

		$form->addSubmit('submit', 'Save');

		$form->onAnchor[] = function (Form $form): void {
			if ($form['brand']->getValue()) {
				try {
					$brand = $this->brandManager->getById($form['brand']->getValue());

					$cartTypes = [];
					foreach ($brand->getCartTypes() as $cartType) {
						$cartTypes[$cartType->getId()] = $cartType->getName();
					}

					$form['cartType']->setPrompt('Neomezovat')
						->setItems($cartTypes);
				} catch (NoResultException | NonUniqueResultException $e) {
					$form['cartType']->setPrompt('Neomezovat')
						->setItems([]);
					$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
				}
			}
		};

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			$priceListItem = new CompanyPriceListItem($this->editedCompany, $values->name);
			$priceListItem->setCode($values->code === '' ? null : $values->code);
			$priceListItem->setPriceFront($values->priceFront === '' ? null : (float) str_replace(',', '.', $values->priceFront));
			$priceListItem->setPriceRetrak($values->priceRetrak === '' ? null : (float) str_replace(',', '.', $values->priceRetrak));
			$priceListItem->setPriceForkLift($values->priceFork === '' ? null : (float) str_replace(',', '.', $values->priceFork));
			$priceListItem->setPriceLowLift($values->priceLow === '' ? null : (float) str_replace(',', '.', $values->priceLow));
			$priceListItem->setPricePalletForkLift($values->pricePallet === '' ? null : (float) str_replace(',', '.', $values->pricePallet));
			$priceListItem->setPriceSpecial($values->priceSpecial === '' ? null : (float) str_replace(',', '.', $values->priceSpecial));

			//motor
			try {
				if ($values->motor !== null) {
					$priceListItem->setMotor(
						$this->cartManager->getCartMotorById($values->motor)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$priceListItem->setMotor(null);
			}

			//brand
			try {
				if ($values->brand !== null) {
					$priceListItem->setBrand(
						$this->brandManager->getById($values->brand)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$priceListItem->setBrand(null);
			}

			//cartType
			try {
				if ($values->cartType !== null) {
					$priceListItem->setCartType(
						$this->cartManager->getCartTypeById($values->cartType)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$priceListItem->setCartType(null);
			}

			$this->entityManager->persist($priceListItem)->flush($priceListItem);

			$this->flashMessage('Položka byla úspěšně přidána do ceníku.', 'success');
			$this->redirect('priceList', ['id' => $this->editedCompany->getId()]);
		};

		return $form;
	}

	/**
	 * @return Form
	 */
	public function createComponentPriceItemEditForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addText('name', 'Položka')
			->setRequired('Zadejte název položky')
			->setDefaultValue($this->editedPriceListItem->getDescription())
			->setHtmlId('inputName');

		$form->addText('code', 'Kód')
			->setDefaultValue($this->editedPriceListItem->getCode())
			->setHtmlId('inputCode');

		$brands = $this->brandManager->getBrandsForForm();

		$form->addSelect('brand', 'Značka', $brands)
			->setDefaultValue($this->editedPriceListItem->getBrand() === null ? null : $this->editedPriceListItem->getBrand()->getId())
			->setPrompt('Neomezovat');

		$types = [];
		if ($this->editedPriceListItem->getBrand() !== null) {
			foreach ($this->editedPriceListItem->getBrand()->getCartTypes() as $cartType) {
				$types[$cartType->getId()] = $cartType->getName();
			}
		}

		$form->addSelect('cartType', 'Typ', $types)
			->setDefaultValue($this->editedPriceListItem->getCartType() === null ? null : $this->editedPriceListItem->getCartType()->getId())
			->setPrompt('Neomezovat');

		$motors = $this->cartManager->getCartMotorsForForm();

		$form->addSelect('motor', 'Motor', $motors)
			->setDefaultValue($this->editedPriceListItem->getMotor() === null ? null : $this->editedPriceListItem->getMotor()->getId())
			->setPrompt('Neomezovat');

		$form->addText('priceFront', 'Cena (čelní)')
			->setDefaultValue($this->editedPriceListItem->getPriceFront())
			->setHtmlId('inputPriceFront');
		$form->addText('priceRetrak', 'Cena (retrak)')
			->setDefaultValue($this->editedPriceListItem->getPriceRetrak())
			->setHtmlId('inputPriceRetrak');
		$form->addText('priceFork', 'Cena (vysokozdvih)')
			->setDefaultValue($this->editedPriceListItem->getPriceForkLift())
			->setHtmlId('inputPriceFork');
		$form->addText('priceLow', 'Cena (nízkozdvih)')
			->setDefaultValue($this->editedPriceListItem->getPriceLowLift())
			->setHtmlId('inputPriceLow');
		$form->addText('pricePallet', 'Cena (paleťák)')
			->setDefaultValue($this->editedPriceListItem->getPricePalletForkLift())
			->setHtmlId('inputPricePallet');
		$form->addText('priceSpecial', 'Cena (special)')
			->setDefaultValue($this->editedPriceListItem->getPricePalletForkLift())
			->setHtmlId('inputPriceSpecial');

		$form->addSubmit('submit', 'Save');

		$form->onAnchor[] = function (Form $form): void {
			if ($form['brand']->getValue()) {
				try {
					$brand = $this->brandManager->getById($form['brand']->getValue());

					$cartTypes = [];
					foreach ($brand->getCartTypes() as $cartType) {
						$cartTypes[$cartType->getId()] = $cartType->getName();
					}

					$form['cartType']->setPrompt('Neomezovat')
						->setItems($cartTypes);
				} catch (NoResultException | NonUniqueResultException $e) {
					$form['cartType']->setPrompt('Neomezovat')
						->setItems([]);
					$this->flashMessage('Vybranou značku se nepodařilo načíst.', 'error');
				}
			}
		};

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {
			$this->editedPriceListItem->setDescription($values->name);
			$this->editedPriceListItem->setCode($values->code === '' ? null : $values->code);
			$this->editedPriceListItem->setPriceFront($values->priceFront === '' ? null : (float) str_replace(',', '.', $values->priceFront));
			$this->editedPriceListItem->setPriceRetrak($values->priceRetrak === '' ? null : (float) str_replace(',', '.', $values->priceRetrak));
			$this->editedPriceListItem->setPriceForkLift($values->priceFork === '' ? null : (float) str_replace(',', '.', $values->priceFork));
			$this->editedPriceListItem->setPriceLowLift($values->priceLow === '' ? null : (float) str_replace(',', '.', $values->priceLow));
			$this->editedPriceListItem->setPricePalletForkLift($values->pricePallet === '' ? null : (float) str_replace(',', '.', $values->pricePallet));
			$this->editedPriceListItem->setPriceSpecial($values->priceSpecial === '' ? null : (float) str_replace(',', '.', $values->priceSpecial));

			//motor
			try {
				if ($values->motor !== null) {
					$this->editedPriceListItem->setMotor(
						$this->cartManager->getCartMotorById($values->motor)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->editedPriceListItem->setMotor(null);
			}

			//brand
			try {
				if ($values->brand !== null) {
					$this->editedPriceListItem->setBrand(
						$this->brandManager->getById($values->brand)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->editedPriceListItem->setBrand(null);
			}

			//cartType
			try {
				if ($values->cartType !== null) {
					$this->editedPriceListItem->setCartType(
						$this->cartManager->getCartTypeById($values->cartType)
					);
				}
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->editedPriceListItem->setCartType(null);
			}

			$this->entityManager->persist($this->editedPriceListItem)->flush($this->editedPriceListItem);

			$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
			$this->redirect('priceList', ['id' => $this->editedCompany->getId()]);
		};

		return $form;
	}

	/**
	 * @param string $itemId
	 * @throws AbortException
	 */
	public function handleDeletePriceListItem(string $itemId): void
	{
		try {
			$item = $this->companyManager->getPriceListItemById($itemId);
			$id = $item->getCompany()->getId();
			$this->entityManager->remove($item)->flush();

			$this->flashMessage('Položka byla smazána.', 'info');
			$this->redirect('priceList', ['id' => $id]);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Požadovaná položka neexistuje.', 'warning');
			$this->redirect('default');
		} catch (EntityManagerException $e) {
			$this->flashMessage('Položku se nepodařilo vymazat z databáze.', 'error');
			$this->redirect('default');
		}
	}

	/**
	 * @return Form
	 */
	public function createComponentCreateCartDocumentForm(): Form
	{
		$form = $this->formFactory->create();

		$documentList = $this->documentManager->getPurchaseDocumentsByCart($this->editedCart);
		$selectData = [];
		foreach ($documentList as $document) {
			$selectData[$document->getId()] = $document->getNumber();
		}

		$form->addSelect('purchaseDocument', 'Kupní smlouva', $selectData)
			->setCaption('Vyberte..')
			->setRequired('Vyberte kupní smlouvu!');

		$form->addInteger('serviceMonthIntervalFirst', 'serviceMonthIntervalFirst')
			->setRequired('Zadajte interval servisu')
			->setDefaultValue(2);

		$form->addInteger('serviceMonthInterval', 'serviceMonthInterval')
			->setRequired('Zadajte interval servisu')
			->setDefaultValue(3);

		$form->addInteger('serviceMotoHoursIntervalFirst', 'serviceMotoHoursIntervalFirst')
			->setRequired('Zadajte interval servisu')
			->setDefaultValue(150);

		$form->addInteger('serviceMotoHoursInterval', 'serviceMotoHoursInterval')
			->setRequired('Zadajte interval servisu')
			->setDefaultValue(300);


		$form->addSubmit('submit', 'Přidat');

		/**
		 * @param Form $form
		 * @param ArrayHash $values
		 */
		$form->onSuccess[] = function (Form $form, ArrayHash $values): void {

			/** @var FakeIdentity|null $user */
			$user = $this->getUser()->getIdentity();

			if ($user instanceof FakeIdentity) {
				$user = $user->getUser();
			}

			if ($user === null || !$user instanceof User) {
				$this->flashMessage('Neznámá identita uživatele!', 'error');
				$this->redirect('detailCartDocument', ['id' => $this->editedCart->getId()]);
			}

			try {
				$purchaseDocument = $this->documentManager->getDocumentById($values->purchaseDocument);
			} catch (NoResultException | NonUniqueResultException $e) {
				$this->flashMessage('Požadovaná kupní smlouva neexistuje!', 'error');
				$this->redirect('detailCartDocument', ['id' => $this->editedCart->getId()]);
			}

			if (!$purchaseDocument instanceof PurchaseContractDocument) {
				$this->flashMessage('Dokument není typu "Kupní smlouva"!', 'error');
				$this->redirect('detailCartDocument', ['id' => $this->editedCart->getId()]);
			}

			try {
				$document = $this->documentManager->getCartDocumentByCartAndPurchaseDocument($this->editedCart, $purchaseDocument);

				$link = $this->link('Documents:show', ['id' => $document->getId(), 'ret' => 2]);

				$this->flashMessage(
					'Dokument k tomuto vozíku a kupní smlouvě již existuje!<br><small><a href="' . $link . '">Document č.: ' . $document->getNumber() . '</a></small>'
					, 'error');
				$this->redirect('detailCartDocument', ['id' => $this->editedCart->getId()]);
			} catch (NoResultException | NonUniqueFieldNameException $e) {
				$number = $this->documentManager->getNextDocumentNumber(CartDocument::class, $user);
				$document = new CartDocument($number, $this->editedCart, $purchaseDocument);
				$document->setCreateUser($user);
				$document->setSubmitted(true);
				$document->setAcceptStatus(DocumentStatus::ACCEPT_SKIPPED);

				//intervaly
				$document->setServiceMonthIntervalFirst((int) $values->serviceMonthIntervalFirst);
				$document->setServiceMonthInterval((int) $values->serviceMonthInterval);
				$document->setServiceMotoHoursIntervalFirst((int) $values->serviceMotoHoursIntervalFirst);
				$document->setServiceMotoHoursInterval((int) $values->serviceMotoHoursInterval);

				//motohodiny
				$document->setCartMotorHours($this->editedCart->getMotoHours());

				$this->entityManager->persist($document);

				$history = new DocumentHistory($document, 'Vytvoření dokumentu');
				$history->setUser($user);

				$this->entityManager->persist($history);

				$document->addHistory($history);

				$this->entityManager->flush();

				$this->flashMessage('Dokument byl úspěšně vytvořen!', 'success');
				$this->redirect('Documents:show', ['id' => $document->getId(), 'ret' => 2]);
			}
		};

		return $form;
	}
}