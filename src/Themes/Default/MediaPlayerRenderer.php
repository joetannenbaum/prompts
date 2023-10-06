<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\MediaPlayer;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Themes\Default\Concerns\DrawsBoxes;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;
use Symfony\Component\Console\Helper\Table as SymfonyTable;
use Symfony\Component\Console\Helper\TableStyle;

class MediaPlayerRenderer extends Renderer
{
    use DrawsBoxes;
    use DrawsScrollbars;

    protected int $searchWidth = 60;

    protected array $frames = ['⠂', '⠒', '⠐', '⠰', '⠠', '⠤', '⠄', '⠆'];

    /**
     * Render the table.
     */
    public function __invoke(MediaPlayer $player): string
    {
        $height = $player->terminal()->lines() - 4;
        $width = $player->terminal()->cols();
        $tableWidth = (int) floor($player->terminal()->cols() * .75);
        $col1Width = (int) floor($tableWidth * .25);
        $col2Width = $tableWidth - $col1Width;
        $xMargin = (int) floor(($width - $tableWidth) / 2) - 5;
        $yMargin = 5;
        $tableHeight = $height - ($yMargin * 2);

        $this->searchWidth = $col1Width - strlen('Search: ');

        $tableStyle = (new TableStyle())
            ->setHorizontalBorderChars('─')
            ->setVerticalBorderChars('│', '│')
            ->setCellHeaderFormat($this->dim('<fg=default>%s</>'))
            ->setCellRowFormat('<fg=default>%s</>')
            ->setCrossingChars('┼', '', '', '', '┤', '┘</>', '┴', '└', '├', '<fg=gray>┌', '┬', '┐');

        $buffered = new BufferedConsoleOutput();

        if ($player->lyrics !== '') {
            $lyricLines = $this->scrollbar(
                collect($player->visibleLyrics($tableHeight))->map(function ($line) use ($player) {
                    if ($player->state === 'reading') {
                        return $line;
                    }

                    return $this->dim($line);
                }),
                $player->lyricsIndex,
                $tableHeight,
                collect($player->lyrics)->count(),
                $col2Width,
            );
        } else {
            $lyricLines = collect();
        }

        $col1Content = [
            $this->renderSearch($player),
            $this->dim(str_repeat('─', $col1Width)),
        ];

        if ($player->state === 'searching') {
            $frame = $this->frames[$player->spinnerCount % count($this->frames)];
            $col1Content[] = " {$this->cyan($frame)} Searching...";
        }

        foreach ($player->results as $i => $result) {
            if ($i === $player->resultIndex) {
                $title = $this->bold($this->cyan($result['title']));
                $artist = $this->cyan($result['artist']);
            } else {
                $title = $this->bold($result['title']);
                $artist = $this->dim($result['artist']);
            }

            if ($player->state !== 'results') {
                $title = $this->dim($title);
                $artist = $this->dim($artist);
            }

            $col1Content[] = $title;
            $col1Content[] = $artist;
            $col1Content[] = '';
        }

        $col2Content = $lyricLines->count() ? $lyricLines->join(PHP_EOL) : str_repeat(str_repeat(' ', $col2Width).PHP_EOL, $tableHeight - 1);

        if ($player->state === 'loadingLyrics') {
            $frame = $this->frames[$player->spinnerCount % count($this->frames)];
            $col2Content = " {$this->cyan($frame)} Loading Lyrics...".PHP_EOL;
            $col2Content .= str_repeat(str_repeat(' ', $col2Width).PHP_EOL, $tableHeight - 2);
        }

        $col1ContentHeight = count($col1Content);

        $rows = [
            [
                implode(PHP_EOL, $col1Content).PHP_EOL.str_repeat(str_repeat(' ', $col1Width).PHP_EOL, $tableHeight - $col1ContentHeight - 1),
                $col2Content,
            ],
        ];

        (new SymfonyTable($buffered))
            ->setRows($rows)
            ->setStyle($tableStyle)
            ->render();

        $this->newLine($yMargin);
        collect(explode(PHP_EOL, trim($buffered->content(), PHP_EOL)))
            ->each(fn ($line) => $this->line(str_repeat(' ', $xMargin).$line));
        $this->newLine($yMargin);

        // $this->newLine();

        // if ($table->state === 'search') {
        //     $hints = [
        //         ['Enter', $this->dim('Select')],
        //         ['Ctrl+D', $this->dim('Clear Search')],
        //     ];
        // } elseif ($table->state === 'jump') {
        //     $hints = [
        //         ['Enter', $this->dim('Jump to Page')],
        //     ];
        // } else {
        //     $hints = [
        //         ['↑ ↓', $this->dim('Navigate Records')],
        //         [$table->page === 1 ? $this->dim('←') : '←', $this->dim('Previous Page')],
        //         [$table->page === $table->totalPages ? $this->dim('→') : '→', $this->dim('Next Page')],
        //         ['Enter', $this->dim('Select')],
        //         ['q', $this->dim('Cancel')],
        //         ['/', $this->dim('Search')],
        //         ['j', $this->dim('Jump to Page')],
        //     ];
        // }

        // $hints = collect($hints)
        //     ->map(fn ($line) => $line[0] . ' ' . $line[1])
        //     ->join('    ');

        // $this->line('  ' . $hints);

        return $this;
    }

    protected function renderSearch(MediaPlayer $player)
    {
        if ($player->state === 'search') {
            return $this->cyan('Search: ').$player->valueWithCursor($this->searchWidth);
        }

        return $this->dim('Search: ').$this->truncate($player->query, $this->searchWidth);
    }
}
