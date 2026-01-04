<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\InvoiceItemPiece;
use App\Enum\SpecType;
use App\Repository\InvoiceItemPieceRepositoryInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Snappy\Pdf as SnappyPdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class InvoicePdfGenerator
{
    private SnappyPdf $snappy;
    private ParameterBagInterface $params;
    private InvoiceItemPieceRepositoryInterface $itemRepo;
    private TwigEnvironment $twig;
    private EntityManagerInterface $entityManager;

    private string $publicPath;
    
    private string $relativeDir = 'uploads/invoices';
    
    private string $uploadDir;
    
    protected ?string $pdfTemplate = null;
    
    private int $vatPercentage = 21;

    public function setItemRepo(InvoiceItemPieceRepositoryInterface $itemRepo): InvoicePdfGenerator
    {
        $this->itemRepo = $itemRepo;
        return $this;
    }

    public function setPdfTemplate(string $pdfTemplate): InvoicePdfGenerator
    {
        $this->pdfTemplate = $pdfTemplate;
        return $this;
    }
    
    

    public function __construct(
        SnappyPdf $snappy,
        ParameterBagInterface $params,
        TwigEnvironment $twig,
        EntityManagerInterface $entityManager
    ) {
        $this->snappy = $snappy;
        $this->params = $params;
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        
        $this->publicPath = $this->params->get('kernel.project_dir') . '/public';
        
        $this->uploadDir = $this->publicPath . '/' . $this->relativeDir;
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * @param Invoice $invoice
     * @param $format
     * @param bool $regenerate
     * @return Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generateInvoice(Invoice $invoice, $format, bool $regenerate = false): Response
    {
        if ($format == 'pdf') {

            $fullPath = $this->generatePdfIfNotExists($invoice, $regenerate);

            return new Response(
                file_get_contents($fullPath),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . basename($fullPath) . '"',
                ]
            );
        } else {
            $vars = $this->getPdfVars($invoice);
            $html = $this->twig->render($this->pdfTemplate, $vars);
            return new Response($html, 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]);
        }
    }

    /**
     * @param Invoice $invoice
     * @param bool $regenerate
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws FileNotFoundException
     */
    private function generatePdfIfNotExists(Invoice $invoice, bool $regenerate = false): string
    {
        if ($regenerate) {
            $this->deleteInvoicePdfFile($invoice);
        }

        if (!$invoice->pdfPath()) {
            $this->generatePdf($invoice);
        }

        $fullPath = $this->publicPath . $invoice->pdfPath();

        if (!file_exists($fullPath)) {
            throw new FileNotFoundException('PDF file not found: ' . $fullPath);
        }
        
        return $fullPath;
    }

    /**
     * @param Invoice $invoice
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function generatePdf(Invoice $invoice): void
    {
        $vars = $this->getPdfVars($invoice);

        $html = $this->twig->render($this->pdfTemplate, $vars);

        $filename = $this->getFilename($invoice);

        $path = $this->uploadDir . '/' . $filename;

        $this->snappy->generateFromHtml($html, $path, [], true);

        $invoice->setPdfPath("/" .$this->relativeDir. "/" . $filename);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();
    }
    
    public function deleteInvoicePdfFile(Invoice $invoice, $flash = true): void
    {
        $pdfPath = $invoice->pdfPath();
        
        if (!$pdfPath) {
            $filename = $this->getFilename($invoice);
            $pdfPath = "/" .$this->relativeDir. "/" . $filename;
        }
        
        if ($pdfPath) {
            $fullPath = $this->publicPath . $pdfPath;
            if ( file_exists($fullPath) && is_file($fullPath) ) {
                unlink($fullPath);
                $invoice->setPdfPath(null);
                $this->entityManager->persist($invoice);
                if ($flash)
                    $this->entityManager->flush();
            }
        }
    }

    /**
     * @param Invoice $invoice
     * @return array
     */
    private function getPdfVars(Invoice $invoice): array
    {
        $items = $this->itemRepo->findItemPiecesByInvoice($invoice);

        list($grouped, $summary) = $this->groupByEntityAndItems($items);
        
        return [
            'invoice' => $invoice,
            'grouped' => $grouped,
            'summary' => $summary,
            'payer' => $invoice->payer()->tplVars(),
            'recipient' => $this->recipientInfo()
        ];
    }

    /**
     * @param Collection<int, InvoiceItemPiece> $itemPieces
     * @return array
     */
    private function groupByEntityAndItems(Collection $itemPieces): array
    {
        $grouped = [];
        
        $summary = [
            'pieces' => 0,
            'hours' => 0,
            'amount' => 0,
        ];

        foreach ($itemPieces as $itemPieceInvoice) {
            $itemPiece = $itemPieceInvoice->getItemPiece();
            $item = $itemPiece->getItem();

            $tariff = ItemTariffService::getTariffCategory($item);

            $entityId =  $itemPieceInvoice->getEntityIdentifier();
            $entityTitle = $itemPieceInvoice->getEntityTitle();

            $itemId = $item->getUuid();
            $itemTitle = $item->getTitle();
            $volume = $item->calculateVolume();
            list('weight' => $weight) = $item->extractPhysicalSpecs();
            
            $pictures = $item->getPictures();
            $firstPicture = !$pictures->isEmpty() ? $pictures->first()->getPath() : null;

            // Init array
            if (!isset($grouped[$entityId])) {
                $grouped[$entityId] = [
                    'id' => $entityId,
                    'title' => $entityTitle,
                    'total_pieces' => 0,
                    'total_hours' => 0,
                    'total_amount' => 0,
                    'items' => [],
                ];
            }

            if (!isset($grouped[$entityId]['items'][$itemId])) {
                $grouped[$entityId]['items'][$itemId] = [
                    'title' => $itemTitle,
                    'id' => $itemId,
                    'volume' => $volume,
                    'weight' => $weight,
                    'picture' => $firstPicture,
                    'tariff' => $tariff,
                    'total_hours' => 0,
                    'total_amount' => 0,
                    'pieces' => [],
                ];
            }

            // Add ItemPiece
            $hours = $itemPieceInvoice->getHours();
            $amount = $itemPieceInvoice->getAmount() ?? 0;

            $grouped[$entityId]['items'][$itemId]['pieces'][] = [
                'id' => $itemPiece->getIncrementalId(),
                'hours' => $hours,
                'amount' => $amount,
            ];

            // Summary aggregation
            $grouped[$entityId]['items'][$itemId]['total_hours'] += $hours;
            $grouped[$entityId]['items'][$itemId]['total_amount'] += $amount;

            $grouped[$entityId]['total_pieces']++;
            $grouped[$entityId]['total_hours'] += $hours;
            $grouped[$entityId]['total_amount'] += $amount;
            
            $summary['pieces']++;
            $summary['hours'] += $hours;
            $summary['amount'] += $amount;
            
        }

        $summary['tax'] = $this->vatPercentage;
        $summary['amountTax'] = $summary['amount'] / 100 * $this->vatPercentage;
        $summary['amountWithTax'] = $summary['amount'] + $summary['amountTax'];

        return [$grouped, $summary];
    }

    /**
     * @return array
     */
    private function recipientInfo(): array
    {
        return [
            'name' => "QWQER EU SIA",
            'subName' => null,
            'infoLines' => [
                //address line 1 + 2
                ['Vienības gatve 109, Rīga, Latvija, LV-1058'], 
                [
                    'Reģ. nr.: 40103636656',
                    'PVN nr.: LV40103636656'
                ],
                [
                    'IBAN: LV98HABA0551051042951',
                    'BANK:  Swedbank AS',
                    'SWIFT: HABALV22',
                    'Country: Latvija'
                ]
            ]
        ];
    }

    /**
     * @param Invoice $invoice
     * @return string
     */
    private function getFilename(Invoice $invoice): string
    {
        return sprintf('invoice_%s.pdf', $invoice->getId());
    }
}