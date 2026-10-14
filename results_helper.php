<?php
include_once __DIR__ . '/auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// ============================================================
// Result rules - ONE place, used by every page that shows a
// total, an average, a rank or PASS / FAIL.
//
// THE RULE
// --------
// A student's result is only worked out when EVERY subject has
// a mark. If even one subject is still empty, the student is
// INCOMPLETE: no total, no average, no rank, no PASS / FAIL.
//
// Why: adding up only the subjects that happen to be entered
// gives a false average. A student with 76 in Maths and nothing
// else would show 76.0 and PASS, which is wrong - the other
// five subjects simply have not been marked yet.
// ============================================================

if (!function_exists('calc_result')) {

    // A student passes on 50% overall
    if (!defined('PASS_MARK')) define('PASS_MARK', 50);

    /**
     * Work out one student's result.
     *
     * @param array $marks    [subject_id => mark]
     * @param array $subjects rows from the subjects table (need 'id', 'name')
     * @return array total, average, status, complete, counted, expected,
     *               missing (names of the subjects with no mark yet)
     */
    function calc_result($marks, $subjects) {
        $expected = count($subjects);
        $total    = 0;
        $counted  = 0;
        $missing  = [];

        foreach ($subjects as $s) {
            $sid = (int)$s['id'];
            // isset() is right here: a mark of 0 IS a mark.
            // empty() would wrongly treat 0 as "not entered".
            if (isset($marks[$sid]) && $marks[$sid] !== '') {
                $total += (float)$marks[$sid];
                $counted++;
            } else {
                $missing[] = isset($s['name']) ? $s['name'] : ('Subject #' . $sid);
            }
        }

        $complete = ($expected > 0 && $counted === $expected);

        if (!$complete) {
            return [
                'expected'      => $expected,
                'counted'       => $counted,
                'missing'       => $missing,
                'missing_count' => $expected - $counted,
                'complete'      => false,
                // nothing is calculated until every subject is in
                'total'         => null,
                'average'       => null,
                'status'        => $expected === 0 ? 'NO SUBJECTS' : 'INCOMPLETE',
            ];
        }

        // Divide by the number of subjects the student is supposed to sit,
        // not by however many happen to be entered.
        $average = $total / $expected;

        return [
            'expected'      => $expected,
            'counted'       => $counted,
            'missing'       => [],
            'missing_count' => 0,
            'complete'      => true,
            'total'         => $total,
            'average'       => $average,
            'status'        => $average >= PASS_MARK ? 'PASS' : 'FAIL',
        ];
    }

    /**
     * Rank a list of students.
     *
     * Only students with a complete result get a rank. Students still
     * missing a subject are put at the bottom with a dash - they cannot
     * be ranked against people who sat every paper.
     *
     * Each row needs 'complete', 'total' and 'name'. Adds 'rank'.
     */
    function rank_students($students) {
        // complete first (best total first), then the incomplete ones by name
        usort($students, function ($a, $b) {
            if ($a['complete'] !== $b['complete']) {
                return $a['complete'] ? -1 : 1;
            }
            if (!$a['complete']) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
            if ($a['total'] == $b['total']) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            }
            return ($b['total'] < $a['total']) ? -1 : 1;
        });

        $pos = 0;
        $rank = 0;
        $prev = null;
        foreach ($students as $i => $st) {
            if (!$st['complete']) {
                $students[$i]['rank'] = null;      // shown as a dash
                continue;
            }
            $pos++;
            // same total = same rank
            if ($prev === null || $st['total'] < $prev) {
                $rank = $pos;
                $prev = $st['total'];
            }
            $students[$i]['rank'] = $rank;
        }
        return $students;
    }

    // ------------------------------------------------------------
    // Small bits of shared display
    // ------------------------------------------------------------

    // What to print in a total / average / rank cell
    function result_cell($value, $decimals = 0) {
        if ($value === null) return '<span class="res-none">—</span>';
        return $decimals ? number_format($value, $decimals) : $value;
    }

    // The coloured status badge
    function result_status_badge($r) {
        if ($r['status'] === 'PASS') {
            return '<span class="res-pass">✓ PASS</span>';
        }
        if ($r['status'] === 'FAIL') {
            return '<span class="res-fail">✗ FAIL</span>';
        }
        if ($r['status'] === 'NO SUBJECTS') {
            return '<span class="res-none">— no subjects —</span>';
        }
        // INCOMPLETE - say how many are still missing, and which ones
        $n     = (int)$r['missing_count'];
        $title = 'Still waiting for: ' . implode(', ', $r['missing']);
        return '<span class="res-incomplete" title="' . htmlspecialchars($title) . '">'
             . '⏳ INCOMPLETE<small>' . $r['counted'] . ' of ' . $r['expected'] . ' subjects'
             . '</small></span>';
    }

    // Styles for the three states above
    function result_styles() {
        ?>
        <style>
            .res-pass { color: #2e7d32; font-weight: bold; }
            .res-fail { color: #c62828; font-weight: bold; }
            .res-none { color: #bbb; }
            .res-incomplete {
                color: #b26a00;
                font-weight: bold;
                font-size: 12px;
                white-space: nowrap;
                cursor: help;
            }
            .res-incomplete small {
                display: block;
                font-weight: normal;
                color: #999;
                font-size: 11px;
                margin-top: 2px;
            }
            /* the whole row is greyed a little so it reads as "not finished" */
            tr.row-incomplete td { background: #fffdf5; }
            .missing-note {
                background: #fff8e1;
                border-left: 4px solid #ffb300;
                padding: 12px 15px;
                border-radius: 8px;
                font-size: 13px;
                color: #7a5c00;
                margin-bottom: 18px;
                line-height: 1.7;
            }
        </style>
        <?php
    }
}
?>
