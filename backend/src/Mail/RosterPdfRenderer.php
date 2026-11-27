<?php

declare(strict_types=1);

namespace App\Mail;

use App\Entity\Assignment;
use App\Entity\Roster;
use App\Entity\Shift;
use App\Service\RosterService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The whole roster as a PDF: every day of the week, every shift with its time,
 * position and the people on it, open places marked. Only names are shown, no
 * contact details, so it can be shared among colleagues to arrange swaps.
 */
class RosterPdfRenderer
{
    public function __construct(
        private readonly RosterService $rosters,
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
    ) {
    }

    /** The PDF file's bytes. */
    public function render(Roster $roster): string
    {
        $workDir = $this->cacheDir.'/dompdf';
        if (!is_dir($workDir)) {
            mkdir($workDir, 0o777, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', $workDir);
        $options->set('fontCache', $workDir);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($roster));
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** The page as HTML. Everything that comes from the database is escaped. */
    public function html(Roster $roster): string
    {
        $shifts = $this->rosters->shiftsOf($roster);
        $assignments = $this->rosters->assignmentsOf($roster);

        $rows = '';
        for ($offset = 0; $offset < 7; ++$offset) {
            $day = $roster->getWeekStart()->modify(sprintf('+%d days', $offset));
            $dayShifts = array_values(array_filter(
                $shifts,
                static fn (Shift $shift): bool => $shift->getDate()->format('Y-m-d') === $day->format('Y-m-d'),
            ));
            $rows .= $this->dayRows($day, $dayShifts, $assignments);
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:11px;color:#222}'
            .'h1{font-size:18px;margin:0 0 10px}'
            .'table{width:100%;border-collapse:collapse}'
            .'th,td{border:1px solid #bbb;padding:5px 7px;text-align:left;vertical-align:top}'
            .'th{background:#eee}.day{font-weight:bold;width:14%}.none{color:#888}em{color:#a33}'
            .'</style></head><body>'
            .'<h1>Roster '.$this->e(WeekLabel::of($roster->getWeekStart())).'</h1>'
            .'<table><tr><th>Day</th><th>Time</th><th>Position</th><th>People</th></tr>'.$rows.'</table>'
            .'</body></html>';
    }

    /**
     * @param list<Shift>                  $dayShifts
     * @param array<int, list<Assignment>> $assignments by shift id
     */
    private function dayRows(\DateTimeImmutable $day, array $dayShifts, array $assignments): string
    {
        $label = $this->e($day->format('D j M'));
        if ([] === $dayShifts) {
            return '<tr><td class="day">'.$label.'</td><td colspan="3" class="none">no shifts</td></tr>';
        }

        $rows = '';
        foreach ($dayShifts as $index => $shift) {
            $dayCell = 0 === $index
                ? '<td class="day" rowspan="'.count($dayShifts).'">'.$label.'</td>'
                : '';
            $rows .= '<tr>'.$dayCell
                .'<td>'.$this->e($this->timeOf($shift)).'</td>'
                .'<td>'.$this->e(ucfirst($shift->getRequiredPosition()->label())).'</td>'
                .'<td>'.$this->peopleOf($shift, $assignments[(int) $shift->getId()] ?? []).'</td>'
                .'</tr>';
        }

        return $rows;
    }

    private function timeOf(Shift $shift): string
    {
        $overnight = $shift->getEndTime()->format('H:i') < $shift->getStartTime()->format('H:i');

        return $shift->getStartTime()->format('H:i').'–'.$shift->getEndTime()->format('H:i').($overnight ? ' +1 day' : '');
    }

    /** @param list<Assignment> $assignments */
    private function peopleOf(Shift $shift, array $assignments): string
    {
        $names = array_map(
            fn (Assignment $assignment): string => $this->e($assignment->getEmployee()->getName()),
            $assignments,
        );
        $open = $shift->getHeadcount() - count($assignments);
        if ($open > 0) {
            $names[] = '<em>'.$open.' open</em>';
        }

        return implode(', ', $names);
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
