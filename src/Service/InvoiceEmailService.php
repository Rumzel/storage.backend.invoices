<?php

namespace App\Service;

use App\Controller\Dto\InvoiceDateRangeRequest;
use App\Mail\InvoiceEmail;
use App\Repository\InvoiceRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\MailerInterface;

class InvoiceEmailService
{
    private InvoiceRepository $invoiceRepo;
    
    public function __construct(
        private MailerInterface $mailer,
        private KernelInterface $kernel,
        private RequestStack $requestStack
    ) {}
    
    /**
     * @throws \Exception
     */
    public function sendInvoicesForMonth(InvoiceDateRangeRequest $request): int
    {
        $invoices = $this->invoiceRepo
            ->findByDateRange($request->dateFrom, $request->dateTill);

        if (empty($invoices)) {
            return 0;
        }

        $sentCount = 0;
        foreach ($invoices as $invoice) {
            
            if (!$invoice->payer()?->getEmail())
                continue;

            $email = new InvoiceEmail(
                $this->kernel,
                $this->requestStack,
                $invoice->payer()->getEmail(),
                $invoice->payer()->getName(),
                $invoice,
                $request->dateFrom
            );

            $this->mailer->send($email);
            $sentCount++;
        }

        return $sentCount;
    }
    public function setInvoiceRepo(InvoiceRepository $invoiceRepo): void
    {
        $this->invoiceRepo = $invoiceRepo;
    }
}