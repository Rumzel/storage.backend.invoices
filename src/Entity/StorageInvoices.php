<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StorageInvoiceRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=StorageInvoiceRepository::class)
 */
final class StorageInvoices extends Invoice
{

    /**
     * @ORM\ManyToOne(targetEntity="Storage")
     * @ORM\JoinColumn(name="storage_id", referencedColumnName="incremental_id")
     */
    private Storage $storage;

    public function __construct(
        Storage $storage,
        DateTime $dateFrom,
        DateTime $dateTill
    ) {
        $this->storage = $storage;
        parent::__construct($dateFrom, $dateTill);
    }

    public function storage(): Storage
    {
        return $this->storage;
    }

    public function payer(): InvoicePayerInterface
    {
        return $this->storage();
    }
}
