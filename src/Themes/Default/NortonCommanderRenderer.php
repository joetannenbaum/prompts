<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\CommanderPanel;
use Laravel\Prompts\DataTable;
use Laravel\Prompts\NortonCommander;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableStyle;

class NortonCommanderRenderer extends Renderer
{
    protected int $tableCellWidth = 0;

    /**
     * Render the table.
     */
    public function __invoke(NortonCommander $commander): string
    {
        $height = $commander->terminal()->lines();
        $width = $commander->terminal()->cols();

        $this->tableCellWidth = (int) (floor($width / 2) / 3) - 4;

        $tableStyle = (new TableStyle())
            ->setHorizontalBorderChars('─')
            ->setVerticalBorderChars('│', '│')
            ->setCellHeaderFormat($this->dim('<fg=default>%s</>'))
            ->setCellRowFormat('<fg=default>%s</>')
            ->setCrossingChars('┼', '', '', '', '┤', '┘</>', '┴', '└', '├', '<fg=gray>┌', '┬', '┐');

        $selectedStyle = new TableCellStyle([
            'bg' => 'white',
            'fg' => 'black',
        ]);

        $tables = collect($commander->panels)->map(function (CommanderPanel $panel) use ($tableStyle, $height) {
            $buffered = new BufferedConsoleOutput();

            $cells = $panel->visible($height - 4);

            $cells = collect($cells)->map(
                fn ($col) => collect($col)->map($this->formatCell(...))->join(PHP_EOL)
            )->toArray();

            while (count($cells) < 3) {
                $cells[] = str_repeat(' ', $this->tableCellWidth);
            }

            foreach ($cells as $key => $cell) {
                $cells[$key] = str_repeat(
                    ' ',
                    (int) floor(($this->tableCellWidth - mb_strlen('Name')) / 2)
                ) . 'Name' . PHP_EOL . $cell;
            }

            $rows = [$cells];

            (new SymfonyTable($buffered))
                ->setHeaderTitle($panel->currentDir)
                ->setRows($rows)
                ->setStyle($tableStyle)
                ->render();

            return trim($buffered->content());
        });

        collect(explode(PHP_EOL, $tables->shift()))->zip(explode(PHP_EOL, $tables->last()))->each(function ($row) {
            $this->line(' ' . $row[0] . $row[1]);
        });

        return $this;

        dd($tables);

        collect(explode(PHP_EOL, trim($buffered->content(), PHP_EOL)))
            ->each(fn ($line) => $this->line(' ' . $line));

        $this->line('  ' . $this->dim('Page ') . $table->page . $this->dim(' of ') . $table->totalPages);
        $this->newLine();

        if ($table->state === 'search') {
            $hints = [
                ['Enter', $this->dim('Select')],
                ['Ctrl+D', $this->dim('Clear Search')],
            ];
        } elseif ($table->state === 'jump') {
            $hints = [
                ['Enter', $this->dim('Jump to Page')],
            ];
        } else {
            $hints = [
                ['↑ ↓', $this->dim('Navigate Records')],
                [$table->page === 1 ? $this->dim('←') : '←', $this->dim('Previous Page')],
                [$table->page === $table->totalPages ? $this->dim('→') : '→', $this->dim('Next Page')],
                ['Enter', $this->dim('Select')],
                ['q', $this->dim('Cancel')],
                ['/', $this->dim('Search')],
                ['j', $this->dim('Jump to Page')],
            ];
        }

        $hints = collect($hints)
            ->map(fn ($line) => $line[0] . ' ' . $line[1])
            ->join('    ');

        $this->line('  ' . $hints);

        return $this;
    }

    protected function formatCell($cell)
    {
        if ($cell['type'] === 'dir') {
            return $this->cyan(str_pad(strtoupper($cell['name']), $this->tableCellWidth, ' ', STR_PAD_RIGHT));
        }

        if (substr($cell['name'], 0, 1) === '.') {
            return $this->cyan(str_pad($cell['name'], $this->tableCellWidth, ' ', STR_PAD_RIGHT));
        }

        $buffer = $this->tableCellWidth - mb_strlen($cell['extension']) - mb_strlen($cell['filename']);

        return $this->cyan($cell['filename'] . str_repeat(' ', $buffer) . $cell['extension']);
    }
}
