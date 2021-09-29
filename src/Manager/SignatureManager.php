<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use MatiCore\User\BaseUser;
use Nette\Utils\FileSystem;

class SignatureManager
{
	private string $wwwDir;


	public function __construct(string $wwwDir)
	{
		$this->wwwDir = $wwwDir;
	}


	public function getSignature(BaseUser $user): ?string
	{
		if ($this->hasSignature($user)) {
			return $this->wwwDir . '/img/signatures/' . $user->getId() . '.png';
		}

		return null;
	}


	public function getSignatureLink(BaseUser $user): ?string
	{
		if ($this->hasSignature($user)) {
			return '/img/signatures/' . $user->getId() . '.png';
		}

		return null;
	}


	public function setSignature(BaseUser $user, string $file): void
	{
		FileSystem::copy($file, $this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
	}


	public function removeSignature(BaseUser $user): void
	{
		if ($this->hasSignature($user)) {
			unlink($this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
		}
	}


	public function hasSignature(BaseUser $user): bool
	{
		return is_file($this->wwwDir . '/img/signatures/' . $user->getId() . '.png');
	}
}
