<?php

declare(strict_types=1);

namespace MatiCore\Invoice;


use Baraja\Shop\Currency\CurrencyManager;
use Doctrine\ORM\EntityManager;
use Latte\Engine;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Nette\Application\UI\ITemplateFactory;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\Type\PdfTypeException;
use Tracy\Debugger;

class ExportManager
{
	/**
	 * @param array $config
	 */
	public function __construct(
		private string $tempDir,
		private array $config,
		private EntityManager $entityManager,
		private ITemplateFactory $templateFactory,
		private CurrencyManager $currencyManager
	) {
	}


	/**
	 * @param array<int, Invoice> $invoices
	 */
	public function exportInvoicesToPDF(array $invoices): ?string
	{
		FileSystem::createDir($this->tempDir . '/export');

		if (is_dir($this->tempDir . '/export')) {
			$dir = opendir($this->tempDir . '/export');
			while ($f = readdir($dir)) {
				if ($f !== '.' && $f !== '..') {
					FileSystem::delete($this->tempDir . '/export/' . $f);
				}
			}
		}

		$files = [];
		foreach ($invoices as $invoice) {
			$tmpFile = $this->tempDir . '/export/' . $invoice->getNumber() . '.pdf';
			$this->exportInvoiceToPdf($invoice, Destination::FILE, $tmpFile);
			$files[] = $tmpFile;
		}

		return $this->mergePdf(
			$files,
			Destination::DOWNLOAD,
			$this->config['invoice']['filename'] . date('Ymd_His') . '.pdf'
		);
	}


	public function getExportInvoiceFileName(Invoice $invoice): string
	{
		if ($invoice->isRegular()) {
			$name = $this->config['invoice']['filename'] . $invoice->getNumber() . '.pdf';
		} elseif ($invoice->isFix()) {
			$name = $this->config['fixInvoice']['filename'] . $invoice->getNumber() . '.pdf';
		} elseif ($invoice->isPayDocument()) {
			$name = $this->config['payDocument']['filename'] . $invoice->getNumber() . '.pdf';
		} else {
			$name = $this->config['proforma']['filename'] . $invoice->getNumber() . '.pdf';
		}

		return $name;
	}


	public function exportInvoiceToPdf(
		Invoice $invoice,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		$name = $this->getExportInvoiceFileName($invoice);
		$params = [
			'color' => $this->getColorByInvoiceDocument($invoice),
			'templateData' => $this->getInvoiceTemplateData($invoice),
			'invoice' => $invoice,
			'currency' => $this->currencyManager->getMainCurrency(),
		];
		$pageBreaker = new PdfPageBreaker($invoice->getCurrency(), 23);
		if ($invoice->getCurrency()->getCode() !== 'CZK') {
			$pageBreaker->increase(3);
		} else {
			if ($invoice->getOrderNumber() !== null) {
				$pageBreaker->increase(1);
			}
			if ($invoice->getRentNumber() !== null) {
				$pageBreaker->increase(1);
			}
			if ($invoice->getContractNumber() !== null) {
				$pageBreaker->increase(1);
			}
		}

		$params['pageBreaker'] = $pageBreaker;
		$params['beforeTextPBI'] = $this->getTextPBI($invoice->getTextBeforeItems());
		$params['afterTextPBI'] = $this->getTextPBI($invoice->getTextAfterItems(), 45, 2);

		if ($invoice->isFix() && isset($this->config['fixInvoice']['template'])) {
			$templateFile = $this->config['fixInvoice']['template'];
			$styleFile = $this->config['fixInvoice']['style'] ?? null;
		} elseif ($invoice->isPayDocument() && isset($this->config['payDocument']['template'])) {
			$templateFile = $this->config['payDocument']['template'];
			$styleFile = $this->config['payDocument']['style'] ?? null;
		} elseif ($invoice->isProforma() && isset($this->config['proforma']['template'])) {
			$templateFile = $this->config['proforma']['template'];
			$styleFile = $this->config['proforma']['style'] ?? null;
		} else {
			$templateFile = $this->config['invoice']['template'];
			$styleFile = $this->config['invoice']['style'] ?? null;
		}

		if ($templateFile === null || $templateFile === '') {
			$templateFile = __DIR__ . '/../Templates/Pdf/Invoice/invoice.latte';
		}

		$html = $this->templateFactory->createTemplate()->renderToString($templateFile, $params);

		if ($styleFile === null || $styleFile === '') {
			$styleFile = __DIR__ . '/../Templates/Pdf/Invoice/invoice.css';
		}

		$style = FileSystem::read($styleFile);
		$style = str_replace('__COLOR__', $params['color'], $style);

		$pdf = new Mpdf();
		$pdf->SetAuthor($this->config['author']);
		$pdf->autoPageBreak = false;
		$pdf->WriteHTML($style, HTMLParserMode::HEADER_CSS);
		$pdf->WriteHTML($html);

		if ($destination === Destination::FILE) {
			$pdf->Output($file, Destination::FILE);

			return null;
		}

		return $pdf->Output($name, $destination);
	}


