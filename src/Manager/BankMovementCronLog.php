<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Nette\Utils\FileSystem;

class BankMovementCronLog
{
	public function __construct(
		private string $logDir,
	) {
	}


	public function setLog(bool $status): void
	{
		FileSystem::write(
			$this->logDir . '/bankMovementCron.log',
			(string) json_encode(
				[
					'date' => date('d.m.Y H:i:s'),
					'status' => $status,
				],
				JSON_THROW_ON_ERROR
			)
		);
	}


	/**
	 * @return array{date: string|null, status: bool|null}
	 */
	public function getLog(): array
	{
		if (!is_file($this->logDir . '/bankMovementCron.log')) {
			return [
				'date' => null,
				'status' => null,
			];
		}

		$data = FileSystem::read($this->logDir . '/bankMovementCron.log');

		return (array) json_decode($data, true, 512, JSON_THROW_ON_ERROR);
	}
}
