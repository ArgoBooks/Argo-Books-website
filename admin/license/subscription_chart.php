<?php
// Builds the "Premium Subscriptions Over Time" series. Kept apart from index.php, which renders a page on include, so it can be tested.

/**
 * One point per day from the first subscription to $today: how many had been created, and how many were active, on that day.
 *
 * @param array<int, array{created_at: string, status: string, cancelled_at: ?string, end_date: string}> $rows
 * @return list<array{date: string, total: int, active: int}>
 */
function subscription_chart_series(array $rows, string $today): array
{
    if (!$rows) {
        return [];
    }

    $subs = [];
    foreach ($rows as $row) {
        $start = substr((string) $row['created_at'], 0, 10);
        if ($row['status'] === 'active') {
            $stop = null;
        } elseif ($row['status'] === 'cancelled' && !empty($row['cancelled_at'])) {
            $stop = substr((string) $row['cancelled_at'], 0, 10);
        } else {
            // An expired or failed subscription lost access at its end date, and no later than today, since the stat card counts it as inactive now.
            $stop = min(substr((string) $row['end_date'], 0, 10), $today);
        }
        $subs[] = [$start, $stop];
    }

    $first = min(array_column($subs, 0));
    $series = [];
    for ($day = new DateTimeImmutable($first), $end = new DateTimeImmutable(max($first, $today)); $day <= $end; $day = $day->modify('+1 day')) {
        $date = $day->format('Y-m-d');
        $total = 0;
        $active = 0;
        foreach ($subs as [$start, $stop]) {
            if ($start > $date) {
                continue;
            }
            $total++;
            if ($stop === null || $date < $stop) {
                $active++;
            }
        }
        $series[] = ['date' => $date, 'total' => $total, 'active' => $active];
    }
    return $series;
}
