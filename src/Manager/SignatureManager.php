<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Nette\Utils\FileSystem;

class SignatureManager
{
	public function __construct(
		private string $wwwDir,
	) {
	}


	public function getSignature(int $user): ?string
	{
		if ($this->hasSignature($user)) {
			return $this->wwwDir . '/img/signatures/' . $user . '.png';
		}

		return null;
	}


	public function getSignatureLink(int $user): ?string
	{
		if ($this->hasSignature($user)) {
			return '/img/signatures/' . $user . '.png';
		}

		return null;
	}


	public function setSignature(int $user, string $file): void
	{
		FileSystem::copy($file, $this->wwwDir . '/img/signatures/' . $user . '.png');
	}


	public function removeSignature(int $user): void
	{
		if ($this->hasSignature($user)) {
			unlink($this->wwwDir . '/img/signatures/' . $user . '.png');
		}
	}


	public function hasSignature(int $user): bool
	{
		return is_file($this->wwwDir . '/img/signatures/' . $user . '.png');
	}
}
