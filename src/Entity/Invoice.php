<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * @ORM\MappedSuperclass
 */
abstract class Invoice
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer", name="incremental_id")
     */
    private int $incrementalId;

    /**
     * @ORM\Column(type="uuid")
     */
    private Uuid $id;


    /**
     * @ORM\Column(type="datetime", name="date_from")
     */
    private DateTime $dateFrom;

    /**
     * @ORM\Column(type="datetime", name="date_till")
     */
    private DateTime $dateTill;

    /**
     * @ORM\Column(type="datetime", options={"default"="CURRENT_TIMESTAMP"})
     */
    private DateTime $createdAt;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    private ?string $pdfPath;

    /**
     * @ORM\Column(type="datetime", name="deleted_at", nullable=true)
     */
    private ?DateTime $deletedAt;

    public function __construct(DateTime $dateFrom, DateTime $dateTill) 
    {
        $this->dateFrom = $dateFrom;
        $this->dateTill = $dateTill;
        $this->id = Uuid::v4();
        $this->createdAt = new DateTime();
        $this->pdfPath = null;
    }

    public function incrementalId(): int
    {
        return $this->incrementalId;
    }

    public function dateFrom(): DateTime
    {
        return $this->dateFrom;
    }

    public function dateTill(): DateTime
    {
        return $this->dateTill;
    }

    public function deletedAt(): DateTime
    {
        return $this->deletedAt;
    }

    public function pdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): void
    {
        $this->pdfPath = $pdfPath;
    }

    public function markDeleted(): void
    {
        $this->deletedAt = new DateTime();
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    
    abstract public function payer(): InvoicePayerInterface;
}