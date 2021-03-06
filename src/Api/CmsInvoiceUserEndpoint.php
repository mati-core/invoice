<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;


use Baraja\StructuredApi\Attributes\PublicEndpoint;
use Baraja\StructuredApi\BaseEndpoint;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use MatiCore\Invoice\SignatureManager;
use Nette\Application\AbortException;
use Nette\Application\UI\Form;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;

#[PublicEndpoint]
class CmsInvoiceUserEndpoint extends BaseEndpoint
{
	private ?int $editedUser = null;


	public function __construct(
		private SignatureManager $signatureManager,
	) {
	}


	public function actionDefault(): void
	{
		$this->template->users = $this->userManager->get()->getAllUsers();
	}


	public function actionUpload(string $id): void
	{
		try {
			$this->editedUser = $this->userManager->get()->getUserById($id);
		} catch (NoResultException | NonUniqueResultException $e) {
			$this->flashMessage('Uživatel neexistuje.', 'error');
			$this->redirect('default');
		}
	}


	public function createComponentUploadForm(): Form
	{
		$form = $this->formFactory->create();

		$form->addUpload('signature', 'Podpis')
			->addRule(Form::MIME_TYPE, 'Podpis musí být typu PNG.', ['image/png']);

		$form->addSubmit('submit', 'Save');

		$form->onSuccess[] = function (Form $form, ArrayHash $values): void
		{
			try {
				/** @var FileUpload $upload */
				$upload = $values->signature;

				if ($upload->isOk()) {
					$this->signatureManager->setSignature($this->editedUser, $upload->getTemporaryFile());
				} else {
					$this->signatureManager->removeSignature($this->editedUser);
				}

				$this->flashMessage('Změny byly úspěšně uloženy.', 'success');
			} catch (\Exception $e) {
				$this->flashMessage('Chyba: ' . $e->getMessage(), 'error');
			}

			$this->redirect('default');
		};

		return $form;
	}


	public function hasSignature(int $user): bool
	{
		return $this->signatureManager->hasSignature($user);
	}
}
