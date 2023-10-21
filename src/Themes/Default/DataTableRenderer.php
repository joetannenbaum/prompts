<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\DataTable;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableStyle;

class DataTableRenderer extends Renderer
{
    /**
     * Render the table.
     */
    public function __invoke(DataTable $table): string
    {
        $this->renderSearch($table);
        $this->renderJump($table);

        $tableStyle = (new TableStyle())
            ->setHorizontalBorderChars('─')
            ->setVerticalBorderChars('│', '│')
            ->setCellHeaderFormat($this->dim('<fg=default>%s</>'))
            ->setCellRowFormat('<fg=default>%s</>');

        if (empty($table->headers)) {
            $tableStyle->setCrossingChars('┼', '', '', '', '┤', '┘</>', '┴', '└', '├', '<fg=gray>┌', '┬', '┐');
        } else {
            $tableStyle->setCrossingChars('┼', '<fg=gray>┌', '┬', '┐', '┤', '┘</>', '┴', '└', '├');
        }

        $buffered = new BufferedConsoleOutput();

        $selectedStyle = new TableCellStyle([
            'bg' => 'white',
            'fg' => 'black',
        ]);

        $rows = $table->visible();

        $rows[$table->index] = collect($rows[$table->index])->map(fn ($cell) => new TableCell($cell, [
            'style' => $selectedStyle,
        ]))->all();

        (new SymfonyTable($buffered))
            ->setHeaders($table->headers)
            ->setRows($rows)
            ->setStyle($tableStyle)
            ->render();

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

    protected function renderSearch(DataTable $table)
    {
        if ($table->state !== 'search' && $table->query === '') {
            return;
        }

        if ($table->state !== 'search' && $table->query !== '') {
            $this->line('  ' . $this->dim('Search: ') . $table->query);

            return;
        }

        $this->line('  Search: ' . $table->valueWithCursor(60));
    }

    protected function renderJump(DataTable $table)
    {
        if ($table->state !== 'jump') {
            return;
        }

        $this->line('  Jump to Page: ' . $table->jumpValueWithCursor(60));
    }
}
