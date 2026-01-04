<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\MarketplaceInvoiceItemPieces;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MarketplaceInvoiceItemPieces|null find($id, $lockMode = null, $lockVersion = null)
 * @method MarketplaceInvoiceItemPieces|null findOneBy(array $criteria, array $orderBy = null)
 * @method MarketplaceInvoiceItemPieces[]    findAll()
 * @method MarketplaceInvoiceItemPieces[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MarketplaceInvoiceItemPieceRepository extends ServiceEntityRepository implements InvoiceItemPieceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceInvoiceItemPieces::class);
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return parent::getEntityManager();
    }

    /**
     * @return Collection<int,MarketplaceInvoiceItemPieces>
     */
    public function findItemPiecesByInvoice(Invoice $invoice): Collection
    {
        $query = $this->createQueryBuilder('miip')
            ->select('miip')
            ->leftJoin('miip.itemPiece','ip')
            ->leftJoin('ip.item','i')
            ->leftJoin('i.storage','s')
            ->leftJoin('s.pictures', 'sp')
            ->leftJoin('i.specs', 'ispecs')
            ->leftJoin('i.pictures', 'ipictures')
            ->addSelect( 'ip', 'i', 's', 'sp', 'ispecs', 'ipictures')
            ->where('miip.marketplaceInvoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->orderBy('miip.incrementalId', 'ASC');
        
        return new ArrayCollection($query->getQuery()->getResult());
    }
}
