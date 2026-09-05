<?php

namespace App\Support;

use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportTimeFilter
{
    public static function parseDate(?string $date, ?Carbon $fallback = null): Carbon
    {
        $date = trim((string) $date);
        if ($date === '') {
            return ($fallback ?: Carbon::now())->copy();
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd M Y', 'd F Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // Try next known format.
            }
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            return ($fallback ?: Carbon::now())->copy();
        }
    }

    public static function resolve(
        Request $request,
        ?RestaurantSetting $restaurant = null,
        string $defaultType = 'year',
        bool $allowAll = false
    ): array {
        $now = Carbon::now();
        $currentYear = (int) $now->year;

        $aliases = [
            'date' => 'range',
            'date_range' => 'range',
            'from_to' => 'range',
            'daily' => 'day',
            'business' => 'business_day',
        ];

        $requestedType = strtolower(trim((string) $request->query('filter_type', $defaultType)));
        $filterType = $aliases[$requestedType] ?? $requestedType;
        $allowed = ['day', 'month', 'year', 'range', 'hour', 'business_day'];
        if ($allowAll) {
            $allowed[] = 'all';
        }
        if (!in_array($filterType, $allowed, true)) {
            $filterType = in_array($defaultType, $allowed, true) ? $defaultType : 'year';
        }

        $year = (int) $request->query('year', $currentYear);
        if ($year < 2000 || $year > ($currentYear + 5)) {
            $year = $currentYear;
        }
        $month = (int) $request->query('month', $now->month);
        if ($month < 1 || $month > 12) {
            $month = (int) $now->month;
        }

        $reportDate = self::parseDate(
            $request->query('report_date', $request->query('date', $now->format('d-m-Y'))),
            $now
        )->startOfDay();

        $rangeStartInput = $request->query('start_date', $now->copy()->startOfMonth()->format('d-m-Y'));
        $rangeEndInput = $request->query('end_date', $now->format('d-m-Y'));
        $rangeStart = self::parseDate($rangeStartInput, $now)->startOfDay();
        $rangeEnd = self::parseDate($rangeEndInput, $now)->endOfDay();
        if ($rangeStart->gt($rangeEnd)) {
            $tmp = $rangeStart->copy();
            $rangeStart = $rangeEnd->copy()->startOfDay();
            $rangeEnd = $tmp->copy()->endOfDay();
        }

        $startTime = self::normalizeTime((string) $request->query('start_time', '00:00'), '00:00');
        $endTime = self::normalizeTime((string) $request->query('end_time', '23:59'), '23:59');

        $defaultBusinessDate = self::defaultBusinessDate($restaurant, $now);
        $businessDate = self::parseDate(
            $request->query('business_date', $defaultBusinessDate->format('d-m-Y')),
            $defaultBusinessDate
        )->startOfDay();

        switch ($filterType) {
            case 'day':
                $startDate = $reportDate->copy()->startOfDay();
                $endDate = $reportDate->copy()->endOfDay();
                break;

            case 'month':
                $startDate = Carbon::create($year, $month, 1)->startOfMonth()->startOfDay();
                $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
                break;

            case 'range':
                $startDate = $rangeStart;
                $endDate = $rangeEnd;
                break;

            case 'hour':
                $startDate = $reportDate->copy()->setTimeFromTimeString($startTime . ':00');
                $endDate = $reportDate->copy()->setTimeFromTimeString($endTime . ':59');
                if ($endDate->lt($startDate)) {
                    $endDate->addDay();
                }
                break;

            case 'business_day':
                [$startDate, $endDate] = self::businessWindow($businessDate, $restaurant);
                break;

            case 'all':
                // Start/end remain harmless display values. Callers that allow All should skip whereBetween.
                $startDate = Carbon::create(2000, 1, 1)->startOfDay();
                $endDate = $now->copy()->addYear()->endOfYear()->endOfDay();
                break;

            case 'year':
            default:
                $filterType = 'year';
                $startDate = Carbon::create($year, 1, 1)->startOfYear()->startOfDay();
                $endDate = Carbon::create($year, 12, 31)->endOfYear()->endOfDay();
                break;
        }

        return [
            'filterType' => $filterType,
            'year' => $year,
            'month' => $month,
            'reportDate' => $reportDate,
            'businessDate' => $businessDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'paymentMethod' => $request->query('payment_method'),
            'yearOptions' => range($currentYear + 1, $currentYear - 10),
            'filterLabel' => self::label($filterType, $startDate, $endDate, $businessDate, $year, $month),
        ];
    }

    public static function label(
        string $filterType,
        Carbon $startDate,
        Carbon $endDate,
        Carbon $businessDate,
        int $year,
        int $month
    ): string {
        return match ($filterType) {
            'day' => 'Date: ' . $startDate->format('d M Y'),
            'month' => 'Month: ' . Carbon::create($year, $month, 1)->format('F Y'),
            'year' => 'Year: ' . $year,
            'range' => 'Date range: ' . $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
            'hour' => 'Hour range: ' . $startDate->format('d M Y, h:i A') . ' - ' . $endDate->format('d M Y, h:i A'),
            'business_day' => 'Business day ' . $businessDate->format('d M Y') . ': ' . $startDate->format('h:i A') . ' - ' . $endDate->format('d M Y, h:i A'),
            'all' => 'All dates',
            default => $startDate->format('d M Y') . ' - ' . $endDate->format('d M Y'),
        };
    }

    private static function normalizeTime(?string $time, string $fallback): string
    {
        $time = trim((string) $time);
        if ($time === '') {
            return $fallback;
        }

        foreach (['H:i', 'H:i:s', 'h:i A', 'h:iA'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $time);
                if ($parsed !== false) {
                    return $parsed->format('H:i');
                }
            } catch (\Throwable $e) {
                // Try next format.
            }
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private static function defaultBusinessDate(?RestaurantSetting $restaurant, Carbon $now): Carbon
    {
        $opening = self::normalizeTime($restaurant?->opening_time, '00:00');
        $closing = self::normalizeTime($restaurant?->closing_time, '23:59');
        $today = $now->copy()->startOfDay();
        $time = $now->format('H:i');

        if ($opening === $closing) {
            return $time >= $opening ? $today : $today->copy()->subDay();
        }

        if ($opening < $closing) {
            return $time < $opening ? $today->copy()->subDay() : $today;
        }

        return $time >= $opening ? $today : $today->copy()->subDay();
    }

    private static function businessWindow(Carbon $businessDate, ?RestaurantSetting $restaurant): array
    {
        $opening = self::normalizeTime($restaurant?->opening_time, '00:00');
        $closing = self::normalizeTime($restaurant?->closing_time, '23:59');

        $start = $businessDate->copy()->startOfDay()->setTimeFromTimeString($opening . ':00');
        $end = $businessDate->copy()->startOfDay()->setTimeFromTimeString($closing . ':59');

        if ($closing <= $opening) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
