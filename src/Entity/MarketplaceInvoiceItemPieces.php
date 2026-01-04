<?php

declare(strict_types=1);

namespace App\Entity;
use App\Repository\MarketplaceInvoiceItemPieceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MarketplaceInvoiceItemPieceRepository::class)
 */
final class MarketplaceInvoiceItemPieces extends InvoiceItemPiece
{
    /**
     * @ORM\ManyToOne(targetEntity="MarketplaceInvoices")
     * @ORM\JoinColumn(name="marketplace_invoice_id", referencedColumnName="incremental_id")
     */
    private MarketplaceInvoices $marketplaceInvoice;

    public function __construct(MarketplaceInvoices $marketplaceInvoices, ItemPieces $itemPiece, int $hours)
    {
        $this->marketplaceInvoice = $marketplaceInvoices; 
        parent::__construct($itemPiece, $hours);
    }
 
    public function marketplaceInvoice(): MarketplaceInvoices
    {
        return $this->marketplaceInvoice;
    }

    function getEntityIdentifier(): string
    {
        return $this->getStorage()->getUuid();
    }

    function getEntityTitle(): string
    {
        return $this->getStorage()->getTitle();
    }

    /**
     * @return Storage
     */
    public function getStorage(): Storage
    {
        return $this->getItemPiece()
            ->getItem()
            ->getStorage();
    }
}