	/**
	 * @param array<int, string> $filePaths
	 * @throws MpdfException
	 */
	public function mergePdf(
		array $filePaths,
		string $destination = Destination::DOWNLOAD,
		string $file = 'attachment.pdf'
	): ?string {
		$mpdf = new Mpdf();
		try {
			$first = true;
			foreach ($filePaths as $importedPdfPath) {
				if (is_file($importedPdfPath)) {
					$pageCount = $mpdf->setSourceFile($importedPdfPath);
					for ($i = 1; $i <= $pageCount; $i++) {
						if ($first) {
							$first = false;
						} else {
							$mpdf->WriteHTML('<pagebreak>');
						}
						$pageId = $mpdf->ImportPage($i);
						$mpdf->UseTemplate($pageId);
					}
				} else {
					throw new MpdfException('Missing imported file: ' . $importedPdfPath);
				}
			}
		} catch (CrossReferenceException | PdfParserException | PdfTypeException $e) {
			Debugger::log($e);
			throw new \RuntimeException('Error merge PDF: ' . $e->getMessage(), $e->getCode(), $e);
		}
		if ($destination === Destination::FILE) {
			$mpdf->Output($file, Destination::FILE);

			return null;
		}

		return $mpdf->Output($file, $destination);
	}


	/**
	 * @throws MpdfException
	 */
	public function exportInvoiceAlertToPDF(
		int $alertNumber,
		Invoice $invoice,
		\DateTime $newDueDate,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		if ($alertNumber === 3) {
			$name = $this->config['alertThree']['filename'] . $invoice->getNumber() . '.pdf';
		} elseif ($alertNumber === 2) {
			$name = $this->config['alertTWoo']['filename'] . $invoice->getNumber() . '.pdf';
		} else {
			$name = $this->config['alertOne']['filename'] . $invoice->getNumber() . '.pdf';
		}

		$templateFile = $this->config['alertOne']['template'];
		if ($templateFile === null || $templateFile === '') {
			if ($alertNumber === 3) {
				$templateFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_three.latte';
			} elseif ($alertNumber === 2) {
				$templateFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_two.latte';
			} else {
				$templateFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_one.latte';
			}
		}

		$styleFile = $this->config['alertOne']['style'];
		if ($styleFile === null || $styleFile === '') {
			if ($alertNumber === 3) {
				$styleFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_three.css';
			} elseif ($alertNumber === 2) {
				$styleFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_two.css';
			} else {
				$styleFile = __DIR__ . '/../Templates/Pdf/InvoiceAlert/invoice_alert_one.css';
			}
		}

		$pdf = new Mpdf();
		$pdf->SetAuthor($this->config['author']);
		$pdf->autoPageBreak = false;
		$pdf->WriteHTML(FileSystem::read($styleFile), HTMLParserMode::HEADER_CSS);
		$pdf->WriteHTML(
			(new Engine)->renderToString(
				$templateFile,
				[
					'invoice' => $invoice,
					'newDueDate' => $newDueDate,
				]
			)
		);

		if ($destination === Destination::FILE) {
			$pdf->Output($file, Destination::FILE);

			return null;
		}

		return $pdf->Output($name, $destination);
	}


