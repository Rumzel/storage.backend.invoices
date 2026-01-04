<?php

declare(strict_types=1);

namespace App\Entity;
use App\Repository\StorageInvoiceItemPieceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StorageInvoiceItemPieceRepository::class)
 */
final class StorageInvoiceItemPieces extends InvoiceItemPiece
{
    
    /**
     * @ORM\ManyToOne(targetEntity="StorageInvoices")
     * @ORM\JoinColumn(name="storage_invoice_id", referencedColumnName="incremental_id")
     */
    private StorageInvoices $storageInvoice;
    
    public function __construct(StorageInvoices $storageInvoices, ItemPieces $itemPiece, int $hours)
    {
        $this->storageInvoice = $storageInvoices;
        parent::__construct($itemPiece, $hours);
    }

    public function storageInvoice(): StorageInvoices
    {
        return $this->storageInvoice;
    }

    function getEntityIdentifier(): string
    {
        return $this->getMarketplace()->getIncrementalId() . '';
    }

    function getEntityTitle(): string
    {
        return $this->getMarketplace()->getName();
    }

    /**
     * @return Marketplace
     */
    public function getMarketplace(): Marketplace
    {
        return $this->getItemPiece()
            ->getItem()
            ->getMarketplace();
    }
}
