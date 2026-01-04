<?php

namespace App\Controller;

use App\Controller\Dto\InvoiceDateRangeRequest;
use App\Entity\Invoice;
use App\Entity\InvoiceItemPiece;
use App\Entity\Item;
use App\Entity\ItemPieces;
use App\Entity\MarketplaceInvoices;
use App\Entity\MarketplaceInvoiceItemPieces;
use App\Entity\StorageInvoices;
use App\Entity\StorageInvoiceItemPieces;
use App\Form\Type\InvoiceDateRangeType;
use App\Repository\MarketplaceInvoiceItemPieceRepository;
use App\Repository\MarketplaceInvoiceRepository;
use App\Repository\MarketplaceRepository;
use App\Repository\StorageInvoiceItemPieceRepository;
use App\Repository\StorageInvoiceRepository;
use App\Repository\StorageRepository;
use App\Service\InvoiceEmailService;
use App\Service\InvoicePdfGenerator;
use App\Service\ItemTariffService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @OA\Tag(name="invoice")
 *
 * @Route("/api/invoice")
 */
class InvoiceController extends AbstractApiController
{
    private MarketplaceRepository $marketplaces;
    private MarketplaceInvoiceRepository $marketplaceInvoices;
    private MarketplaceInvoiceItemPieceRepository $marketplaceInvoiceItemPieces;


    private StorageRepository $storages;
    private StorageInvoiceRepository $storageInvoices;
    private StorageInvoiceItemPieceRepository $storageInvoiceItemPieces;

    private InvoicePdfGenerator $pdfGenerator;

