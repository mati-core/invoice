<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Baraja\Plugin\PluginComponentExtension;
use MatiCore\Company\CompanyInvoiceStatisticsControl;
use MatiCore\Company\CompanyManager;
use MatiCore\Company\CompanyManagerAccessor;
use MatiCore\Invoice\Command\InvoiceAlertCommand;
use MatiCore\Invoice\Command\InvoicePayCheckCommand;
use MatiCore\Invoice\Command\InvoiceTaxListFixCommand;
use MatiCore\Supplier\SupplierManager;
use MatiCore\Supplier\SupplierManagerAccessor;
use Nette\DI\CompilerExtension;

final class InvoiceExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		PluginComponentExtension::defineBasicServices($builder);
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'MatiCore\Invoice',
			__DIR__ . '/Entity',
		);
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'MatiCore\Company',
			__DIR__ . '/Entity',
		);
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder,
			'MatiCore\Supplier',
			__DIR__ . '/Entity',
		);

		$builder->addDefinition($this->prefix('invoicePayCheckCommand'))
			->setFactory(InvoicePayCheckCommand::class)
			->setArgument('tempDir', '')
			->setArgument('params', []);

		$builder->addDefinition($this->prefix('invoiceAlertCommand'))
			->setFactory(InvoiceAlertCommand::class)
			->setArgument('tempDir', '')
			->setArgument('params', []);

		$builder->addDefinition($this->prefix('invoiceTaxListFixCommand'))
			->setFactory(InvoiceTaxListFixCommand::class);

		$builder->addDefinition($this->prefix('supplierManager'))
			->setFactory(SupplierManager::class);

		$builder->addAccessorDefinition($this->prefix('supplierManagerAccessor'))
			->setImplement(SupplierManagerAccessor::class);

		$builder->addDefinition($this->prefix('expenseHelper'))
			->setFactory(ExpenseManager::class);

		$builder->addAccessorDefinition($this->prefix('expenseManagerAccessor'))
			->setImplement(ExpenseManagerAccessor::class);

		$builder->addDefinition($this->prefix('invoiceHelper'))
			->setFactory(InvoiceHelper::class);

		$builder->addDefinition($this->prefix('companyManager'))
			->setFactory(CompanyManager::class);

		$builder->addAccessorDefinition($this->prefix('companyManagerAccessor'))
			->setImplement(CompanyManagerAccessor::class);

		$builder->addDefinition($this->prefix('invoiceManager'))
			->setFactory(InvoiceManager::class)
			->setArgument('tempDir', '')
			->setArgument('params', []);

		$builder->addAccessorDefinition($this->prefix('invoiceManagerAccessor'))
			->setImplement(InvoiceManagerAccessor::class);

		$builder->addDefinition($this->prefix('bankMovementManager'))
			->setFactory(BankMovementManager::class);

		$builder->addAccessorDefinition($this->prefix('bankMovementManagerAccessor'))
			->setImplement(BankMovementManagerAccessor::class);

		$builder->addDefinition($this->prefix('bankMovementCronLog'))
			->setFactory(BankMovementCronLog::class)
			->setArgument('logDir', '');

		$builder->addAccessorDefinition($this->prefix('bankMovementCronLogAccessor'))
			->setImplement(BankMovementCronLogAccessor::class);

		$builder->addDefinition($this->prefix('exportManager'))
			->setFactory(ExportManager::class)
			->setArgument('tempDir', '')
			->setArgument('config', '');

		$builder->addAccessorDefinition($this->prefix('exportManagerAccessor'))
			->setImplement(ExportManagerAccessor::class);

		$builder->addDefinition($this->prefix('signatureManager'))
			->setFactory(SignatureManager::class)
			->setArgument('wwwDir', '');

		$builder->addAccessorDefinition($this->prefix('signatureManagerAccessor'))
			->setImplement(SignatureManagerAccessor::class);

		$builder->addDefinition($this->prefix('companyInvoiceStatisticsControl'))
			->setFactory(CompanyInvoiceStatisticsControl::class);
	}
}
