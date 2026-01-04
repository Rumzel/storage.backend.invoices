<?php

namespace App\Service;

use App\Entity\Item;
use App\Enum\TariffSeasonType;
use App\Enum\TariffType;
use App\Service\Model\VirtualItem;
use DateTimeInterface;

class ItemTariffService
{
    // for StorageCostController

    public static function calculateStorageCostByMonths(VirtualItem $item, int $months, string $tariffMode = 'auto'): int
    {
        $totalCost = 0.0;

        if ($tariffMode === 'auto') {
            $startDate = new \DateTimeImmutable();
            for ($i = 0; $i < $months; $i++) {
                $currentDate = $startDate->modify("+$i months");
                $rate = self::getMonthlyRate($currentDate, $item);
                $totalCost += $item->calculateTotalVolume() * $rate;
            }
        } else {
            $season = match ($tariffMode) {
                'low' => TariffSeasonType::Low(),
                'peak' => TariffSeasonType::Peak(),
                default => throw new \InvalidArgumentException("Invalid tariff_mode"),
            };

            $category = self::getTariffCategory($item);
            $tariffs = self::getTariffConfig()['tariffs'];
            $monthlyRate = $tariffs[$season->getValue()][$category->getValue()];
            $totalCost = $item->calculateTotalVolume() * $monthlyRate * $months;
        }

        return (int)(round($totalCost, 2) * 100);
    }

    private static function getMonthlyRate(\DateTimeInterface $date, VirtualItem $item): float
    {
        $season = self::getSeasonByDate($date);
        $category = self::getTariffCategory($item);
        $tariffs = self::getTariffConfig()['tariffs'];
        return $tariffs[$season->getValue()][$category->getValue()];
    }
    
    
    // for invoices
    
    public static function calculateStorageCost(Item $item, DateTimeInterface $seasonDate, $hours): int
    {
        $hourlyRate = self::getHourlyRate($seasonDate, $item);

        $totalCost = $item->calculateVolume() * $hours * $hourlyRate;

        return (int)(round($totalCost, 2) * 100);
    }
    
    public static function getTariffCategory(Item|VirtualItem $item) {
        list('oversize_thresholds' => $oversizeThresholds) = self::getTariffConfig();

        list(
            'width' => $width,
            'height' => $height,
            'depth' => $depth,
            'weight' => $weight
            ) = $item->extractPhysicalSpecs();

        $isOversize = $width > $oversizeThresholds['width']
            || $height > $oversizeThresholds['height']
            || $depth > $oversizeThresholds['depth']
            || $weight > $oversizeThresholds['weight'];

        if ($isOversize) {
            return TariffType::Oversize();
        } else {
            return TariffType::Standard();
        }
    }
    
    /**
     * @param DateTimeInterface $seasonDate
     * @param Item $item
     * @return float
     */
    public static function getHourlyRate(DateTimeInterface $seasonDate, Item $item): float
    {
        $tariffs = self::getTariffConfig()['tariffs'];
        $season = self::getSeasonByDate($seasonDate);
        $category = self::getTariffCategory($item);

        return $tariffs[$season->getValue()][$category->getValue()] / 30.44 / 24; // €/м³/час
    }
    
    public static function getSeasonByDate(DateTimeInterface $date): TariffSeasonType
    {
        $month = (int)$date->format('n'); // 1-12
        return in_array($month, [10, 11, 12], true) ?
            TariffSeasonType::Peak() : TariffSeasonType::Low();
    }

    //@TODO Refactoring to file after finish stage
    private static function getTariffConfig(): array
    {
        return [
            'tariffs' => [
                TariffSeasonType::Low()->getValue() => [
                    TariffType::Standard()->getValue() => 16.35, // €/м³/month
                    TariffType::Oversize()->getValue() => 23.92,
                ],
                TariffSeasonType::Peak()->getValue() => [
                    TariffType::Standard()->getValue() => 24.79,
                    TariffType::Oversize()->getValue() => 42.71,
                ],
            ],

            'oversize_thresholds' => [
                'width'  => 0.45,
                'height' => 0.35,
                'depth'  => 0.20,
                'weight' => 9.0,
            ],
        ];
    }
}