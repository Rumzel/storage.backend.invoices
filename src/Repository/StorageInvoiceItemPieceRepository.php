<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\StorageInvoiceItemPieces;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method StorageInvoiceItemPieces|null find($id, $lockMode = null, $lockVersion = null)
 * @method StorageInvoiceItemPieces|null findOneBy(array $criteria, array $orderBy = null)
 * @method StorageInvoiceItemPieces[]    findAll()
 * @method StorageInvoiceItemPieces[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StorageInvoiceItemPieceRepository extends ServiceEntityRepository implements InvoiceItemPieceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StorageInvoiceItemPieces::class);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return parent::getEntityManager();
    }

    /**
     * @return Collection<int,StorageInvoiceItemPieces>
     */
    public function findItemPiecesByInvoice(Invoice $invoice): Collection
    {
        $query = $this->createQueryBuilder('siip')
            ->select('siip')
            ->leftJoin('siip.itemPiece','ip')
            ->leftJoin('ip.item','i')
            ->leftJoin('i.marketplace','im')
            ->leftJoin('i.specs', 'ispecs')
            ->leftJoin('i.pictures', 'ipictures')
            ->addSelect( 'ip', 'i', 'im', 'ispecs', 'ipictures')
            ->where('siip.storageInvoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('siip.incrementalId', 'ASC');
        
        return new ArrayCollection($query->getQuery()->getResult());
    }
}