	/**
	 * @throws MpdfException
	 */
	public function exportInvoiceAlertOneToPDF(
		Invoice $invoice,
		\DateTime $newDueDate,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		return $this->exportInvoiceAlertToPDF(1, $invoice, $newDueDate, $destination, $file);
	}


	public function exportInvoiceAlertTwoToPDF(
		Invoice $invoice,
		\DateTime $newDueDate,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		return $this->exportInvoiceAlertToPDF(2, $invoice, $newDueDate, $destination, $file);
	}


	public function exportInvoiceAlertThreeToPDF(
		Invoice $invoice,
		\DateTime $newDueDate,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		return $this->exportInvoiceAlertToPDF(3, $invoice, $newDueDate, $destination, $file);
	}


	/**
	 * @param Invoice[] $invoices
	 */
	public function exportInvoiceSummaryToPDF(
		array $invoices,
		string $destination = Destination::DOWNLOAD,
		?string $file = null
	): ?string {
		$name = $this->config['summary']['filename'] . date('Ymd_His') . '.pdf';

		$currency = $this->currencyManager->getDefaultCurrency();
		$data = [];
		$totalPrice = 0.0;
		$totalTax = 0.0;

		foreach ($invoices as $invoice) {
			$data[] = [
				'number' => $invoice->getNumber(),
				'company' => Strings::truncate($invoice->getCustomerName(), 40),
				'ic' => $invoice->getCustomerCin(),
				'date' => $invoice->getDate()->format('d.m.Y'),
				'dateTax' => ($invoice->isProforma() ? '' : $invoice->getTaxDate()->format('d.m.Y')),
				'dueDate' => $invoice->getDueDate()->format('d.m.Y'),
				'payDate' => ($invoice->getPayDate() === null ? '' : $invoice->getPayDate()->format('d.m.Y')),
				'tax' => Number::formatPrice($invoice->getTotalTax() * $invoice->getRate(), $currency, 2),
				'price' => Number::formatPrice($invoice->getTotalPrice(), $invoice->getCurrency(), 2),
				'priceCZK' => Number::formatPrice($invoice->getTotalPrice() * $invoice->getRate(), $currency, 2),
				'late' => $invoice->isLate(),
			];

			$totalTax += ($invoice->getTotalTax() * $invoice->getRate());
			$totalPrice += ($invoice->getTotalPrice() * $invoice->getRate());
		}

		$totalTax = Number::formatPrice($totalTax, $currency, 2);
		$totalPrice = Number::formatPrice($totalPrice, $currency, 2);

		$template = $this->templateFactory->createTemplate();
		$template->data = $data;
		$template->totalTax = $totalTax;
		$template->totalPrice = $totalPrice;
		$template->dateNow = new \DateTime;

		$templateFile = $this->config['summary']['template'];
		if ($templateFile === null || $templateFile === '') {
			$templateFile = __DIR__ . '/../Templates/Pdf/InvoiceSummary/invoiceSummary.latte';
		}

		$template->setFile($templateFile);
		$html = $template->renderToString();

		$styleFile = $this->config['summary']['style'];
		if ($styleFile === null || $styleFile === '') {
			$styleFile = __DIR__ . '/../Templates/Pdf/InvoiceSummary/invoiceSummary.css';
		}

		$style = FileSystem::read($styleFile);

		$pdf = new Mpdf();
		$pdf->SetAuthor($this->config['author']);
		$pdf->autoPageBreak = false;
		$pdf->WriteHTML($style, HTMLParserMode::HEADER_CSS);
		$pdf->WriteHTML($html);

		if ($destination === Destination::FILE) {
			$pdf->Output($file, Destination::FILE);

			return null;
		}

		return $pdf->Output($name, $destination);
	}


