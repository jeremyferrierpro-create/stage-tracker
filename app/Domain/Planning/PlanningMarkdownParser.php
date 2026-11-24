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
 *   | Lun 23/11 | texte matin | texte après-midi | livrable/note |
 * La 4e colonne éventuelle (« Livrable / note ») est ignorée.
 * Les lignes sans créneau de travail (« VACANCES », « — », cellules
 * vides) ne produisent aucune entrée.
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

        // On garde les cases vides : les colonnes sont positionnelles
        // (jour | matin | après-midi | livrable/note éventuel).
        $cells = explode('|', $line);

        // explode produit des cases vides en tête et en queue.
        if (count($cells) < 5) {
            return null;
        }

        $dayCell = trim($cells[1]);

        // La première cellule contient une date « 23/11 » (gras optionnel).
        $plain = str_replace(['*', '`'], '', $dayCell);
        if (! preg_match('/(\d{1,2})\/(\d{1,2})/u', $plain, $m)) {
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

        return [$date, [trim($cells[2] ?? ''), trim($cells[3] ?? '')]];
    }

    /**
     * Retire le markdown et les cellules vides (« — »).
     */
    private static function clean(string $text): string
    {
        $text = str_replace(['**', '`', '*'], '', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $text = trim($text);

        // Cellules sans travail planifié.
        if ($text === '' || in_array($text, ['—', '-', 'VACANCES', 'Week-end'], true)) {
            return '';
        }

        return $text;
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
            $plusParts = preg_split('/\s+\+\s+/u', $part) ?: [$part];

            // On ne scinde sur « + » que si les deux côtés sont des
            // sous-tâches plausibles (évite « AIDF 0.4.0 + npm » → « npm »).
            $splittable = count($plusParts) > 1
                && min(array_map('mb_strlen', $plusParts)) >= 6;

            foreach ($splittable ? $plusParts : [$part] as $sub) {
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
