<?php

namespace App\Services;

use Carbon\Carbon;

class PhilippineHolidayService
{
    /**
     * Get all Philippine holidays for a given year.
     *
     * @return array<string, array{name: string, filipino_name: string, type: 'regular' | 'special_non_working', description: string, date: string}>
     */
    public static function getHolidaysForYear(int $year): array
    {
        $holidays = [];

        // Fixed Regular Holidays
        $holidays["{$year}-01-01"] = [
            'date' => "{$year}-01-01",
            'name' => "New Year's Day",
            'filipino_name' => 'Araw ng Bagong Taon',
            'type' => 'regular',
            'description' => 'Regular Holiday · Nationwide celebration of the new year',
        ];

        $holidays["{$year}-04-09"] = [
            'date' => "{$year}-04-09",
            'name' => 'Araw ng Kagitingan (Day of Valor)',
            'filipino_name' => 'Araw ng Kagitingan',
            'type' => 'regular',
            'description' => 'Regular Holiday · Commemoration of the Fall of Bataan and heroism of Filipino soldiers',
        ];

        $holidays["{$year}-05-01"] = [
            'date' => "{$year}-05-01",
            'name' => 'Labor Day',
            'filipino_name' => 'Araw ng Paggawa',
            'type' => 'regular',
            'description' => 'Regular Holiday · Honoring workers and laborers across the Philippines',
        ];

        $holidays["{$year}-06-12"] = [
            'date' => "{$year}-06-12",
            'name' => 'Philippine Independence Day',
            'filipino_name' => 'Araw ng Kasarinlan',
            'type' => 'regular',
            'description' => 'Regular Holiday · Declaration of Philippine Independence in 1898',
        ];

        // National Heroes Day: Last Monday of August
        $lastMondayAug = Carbon::create($year, 8, 31);
        while ($lastMondayAug->dayOfWeek !== Carbon::MONDAY) {
            $lastMondayAug->subDay();
        }
        $holidays[$lastMondayAug->format('Y-m-d')] = [
            'date' => $lastMondayAug->format('Y-m-d'),
            'name' => 'National Heroes Day',
            'filipino_name' => 'Araw ng mga Pambansang Bayani',
            'type' => 'regular',
            'description' => 'Regular Holiday · Commemorating all Philippine national heroes',
        ];

        $holidays["{$year}-11-30"] = [
            'date' => "{$year}-11-30",
            'name' => 'Bonifacio Day',
            'filipino_name' => 'Kaarawan ni Andres Bonifacio',
            'type' => 'regular',
            'description' => 'Regular Holiday · Birth anniversary of Andres Bonifacio',
        ];

        $holidays["{$year}-12-25"] = [
            'date' => "{$year}-12-25",
            'name' => 'Christmas Day',
            'filipino_name' => 'Araw ng Pasko',
            'type' => 'regular',
            'description' => 'Regular Holiday · Nationwide celebration of Christmas',
        ];

        $holidays["{$year}-12-30"] = [
            'date' => "{$year}-12-30",
            'name' => 'Rizal Day',
            'filipino_name' => 'Araw ng Kabayanihan ni Dr. Jose Rizal',
            'type' => 'regular',
            'description' => 'Regular Holiday · Honoring the martyrdom of national hero Dr. Jose Rizal',
        ];

        // Fixed Special Non-Working Days
        $holidays["{$year}-02-25"] = [
            'date' => "{$year}-02-25",
            'name' => 'EDSA People Power Revolution Anniversary',
            'filipino_name' => 'Anibersaryo ng Rebolusyong EDSA',
            'type' => 'special_non_working',
            'description' => 'Special Non-Working Day · Commemoration of the 1986 EDSA Revolution',
        ];

        $holidays["{$year}-08-21"] = [
            'date' => "{$year}-08-21",
            'name' => 'Ninoy Aquino Day',
            'filipino_name' => 'Araw ni Ninoy Aquino',
            'type' => 'special_non_working',
            'description' => 'Special Non-Working Day · Commemorating the assassination of Senator Benigno Aquino Jr.',
        ];

        $holidays["{$year}-11-01"] = [
            'date' => "{$year}-11-01",
            'name' => "All Saints' Day",
            'filipino_name' => 'Undas / Araw ng mga Banal',
            'type' => 'special_non_working',
            'description' => "Special Non-Working Day · Traditional observance of All Saints' Day",
        ];

        $holidays["{$year}-11-02"] = [
            'date' => "{$year}-11-02",
            'name' => "All Souls' Day",
            'filipino_name' => 'Araw ng mga Patay',
            'type' => 'special_non_working',
            'description' => "Special Non-Working Day · Traditional observance of All Souls' Day",
        ];

        $holidays["{$year}-12-08"] = [
            'date' => "{$year}-12-08",
            'name' => 'Feast of the Immaculate Conception',
            'filipino_name' => 'Pista ng Kalinis-linisang Paglilihi kay Maria',
            'type' => 'special_non_working',
            'description' => 'Special Non-Working Day · Principal patroness of the Philippines',
        ];

        $holidays["{$year}-12-24"] = [
            'date' => "{$year}-12-24",
            'name' => 'Christmas Eve',
            'filipino_name' => 'Bisperas ng Pasko',
            'type' => 'special_non_working',
            'description' => 'Special Non-Working Day · Preparations for Christmas celebration',
        ];

        $holidays["{$year}-12-31"] = [
            'date' => "{$year}-12-31",
            'name' => "New Year's Eve / Last Day of the Year",
            'filipino_name' => 'Bisperas ng Bagong Taon',
            'type' => 'special_non_working',
            'description' => "Special Non-Working Day · Year-end holiday",
        ];

        // Holy Week (Easter-based movable holidays)
        $easterDays = easter_days($year);
        $easterSunday = Carbon::create($year, 3, 21)->addDays($easterDays);

        $maundyThursday = $easterSunday->copy()->subDays(3);
        $goodFriday = $easterSunday->copy()->subDays(2);
        $blackSaturday = $easterSunday->copy()->subDays(1);

        $holidays[$maundyThursday->format('Y-m-d')] = [
            'date' => $maundyThursday->format('Y-m-d'),
            'name' => 'Maundy Thursday',
            'filipino_name' => 'Huwebes Santo',
            'type' => 'regular',
            'description' => 'Regular Holiday · Holy Week religious observance',
        ];

        $holidays[$goodFriday->format('Y-m-d')] = [
            'date' => $goodFriday->format('Y-m-d'),
            'name' => 'Good Friday',
            'filipino_name' => 'Biyernes Santo',
            'type' => 'regular',
            'description' => 'Regular Holiday · Holy Week religious observance',
        ];

        $holidays[$blackSaturday->format('Y-m-d')] = [
            'date' => $blackSaturday->format('Y-m-d'),
            'name' => 'Black Saturday',
            'filipino_name' => 'Sabado de Gloria',
            'type' => 'special_non_working',
            'description' => 'Special Non-Working Day · Holy Week religious observance',
        ];

        // Sort by date key ascending
        ksort($holidays);

        return $holidays;
    }

    /**
     * Get Philippine holidays occurring within a date range.
     *
     * @return array<string, array{name: string, filipino_name: string, type: 'regular' | 'special_non_working', description: string, date: string}>
     */
    public static function getHolidaysInRange(Carbon $startDate, Carbon $endDate): array
    {
        $startYear = $startDate->year;
        $endYear = $endDate->year;

        $allHolidays = [];
        for ($y = $startYear; $y <= $endYear; $y++) {
            $allHolidays += self::getHolidaysForYear($y);
        }

        $filtered = [];
        $startStr = $startDate->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        foreach ($allHolidays as $date => $holiday) {
            if ($date >= $startStr && $date <= $endStr) {
                $filtered[$date] = $holiday;
            }
        }

        return $filtered;
    }
}