	public function exportIntrastatToXLS(\DateTime $date): void
	{
		$date->modify('first day of this month');
		$startDate = new \DateTime($date->format('Y-m-d') . ' 00:00:00');
		$date->modify('+1 month');
		$stopDate = new \DateTime($date->format('Y-m-d') . ' 00:00:00');

		/** @var ExpenseInvoice[] $expenseList */
		$expenseList = $this->entityManager->getRepository(ExpenseInvoice::class)
			->createQueryBuilder('ei')
			->join('ei.supplierCountry', 'country')
			->where('ei.date >= :startDate AND ei.date < :stopDate')
			->andWhere('country.isoCode != :countryCode')
			->setParameter('startDate', $startDate->format('Y-m-d'))
			->setParameter('stopDate', $stopDate->format('Y-m-d'))
			->setParameter('countryCode', 'CZE')
			->orderBy('ei.date', 'ASC')
			->getQuery()
			->getResult();

		$spreadsheet = new Spreadsheet();

		$spreadsheet->getProperties()
			->setCreator($this->config[''])
			->setTitle($this->config['intrastat']['title'] . $startDate->format('Y-m'));

		$sheet = $spreadsheet->getActiveSheet();
		if ($sheet->getColumnDimension('B') !== null) {
			$sheet->getColumnDimension('B')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('C') !== null) {
			$sheet->getColumnDimension('C')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('D') !== null) {
			$sheet->getColumnDimension('D')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('E') !== null) {
			$sheet->getColumnDimension('E')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('F') !== null) {
			$sheet->getColumnDimension('F')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('G') !== null) {
			$sheet->getColumnDimension('G')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('H') !== null) {
			$sheet->getColumnDimension('H')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('I') !== null) {
			$sheet->getColumnDimension('I')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('J') !== null) {
			$sheet->getColumnDimension('J')->setAutoSize(true);
		}
		if ($sheet->getColumnDimension('K') !== null) {
			$sheet->getColumnDimension('K')->setAutoSize(true);
		}

		$sheet->getStyle('B:J')->getAlignment()->setHorizontal('center');

		$spreadsheet->getActiveSheet()->mergeCells('B4:C4');
		$spreadsheet->getActiveSheet()->mergeCells('D4:K4');

		$sheet->getStyle('B4')->getAlignment()->setHorizontal('left');
		$sheet->getStyle('D4')->getAlignment()->setHorizontal('right');
		$sheet->getStyle('F')->getAlignment()->setHorizontal('right');
		$sheet->getStyle('K')->getAlignment()->setHorizontal('right');

		$sheet->setCellValue('B4', Strings::upper(Date::getCzechMonthName($startDate)) . ' ' . $startDate->format('Y'));
		$sheet->setCellValue(
			'D4', 'termín pro podání hlášení je Celní správou stanoven - 12. pracovní den následujícího měsíce'
		);

		$spreadsheet->getActiveSheet()->getStyle('B4')->getFont()->setUnderline(true);
		$spreadsheet->getActiveSheet()->getStyle('B6:K6')->getFont()->setBold(true);

		$sheet->setCellValue('B6', 'POL');
		$sheet->setCellValue('C6', 'STÁT DOD');
		$sheet->setCellValue('D6', 'PŮVOD ZBOŽÍ');
		$sheet->setCellValue('E6', 'DRUH DOPR');
		$sheet->setCellValue('F6', 'ČÁSTKA');
		$sheet->setCellValue('G6', 'HMOTN. KG');
		$sheet->setCellValue('H6', 'KÓD ZBOŽÍ');
		$sheet->setCellValue('I6', 'DODAVATEL');
		$sheet->setCellValue('J6', 'V.S. DOKLADU');
		$sheet->setCellValue('K6', 'DATUM FAKT.');

		$rowId = 6;
		$i = 0;

		foreach ($expenseList as $expense) {
			$rowId++;
			$sheet->setCellValue('B' . $rowId, $i++);
			$sheet->setCellValue('C' . $rowId, $expense->getSupplierCountry()->getIsoCode());
			$sheet->setCellValue('D' . $rowId, $expense->getSupplierCountry()->getIsoCode());
			$sheet->setCellValue(
				'E' . $rowId, $expense->getDeliveryType() === 5 ? '3,4' : (string) $expense->getDeliveryType()
			);
			$sheet->setCellValue(
				'F' . $rowId,
				str_replace('&nbsp;', ' ', Number::formatPrice($expense->getTotalPrice(), $expense->getCurrency()))
			);
			$sheet->setCellValue('G' . $rowId, str_replace('.', ',', (string) $expense->getWeight()));
			$sheet->setCellValue('H' . $rowId, str_replace(' ', '', $expense->getProductCode() ?? ''));
			$sheet->setCellValue('I' . $rowId, $expense->getSupplierName());
			$sheet->setCellValue('J' . $rowId, $expense->getVariableSymbol() ?? '');
			$sheet->setCellValue('K' . $rowId, $expense->getDate()->format('d.m.Y'));
		}

		$spreadsheet->getActiveSheet()
			->getStyle('B6:K' . $rowId)
			->getBorders()
			->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

		$rowId += 2;
		$spreadsheet->getActiveSheet()->mergeCells('B' . $rowId . ':K' . $rowId);
		$sheet->getStyle('B' . $rowId)->getAlignment()->setHorizontal('left');
		$sheet->setCellValue('B' . $rowId, 'Vygenerováno: ' . date('d.m.Y H:i:s'));

		Debugger::enable(Debugger::PRODUCTION);

		ob_clean();

		$writer = new Xlsx($spreadsheet);
		$fileName = $this->config['intrastat']['filename'] . $date->format('Y_m') . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $fileName . '"');
		header('Cache-Control: max-age=0');

