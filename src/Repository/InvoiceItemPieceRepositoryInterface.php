<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\InvoiceItemPiece;
use Doctrine\Common\Collections\Collection;

interface InvoiceItemPieceRepositoryInterface
{
    /**
     * @return Collection<int,InvoiceItemPiece>
     */
    public function findItemPiecesByInvoice(Invoice $invoice): Collection;
}