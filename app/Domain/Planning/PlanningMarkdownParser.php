<?php

namespace App\Domain\Planning;

use App\Domain\Planning\Models\PlanEntry;
use Illuminate\Support\Carbon;

/**
 * Parse un planning journalier rédigé en markdown (tableaux par semaine,
 * une ligne par jour ouvré, colonnes Matin / Après-midi) et produit la
 * liste des entrées plan_entries à insérer.
 *
 * Format attendu (voir aidf/projets/coab/docs/PLANNING_STAGE.md) :
 *   | **Lun 23/11** *(en cours)* | texte matin | texte après-midi |
 *
 * Chaque cellule peut contenir plusieurs sous-tâches séparées par « ; »
 * ou « + » : elles deviennent des plan_entries distinctes qui se
 * partagent les 210 minutes du créneau.
 */
final class PlanningMarkdownParser
{
    /**
     * Caractères markdown retirés des titres importés.
     */
    private const SLOT_MINUTES = PlanEntry::SLOT_MINUTES;

    /**
     * Nombre maximal de sous-tâches par créneau (garde-fou).
     */
    private const MAX_SUBTASKS = 6;

    /**
     * @return list<array{plan_date: string, slot: string, title: string, planned_minutes: int, position: int}>
     */
    public static function parse(string $markdown): array
    {
        $entries = [];

        foreach (preg_split('/\r\n|\r|\n/', $markdown) ?: [] as $line) {
            $row = self::parseRow($line);

            if ($row === null) {
                continue;
            }

            [$date, $cells] = $row;

            foreach (['morning', 'afternoon'] as $index => $slot) {
                $text = self::clean($cells[$index] ?? '');

                if ($text === '') {
                    continue;
                }

                foreach (self::splitSubtasks($text) as $position => $title) {
                    $entries[] = [
                        'plan_date' => $date->toDateString(),
                        'slot' => $slot,
                        'title' => $title,
                        'planned_minutes' => 0, // calculé après regroupement
                        'position' => $position,
                    ];
                }
            }
        }

        return self::distributeMinutes($entries);
    }

    /**
     * Extrait la date et les cellules d'une ligne de tableau « jour ».
     *
     * @return array{0: Carbon, 1: list<string>}|null
     */
    private static function parseRow(string $line): ?array
    {
        $line = trim($line);

        if (! str_starts_with($line, '|')) {
            return null;
        }

        $cells = array_map('trim', explode('|', $line));
        // explode produit des cases vides en tête et en queue.
        $cells = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));

        if (count($cells) < 3) {
            return null;
        }

        // La première cellule ressemble à « **Lun 23/11** *(en cours)* ».
        if (! preg_match('/\*\*[^*]*?(\d{1,2})\/(\d{1,2})[^*]*?\*\*/u', $cells[0], $m)) {
            return null;
        }

        $day = (int) $m[1];
        $month = (int) $m[2];

        // Le stage couvre nov. 2026 → janv. 2027 : mois >= 10 ⇒ 2026.
        $year = $month >= 10 ? 2026 : 2027;

        try {
            $date = Carbon::create($year, $month, $day)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return [$date, [$cells[1] ?? '', $cells[2] ?? '']];
    }

    /**
     * Retire le markdown et les cellules vides (« — »).
     */
    private static function clean(string $text): string
    {
        $text = str_replace(['**', '`', '*'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $text = trim($text);

        return in_array($text, ['', '—', '-'], true) ? '' : $text;
    }

    /**
     * Découpe une cellule en sous-tâches (« ; » puis « + ») et tronque
     * les titres trop longs pour la colonne.
     *
     * @return list<string>
     */
    private static function splitSubtasks(string $text): array
    {
        $parts = preg_split('/\s*;\s*/u', $text) ?: [$text];

        $subtasks = [];
        foreach ($parts as $part) {
            foreach (preg_split('/\s+\+\s+/u', $part) ?: [$part] as $sub) {
                $sub = trim($sub, " \t\n\r\0\x0B.,");
                if ($sub !== '') {
                    $subtasks[] = mb_strimwidth($sub, 0, 200, '');
                }
            }
        }

        return array_slice($subtasks, 0, self::MAX_SUBTASKS);
    }

    /**
     * Répartit les 210 minutes de chaque créneau entre ses sous-tâches
     * (le reste éventuel va aux premières entrées).
     *
     * @param  list<array{plan_date: string, slot: string, title: string, planned_minutes: int, position: int}>  $entries
     * @return list<array{plan_date: string, slot: string, title: string, planned_minutes: int, position: int}>
     */
    private static function distributeMinutes(array $entries): array
    {
        // Regroupe les index par (date, slot) en conservant l'ordre.
        $groups = [];
        foreach ($entries as $i => $entry) {
            $groups[$entry['plan_date'].'|'.$entry['slot']][] = $i;
        }

        foreach ($groups as $indexes) {
            $count = count($indexes);
            $base = intdiv(self::SLOT_MINUTES, $count);
            $remainder = self::SLOT_MINUTES % $count;

            foreach ($indexes as $offset => $i) {
                $entries[$i]['planned_minutes'] = $base + ($offset < $remainder ? 1 : 0);
            }
        }

        return $entries;
    }
}