		$writer->save('php://output');
		die;
	}


	public function getColorByInvoiceDocument(Invoice $invoice): string
	{
		if ($invoice->isFix() && isset($this->config['fixInvoice']['color'])) {
			return $this->config['fixInvoice']['color'];
		}
		if ($invoice->isPayDocument() && isset($this->config['payDocument']['color'])) {
			return $this->config['payDocument']['color'];
		}
		if ($invoice->isProforma() && isset($this->config['proforma']['color'])) {
			return $this->config['proforma']['color'];
		}

		return $this->config['invoice']['color'] ?? 'rgb(74, 164, 50)';
	}


	public function getFooterEmail(): ?string
	{
		return $this->config['email'] ?? null;
	}


	public function getFooterPhone(): ?string
	{
		return $this->config['phone'] ?? null;
	}


	public function getCompanyDescription(): ?string
	{
		return $this->config['companyDescription'] ?? null;
	}


	public function getDescription(Invoice $invoice): ?string
	{
		if ($invoice->isFix() && isset($this->config['fixInvoice']['description'])) {
			return $this->config['fixInvoice']['description'];
		}
		if ($invoice->isPayDocument() && isset($this->config['payDocument']['description'])) {
			return $this->config['payDocument']['description'];
		}
		if ($invoice->isProforma() && isset($this->config['proforma']['description'])) {
			return $this->config['proforma']['description'];
		}

		return $this->config['invoice']['description'] ?? null;
	}


	public function getAdditionalDescription(Invoice $invoice): ?string
	{
		if ($invoice->isFix() && isset($this->config['fixInvoice']['additionalDescription'])) {
			return $this->config['fixInvoice']['additionalDescription'];
		}
		if ($invoice->isPayDocument() && isset($this->config['payDocument']['additionalDescription'])) {
			return $this->config['payDocument']['additionalDescription'];
		}
		if ($invoice->isProforma() && isset($this->config['proforma']['additionalDescription'])) {
			return $this->config['proforma']['additionalDescription'];
		}

		return $this->config['invoice']['additionalDescription'] ?? null;
	}


	/**
	 * @return array<string, string|null>
	 */
	public function getInvoiceTemplateData(Invoice $invoice): array
	{
		return [
			'companyDescription' => $this->getCompanyDescription(),
			'description' => $this->getDescription($invoice),
			'additionalDescription' => $this->getAdditionalDescription($invoice),
			'footerEmail' => $this->getFooterEmail(),
			'footerPhone' => $this->getFooterPhone(),
		];
	}


	private function getTextPBI(?string $txt, int $charToRow = 70, int $skip = 0, float $lineWeight = 1.5): int
	{
		$index = 0.0;
		if ($txt !== null) {
			$lines = explode("\n", $txt);
			$index += count($lines) / $lineWeight;
			foreach ($lines as $line) {
				$charCount = strlen($line);
				while ($charCount > $charToRow) {
					$index++;
					$charCount -= $charToRow;
				}
			}
		}

		$index -= $skip;
		if ($index < 0) {
			$index = 0;
		}

		return (int) round($index);
	}
}
