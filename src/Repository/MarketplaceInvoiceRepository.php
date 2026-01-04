<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MarketplaceInvoices;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MarketplaceInvoices|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceInvoices|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceInvoices[]    findAll()
 * @method MarketplaceInvoices[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MarketplaceInvoiceRepository extends InvoiceRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceInvoices::class);
    }
}
