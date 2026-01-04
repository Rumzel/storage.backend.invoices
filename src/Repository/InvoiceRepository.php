<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @method Invoice|null find($id, $lockMode = null, $lockVersion = null)
 * @method Invoice|null findOneBy(array $criteria, array $orderBy = null)
 * @method Invoice[]    findAll()
 * @method Invoice[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
abstract class InvoiceRepository extends ServiceEntityRepository
{
    public function add(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }

    public function update(Invoice $invoice): void
    {
        $this->getEntityManager()->persist($invoice);
        $this->getEntityManager()->flush();
    }
    
    /**
     * @param \DateTimeInterface $dateFrom
     * @param \DateTimeInterface $dateTill
     * @return Invoice[]
     */
    public function findByDateRange(\DateTimeInterface $dateFrom, \DateTimeInterface $dateTill): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.dateFrom >= :dateFrom')
            ->andWhere('i.dateTill <= :dateTill')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTill', $dateTill)
            ->getQuery()
            ->getResult();
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return parent::getEntityManager();
    }
}