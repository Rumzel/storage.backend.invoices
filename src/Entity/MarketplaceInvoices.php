<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MarketplaceInvoiceRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MarketplaceInvoiceRepository::class)
 */
final class MarketplaceInvoices extends Invoice
{

    /**
     * @ORM\ManyToOne(targetEntity="Marketplace")
     * @ORM\JoinColumn(name="marketplace_id", referencedColumnName="incremental_id")
     */
    private Marketplace $marketplace;

    public function __construct(
        Marketplace $marketplace,
        DateTime $dateFrom,
        DateTime $dateTill
    ) {
        $this->marketplace = $marketplace;
        parent::__construct($dateFrom, $dateTill);
    }

    public function marketplace(): Marketplace
    {
        return $this->marketplace;
    }
    
    public function payer(): InvoicePayerInterface
    {
        return $this->marketplace();
    }
}
