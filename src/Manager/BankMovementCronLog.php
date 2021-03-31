<?php

declare(strict_types=1);


namespace MatiCore\Invoice;


use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

/**
 * Class BankMovementCronLog
 * @package MatiCore\Invoice
 */
class BankMovementCronLog
{

	/**
	 * @var string
	 */
	private string $logDir;

	/**
	 * BankMovementCronLogger constructor.
	 * @param string $logDir
	 */
	public function __construct(string $logDir)
	{
		$this->logDir = $logDir;
	}

	/**
	 * @param bool $status
	 * @throws JsonException
	 */
	public function setLog(bool $status): void
	{
		FileSystem::write($this->logDir . '/bankMovementCron.log', Json::encode([
			'date' => date('d.m.Y H:i:s'),
			'status' => $status,
		]));
	}

	/**
	 * @return array|null
	 * @throws JsonException
	 */
	public function getLog(): ?array
	{
		if (!is_file($this->logDir . '/bankMovementCron.log')) {
			return null;
		}

		$data = FileSystem::read($this->logDir . '/bankMovementCron.log');

		return Json::decode($data, Json::FORCE_ARRAY);
	}

}