    public function __construct(
        MarketplaceRepository $marketplaces,
        MarketplaceInvoiceRepository $marketplaceInvoices,
        MarketplaceInvoiceItemPieceRepository $marketplaceInvoiceItemPieces,

        StorageRepository $storages,
        StorageInvoiceRepository $storageInvoices,
        StorageInvoiceItemPieceRepository $storageInvoiceItemPieces,

        InvoicePdfGenerator $pdfGenerator
    ) {
        $this->marketplaces = $marketplaces;
        $this->marketplaceInvoices = $marketplaceInvoices;
        $this->marketplaceInvoiceItemPieces = $marketplaceInvoiceItemPieces;

        $this->storages = $storages;
        $this->storageInvoices = $storageInvoices;
        $this->storageInvoiceItemPieces = $storageInvoiceItemPieces;

        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Generate marketplace invoices
     *
     * @OA\RequestBody(
     *      @OA\MediaType(
     *         mediaType="application/json",
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(
     *                 property="dateFrom",
     *                 type="string",
     *                 nullable=true,
     *                 format="date",
     *                 pattern="YYYY-MM-DD",
     *                 example="2021-01-01"
     *             ),
     *             @OA\Property(
     *                 property="dateTill",
     *                 type="string",
     *                 nullable=true,
     *                 format="date",
     *                 pattern="YYYY-MM-DD",
     *                 example="2021-01-01"
     *             ),
     *             @OA\Property(
     *                 property="monthlyRange",
     *                 type="string",
     *                 nullable=true,
     *                 format="date",
     *                 pattern="MMMM-YYYY",
     *                 example="June 2025"
     *             )
     *        )
     *    )
     * )
     *
     * @Route("/generate/marketplaces", name="generate_marketplaces_invoices", methods={"POST"})
     */
    public function generateMarketplacesAction(Request $request): Response
    {
        $requestDto = new InvoiceDateRangeRequest();
        $form = $this->buildForm(InvoiceDateRangeType::class, $requestDto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid())
            return $this->respond($form, Response::HTTP_BAD_REQUEST);

        $marketplaces = $this->marketplaces->findBy(["type" => "default"]);

        if (!$marketplaces) {
            return $this->respond(['message' => 'No marketplaces found.'], Response::HTTP_NOT_FOUND);
        }

        $this->configPdfGeneratorForMarketplace();
        
        $objectManager = $this->getDoctrine()->getManager();

        $invoices = new ArrayCollection();
        
        try {
            foreach ($marketplaces as $marketplace) {

                self::deleteAllInvoices(
                    $marketplace->invoices(),
                    $objectManager,
                    $this->pdfGenerator,
                    $requestDto->dateFrom,
                    $requestDto->dateTill
                );

                $invoice = new MarketplaceInvoices($marketplace, $requestDto->dateFrom, $requestDto->dateTill);
                
                $invoiceDidCreate = $this->iterateItems(
                    $requestDto,
                    $invoice,
                    $marketplace->getItems(), // @TODO add leftJoin item_pieces
                    fn($piece, $hours) => new MarketplaceInvoiceItemPieces($invoice, $piece, $hours)
                );
                
                if ($invoiceDidCreate) {
                    $invoices->add($invoice);
                }
            }
            
            $objectManager->flush();
            
        } catch(Throwable $e) {
            return $this->respond($e, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->configPdfGeneratorForMarketplace();
        
        foreach ($invoices as $invoice) {
            try {
                $this->pdfGenerator->generateInvoice($invoice, 'pdf', true);
            } catch (Throwable $e) {
                return $this->respond($e, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return $this->respond(['message' => 'OK']);
    }
    
    #[Route('/generate/storages', name: 'generate_storages_invoices', methods: "POST")]
    public function generateStorages(Request $request): Response
    {
        $requestDto = new InvoiceDateRangeRequest();
        $form = $this->buildForm(InvoiceDateRangeType::class, $requestDto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid())
            return $this->respond($form, Response::HTTP_BAD_REQUEST);

        $storages = $this->storages->findAll();

        if (!$storages)
            return $this->respond(['message' => 'No storages found.'], Response::HTTP_NOT_FOUND);

        $this->configPdfGeneratorForStorage();
        
        $objectManager = $this->getDoctrine()->getManager();

        $invoices = new ArrayCollection();
        
        try {
            foreach ($storages as $storage) {
                self::deleteAllInvoices(
                    $storage->invoices(),
                    $objectManager, 
                    $this->pdfGenerator, 
                    $requestDto->dateFrom, 
                    $requestDto->dateTill
                );
                
                $invoice = new StorageInvoices($storage, $requestDto->dateFrom, $requestDto->dateTill);

                $invoiceDidCreate = $this->iterateItems(
                    $requestDto, 
                    $invoice,
                    $storage->getItems(), // @TODO add leftJoin item_pieces
                    fn($piece, $hours) => new StorageInvoiceItemPieces($invoice, $piece, $hours)
                );

                if ($invoiceDidCreate) {
                    $invoices->add($invoice);
                }
            }
            
            $objectManager->flush();
            
        } catch(Throwable $e) {
            return $this->respond($e, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $this->configPdfGeneratorForStorage();

        foreach ($invoices as $invoice) {
            try {
                $this->pdfGenerator->generateInvoice($invoice, 'pdf', true);
            } catch (Throwable $e) {
                return $this->respond($e, Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        return $this->respond(['message' => 'OK']);
    }
    
    /**
     * @param callable(ItemPieces, int): InvoiceItemPiece $callback
     * @throws Exception
     */
    private function iterateItems(InvoiceDateRangeRequest $requestDto,
                                  Invoice                 $invoice,
                                  Collection              $items,
                                  callable                $callback): bool
    {
        $invoiceIsPersist = false;
        
        /** @var Item $item */
        foreach ($items as $item) {
            $pieces = $item->pieces();

            /** @var ItemPieces $piece */
            foreach ($pieces as $piece) {

                if (!$piece->getReceivedDate())
                    continue;

                if ($piece->getSoldDate())
                    if ($piece->getSoldDate() < $piece->getReceivedDate())
                        throw new Exception("getSoldDate is less then getReceivedDate");

                $hours = $piece->getStorageHours($requestDto->dateFrom, $requestDto->dateTill);

                if ($hours == -1)
                    continue;

                $price = ItemTariffService::calculateStorageCost($item, $requestDto->dateFrom, $hours);

                /**@var $invoiceItemPiece InvoiceItemPiece**/
                $invoiceItemPiece = $callback($piece, $hours);
                $invoiceItemPiece->setAmount($price);

                $manager = $this->getDoctrine()
                    ->getManager();
                
                if (!$invoiceIsPersist) {
                    $manager->persist($invoice);
                    $invoiceIsPersist = true;
                }
                
                $manager->persist($invoiceItemPiece);
            }
        }
        
        return $invoiceIsPersist;
    }


    #[Route('/marketplace/{id}/{format}', name: 'marketplace_invoice_pdf')]
    public function marketplaceGeneratePdf(Request $request, string $id, string $format): Response
    {
        $invoice = $this->marketplaceInvoices->findOneBy(['id' => $id]);

        if (!$invoice) {
            throw $this->createNotFoundException('Invoice not found');
        }

        $regenerate = (bool)$request->get('regenerate', false);

        $this->configPdfGeneratorForMarketplace();

        return $this->pdfGenerator->generateInvoice($invoice, $format, $regenerate);
    }

    #[Route('/storage/{id}/{format}', name: 'storage_invoice_pdf')]
    public function storageGeneratePdf(Request $request, string $id, string $format): Response
    {
        $regenerate = (bool)$request->get('regenerate', false);

        $invoice = $this->storageInvoices->findOneBy(['id' => $id]);

        if (!$invoice)
            throw $this->createNotFoundException('Invoice not found');

        $this->configPdfGeneratorForStorage();
        
        return $this->pdfGenerator->generateInvoice($invoice, $format, $regenerate);
    }

    public function deleteAllInvoices(Collection $invoices,
                                      EntityManagerInterface $manager,
                                      InvoicePdfGenerator          $invoicePdfGenerator,
                                      \DateTimeInterface           $dateFrom,
                                      \DateTimeInterface           $dateTill,
                                      bool                         $physicalRemove = true): void
    {
        $didRemove = false;
        foreach ($invoices as $invoice) {
            /** @var Invoice $invoice */
            if ($invoice->dateFrom() == $dateFrom && $invoice->dateTill() == $dateTill) {
                if ($physicalRemove) {
                    $invoicePdfGenerator->deleteInvoicePdfFile($invoice, false);
                    $manager->remove($invoice);
                } else {
                    $invoice->markDeleted();
                    $manager->persist($invoice);
                }
                $didRemove = true;
            }
        }

        if ($didRemove) {
//            $manager->flush();
        }
    }

    /**
     * @return void
     */
    public function configPdfGeneratorForMarketplace(): void
    {
        $this->pdfGenerator
            ->setItemRepo($this->marketplaceInvoiceItemPieces)
            ->setPdfTemplate('invoice/marketplace.pdf.html.twig');
    }

    /**
     * @return void
     */
    public function configPdfGeneratorForStorage(): void
    {
        $this->pdfGenerator
            ->setItemRepo($this->storageInvoiceItemPieces)
            ->setPdfTemplate('invoice/storage.pdf.html.twig');
    }

    #[Route('/sending/marketplaces', name: 'send_invoices_to_marketplaces', methods: ['POST'])]
    function sendInvoicesToMarketplaces(
        Request $request,
        InvoiceEmailService $emailService,
    ): Response
    {
        $requestDto = new InvoiceDateRangeRequest();
        $form = $this->buildForm(InvoiceDateRangeType::class, $requestDto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid())
            return $this->respond($form, Response::HTTP_BAD_REQUEST);

        $emailService->setInvoiceRepo($this->marketplaceInvoices);
        
        try {
            $sentCount = $emailService->sendInvoicesForMonth($requestDto);
            return $this->json([
                'message' => 'OK',
                'sent_count' => $sentCount,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to send emails',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/sending/storages', name: 'send_invoices_to_storages', methods: ['POST'])]
    function sendInvoicesToStorages(
        Request $request,
        InvoiceEmailService $emailService,
    ): Response
    {
        $requestDto = new InvoiceDateRangeRequest();
        $form = $this->buildForm(InvoiceDateRangeType::class, $requestDto);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid())
            return $this->respond($form, Response::HTTP_BAD_REQUEST);

        $emailService->setInvoiceRepo($this->storageInvoices);

        try {
            $sentCount = $emailService->sendInvoicesForMonth($requestDto);
            return $this->json([
                'message' => 'OK',
                'sent_count' => $sentCount,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to send emails',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
