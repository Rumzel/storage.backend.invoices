<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class InvoiceItemPiece
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     * @ORM\Column(type="integer", name="incremental_id")
     */
    private int $incrementalId;
    

    /**
     * @ORM\ManyToOne(targetEntity="ItemPieces")
     * @ORM\JoinColumn(name="item_piece_id", referencedColumnName="incremental_id")
     */
    private ItemPieces $itemPiece;

    /**
     * @ORM\Column(type="integer")
     */
    private int $hours;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private int $amount;

    public function __construct(ItemPieces $itemPiece, int $hours)
    {
        $this->itemPiece = $itemPiece;
        $this->hours = $hours;
    }

    public function incrementalId(): int
    {
        return $this->incrementalId;
    }

    public function getItemPiece(): ItemPieces
    {
        return $this->itemPiece;
    }

    public function getHours(): int
    {
        return $this->hours;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getIncrementalId(): int
    {
        return $this->incrementalId;
    }
    
    abstract function getEntityIdentifier();
    abstract function getEntityTitle();
}