<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use MatiCore\User\BaseUser;
use Nette\Utils\FileSystem;

/**
 * Class SignatureManager
 * @package MatiCore\Invoice
 */
class SignatureManager
{

	/**
	 * @var string
	 */
	private string $wwwDir;

	/**
	 * SignatureManager constructor.
	 * @param string $wwwDir
	 */
	public function __construct(string $wwwDir)
	{
		$this->wwwDir = $wwwDir;
	}

	/**
	 * @param BaseUser $user
	 * @return string|null
	 */
	public function getSignature(BaseUser $user): ?string
	{
		if($this->hasSignature($user)){
			return $this->wwwDir . '/img/signatures/' . $user->getId() . '.png';
		}

		return null;
	}

	/**
	 * @param BaseUser $user
	 * @return string|null
	 */
	public function getSignatureLink(BaseUser $user): ?string
	{
		if($this->hasSignature($user)){
			return '/img/signatures/' . $user->getId() . '.png';
		}

		return null;
	}

	/**
	 * @param BaseUser $user
	 * @param string $file
	 */
	public function setSignature(BaseUser $user, string $file): void
	{
		FileSystem::copy($file, $this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
	}

	/**
	 * @param BaseUser $user
	 */
	public function removeSignature(BaseUser $user): void
	{
		if ($this->hasSignature($user)) {
			unlink($this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
		}
	}

	/**
	 * @param BaseUser $user
	 * @return bool
	 */
	public function hasSignature(BaseUser $user): bool
	{
		return is_file($this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
	}

}