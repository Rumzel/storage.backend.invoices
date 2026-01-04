<?php

namespace App\Controller\Dto;

use DateTimeInterface;
use DateTime;
use Exception;

class InvoiceDateRangeRequest
{
    public ?DateTime $dateFrom = null;

    public ?DateTime $dateTill = null;

    public ?string $monthlyRange = null;

    /**
     * @return void
     * @throws Exception
     */
    function dateRangeFromMonthlyRange(): void
    {
        if ($this->monthlyRange) {
            list(
                'dateFrom' => $this->dateFrom,
                'dateTill' => $this->dateTill
                ) = $this->getDateRangeByMonthYear($this->monthlyRange);
            $this->monthlyRange = null;
        }
    }


    /**
     * Converts a human-readable month-year string (e.g., "January 2025") into a date range.
     *
     * @param string $monthYear Format: "January 2025", "March 2024", etc.
     * @return array{start: DateTimeInterface, end: DateTimeInterface}
     * @throws Exception if invalid format
     */
    function getDateRangeByMonthYear(string $monthYear): array
    {
        $date = DateTime::createFromFormat('F Y', $monthYear);

        if (!$date) {
            throw new Exception(sprintf('Invalid month-year format: "%s". Expected like "January 2025".', $monthYear));
        }

        $start = clone $date;
        $start->setDate((int)$date->format('Y'), (int)$date->format('n'), 1); // 1st day of month
        $start->setTime(0, 0);

        $end = clone $start;
        $end->modify('last day of this month');
        $end->setTime(23, 59, 59);

        return [
            'dateFrom' => $start,
            'dateTill' => $end,
        ];
    }

    function isDateInRange(DateTimeInterface $date, DateTimeInterface $from, DateTimeInterface $till): bool
    {
        return $date >= $from && $date <= $till;
    }
}

