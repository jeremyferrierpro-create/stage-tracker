<?php

namespace App\Domain\Planning;

use App\Domain\Internship\Models\Internship;
use App\Domain\Journal\Models\DailyLog;
use App\Domain\Planning\Models\PlanEntry;
use Illuminate\Support\Carbon;

/**
 * Compare le planning prévisionnel (plan_entries) au réel (daily_logs)
 * pour un stage, jour par jour.
 *
 * Statuts calculés automatiquement :
 * - done    : journal existant et durée réelle >= durée planifiée ;
 * - partial : journal existant mais durée inférieure au prévu ;
 * - todo    : jour futur sans journal ;
 * - missed  : jour passé sans journal (considéré « reporté »).
 */
final class PlanningComparisonService
{
    public const STATUSES = ['done', 'partial', 'todo', 'missed'];

    public const STATUS_LABELS = [
        'done' => 'Fait',
        'partial' => 'Partiel',
        'todo' => 'À faire',
        'missed' => 'Reporté',
    ];

    /**
     * @return list<array{
     *     date: string,
     *     entries: array<string, list<PlanEntry>>,
     *     planned_minutes: int,
     *     logs: list<DailyLog>,
     *     actual_minutes: int,
     *     percent: int,
     *     status: string,
     * }>
     */
    public function daysFor(Internship $internship): array
    {
        $entries = PlanEntry::query()
            ->where('internship_id', $internship->getKey())
            ->orderBy('plan_date')
            ->orderBy('slot')
            ->orderBy('position')
            ->get()
            ->groupBy(static fn (PlanEntry $e): string => $e->plan_date->toDateString());

        $dates = $entries->keys()->all();

        // Journaux réels groupés par date (plusieurs auteurs possibles).
        $logsByDate = DailyLog::query()
            ->where('internship_id', $internship->getKey())
            ->whereIn('work_date', $dates)
            ->orderBy('work_date')
            ->get()
            ->groupBy(static fn (DailyLog $l): string => $l->work_date->toDateString());

        $today = Carbon::today();
        $days = [];

        foreach ($entries as $date => $dayEntries) {
            $dayEntries = $dayEntries
                ->groupBy('slot')
                ->map(static fn ($slotEntries) => $slotEntries->values()->all())
                ->all();

            $logs = $logsByDate->get($date, collect())->values()->all();

            $planned = $dayEntries === []
                ? 0
                : array_sum(array_map(
                    static fn (PlanEntry $e): int => $e->planned_minutes,
                    array_merge(...array_values($dayEntries)),
                ));

            $actual = array_sum(array_map(
                static fn (DailyLog $l): int => $l->duration_minutes ?? 0,
                $logs,
            ));

            $status = self::statusFor($date, $logs, $planned, $actual, $today);

            $days[] = [
                'date' => $date,
                'entries' => $dayEntries,
                'planned_minutes' => $planned,
                'logs' => $logs,
                'actual_minutes' => $actual,
                'percent' => $planned > 0 ? min(100, (int) round($actual / $planned * 100)) : 0,
                'status' => $status,
            ];
        }

        return $days;
    }

    /**
     * @param  list<DailyLog>  $logs
     */
    private static function statusFor(string $date, array $logs, int $planned, int $actual, Carbon $today): string
    {
        if ($logs === []) {
            return Carbon::parse($date)->lt($today) ? 'missed' : 'todo';
        }

        if ($planned <= 0) {
            return 'done';
        }

        if ($actual >= $planned) {
            return 'done';
        }

        // Journal présent mais sans durée : « partiel » s'il contient du
        // contenu, « à faire » s'il est vide.
        if ($actual === 0) {
            $hasContent = false;
            foreach ($logs as $log) {
                if (filled($log->work_done) || $log->activities->isNotEmpty()) {
                    $hasContent = true;
                    break;
                }
            }

            return $hasContent ? 'partial' : 'todo';
        }

        return 'partial';
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }
}
