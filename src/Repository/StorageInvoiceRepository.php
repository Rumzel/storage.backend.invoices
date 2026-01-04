<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StorageInvoices;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StorageInvoices|null find($id, $lockMode = null, $lockVersion = null)
 * @method StorageInvoices|null findOneBy(array $criteria, array $orderBy = null)
 * @method StorageInvoices[]    findAll()
 * @method StorageInvoices[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StorageInvoiceRepository extends InvoiceRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageInvoices::class);
    }
}
