<?php

namespace Laravel\Prompts\Themes\Default;

use Laravel\Prompts\Kanban;
use Laravel\Prompts\Themes\Default\Concerns\DrawsBoxes;

class KanbanRenderer extends Renderer
{
    use DrawsBoxes;

    /**
     * Render the table.
     */
    public function __invoke(Kanban $kanban): string
    {
        $columnWidth = (int) floor($this->prompt->terminal()->cols() / 3) - 6;

        $cardWidth = $columnWidth - 6;

        $totalHeight = $this->prompt->terminal()->lines() - 10;

        $columnTitles = ['To Do', 'In Progress', 'Done'];

        $columns = collect($kanban->items)->map(function ($cards, $columnIndex) use (
            $cardWidth,
            $columnWidth,
            $kanban,
            $totalHeight,
            $columnTitles,
        ) {
            $this->output = '';

            $this->minWidth = $cardWidth;

            collect($cards)->each(function ($card, $cardIndex) use ($kanban, $columnIndex) {
                $this->newLine();
                $this->box(
                    $kanban->state === 'pendingDelete' ? $this->dim($card['title']) : $card['title'],
                    PHP_EOL.
                        ($kanban->state === 'pendingDelete' ? $this->dim(
                            $card['description']
                        ) : $card['description']).PHP_EOL,
                    '',
                    $cardIndex == $kanban->itemIndex && $kanban->columnIndex === $columnIndex ? 'green' : 'dim',
                );
            });

            $cardContent = $this->output;

            $cardContent .= str_repeat(PHP_EOL, $totalHeight - count(explode(PHP_EOL, $this->output)) + 1);

            $this->output = '';

            $this->minWidth = $columnWidth;

            return explode(
                PHP_EOL,
                $this->box(
                    $kanban->columnIndex === $columnIndex ? $this->cyan($columnTitles[$columnIndex]) : $this->dim($columnTitles[$columnIndex]),
                    $cardContent,
                    '',
                    $kanban->columnIndex === $columnIndex ? 'cyan' : 'dim',
                )->output
            );
        });

        $this->output = '';

        $lines = collect($columns->shift())->zip(...$columns)->map(fn ($lines) => $lines[0].$lines[1].$lines[2]);

        if ($kanban->state === 'pendingDelete') {
            if ($kanban->modalAnimating) {
                $lines = $this->drawAnimatedModal($lines, $kanban);
            } else {
                $lines = $this->drawModal($lines, $kanban);
            }
        }

        $lines->each(fn ($line) => $this->line($line));

        return $this;
    }

    protected function drawAnimatedModal($lines, Kanban $kanban)
    {
        $this->minWidth = 0;

        $body = collect(['Are you sure you want to delete '.$this->bold($kanban->items[$kanban->columnIndex][$kanban->itemIndex]['title']).'?'])
            ->map(fn ($line) => str_repeat(' ', 10).$line.str_repeat(' ', 10));

        $bodyWidth = mb_strlen($this->stripEscapeSequences($body->first()));

        $confirmActive = $this->bgRed(str_repeat(' ', 11)).PHP_EOL.$this->bgRed($this->white('  Confirm  ')).PHP_EOL.$this->bgRed(str_repeat(' ', 11));
        $confirmInactive = str_repeat(' ', 11).PHP_EOL.$this->dim('  Confirm  ').PHP_EOL.str_repeat(' ', 11);

        $confirm = $kanban->deleteAction === 'confirm' ? $confirmActive : $confirmInactive;

        $cancelActive = $this->bgWhite(str_repeat(' ', 10)).PHP_EOL.$this->bgWhite($this->white('  Cancel  ')).PHP_EOL.$this->bgWhite(str_repeat(' ', 10));
        $cancelInactive = str_repeat(' ', 10).PHP_EOL.$this->dim('  Cancel  ').PHP_EOL.str_repeat(' ', 10);

        $cancel = $kanban->deleteAction === 'cancel' ? $cancelActive : $cancelInactive;

        $buttons = collect(explode(PHP_EOL, $cancel))->zip(explode(PHP_EOL, $confirm))->map(fn ($lines) => $lines[0].str_repeat(' ', 5).$lines[1])
            ->map(fn ($line) => str_repeat(' ', ($bodyWidth - mb_strlen($this->stripEscapeSequences($line))) / 2).$line)
            ->implode(PHP_EOL);

        $body->push($buttons);

        $body = $body->implode(str_repeat(PHP_EOL, 2));

        $modal = $this->box(
            '',
            str_repeat(PHP_EOL, 2).$body.str_repeat(PHP_EOL, 2),
            '',
            'red',
        );

        $modalContent = trim($modal->output);

        $this->output = '';

        $modalLines = explode(PHP_EOL, $modalContent);

        // if ($kanban->modalFirstRound) {
        //     $kanban->modalFirstRound = false;
        //     $kanban->modalFrames = count($modalLines) * -1;
        // }

        // $modalLines = array_slice($modalLines, ((($kanban->modalFrames + 1)) * -1 / 2));

        // dd($kanban->modalFrames, $modalLines, (((-$kanban->modalFrames + 1))  / 2) * -1);

        $yStart = (int) floor(($lines->count() - count($modalLines)) / 2) - 2;

        $xBuffer = (int) floor((mb_strlen($this->stripEscapeSequences($lines->first())) - mb_strlen($this->stripEscapeSequences($modalLines[0]))) / 2);

        $lines = $lines->map(function ($line) {
            return collect(preg_split("/(\e[^m]*m)/", $line, -1, PREG_SPLIT_DELIM_CAPTURE))->map(function ($segment) {
                if (str_starts_with($segment, "\e")) {
                    return $segment;
                }

                return $this->dim($segment);
            })->join('');
        });

        foreach ($modalLines as $index => $line) {
            $currentIndex = $kanban->modalFrames + $index;
            $length = 0;
            $beforeModal = '';
            $afterModal = [];

            $lineSegments = preg_split("/(\e[^m]*m)/", $lines[$currentIndex], -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($lineSegments as $segment) {
                if (str_starts_with($segment, "\e")) {
                    $beforeModal .= $segment;

                    continue;
                }

                $length += mb_strlen($segment);

                if ($length < $xBuffer) {
                    $beforeModal .= $segment;
                } else {
                    $extraLength = $length - $xBuffer;
                    $beforeModal .= mb_substr($segment, 0, mb_strlen($segment) - $extraLength);
                    break;
                }
            }

            $length = 0;

            foreach (array_reverse($lineSegments) as $segment) {
                if (str_starts_with($segment, "\e")) {
                    $afterModal[] = $segment;

                    continue;
                }

                $length += mb_strlen($segment);

                if ($length < $xBuffer) {

                    $afterModal[] = $segment;
                } else {
                    $extraLength = $length - $xBuffer;
                    $afterModal[] = mb_substr($segment, (mb_strlen($segment) - $extraLength) * -1);
                    break;
                }
            }

            $afterModal = implode('', array_reverse($afterModal));

            $lines[$currentIndex] = $this->dim($beforeModal).$this->reset('').$line.$this->reset('').$this->dim($afterModal);
        }

        $kanban->modalFrames++;
        $kanban->modalAnimating = $kanban->modalFrames < $yStart;

        return $lines;
    }

    protected function drawModal($lines, Kanban $kanban)
    {
        $this->minWidth = 0;

        $body = collect(['Are you sure you want to delete '.$this->bold($kanban->items[$kanban->columnIndex][$kanban->itemIndex]['title']).'?'])
            ->map(fn ($line) => str_repeat(' ', 10).$line.str_repeat(' ', 10));

        $bodyWidth = mb_strlen($this->stripEscapeSequences($body->first()));

        $confirmActive = $this->bgRed(str_repeat(' ', 11)).PHP_EOL.$this->bgRed($this->white('  Confirm  ')).PHP_EOL.$this->bgRed(str_repeat(' ', 11));
        $confirmInactive = str_repeat(' ', 11).PHP_EOL.$this->dim('  Confirm  ').PHP_EOL.str_repeat(' ', 11);

        $confirm = $kanban->deleteAction === 'confirm' ? $confirmActive : $confirmInactive;

        $cancelActive = $this->bgWhite(str_repeat(' ', 10)).PHP_EOL.$this->bgWhite($this->white('  Cancel  ')).PHP_EOL.$this->bgWhite(str_repeat(' ', 10));
        $cancelInactive = str_repeat(' ', 10).PHP_EOL.$this->dim('  Cancel  ').PHP_EOL.str_repeat(' ', 10);

        $cancel = $kanban->deleteAction === 'cancel' ? $cancelActive : $cancelInactive;

        $buttons = collect(explode(PHP_EOL, $cancel))->zip(explode(PHP_EOL, $confirm))->map(fn ($lines) => $lines[0].str_repeat(' ', 5).$lines[1])
            ->map(fn ($line) => str_repeat(' ', ($bodyWidth - mb_strlen($this->stripEscapeSequences($line))) / 2).$line)
            ->implode(PHP_EOL);

        $body->push($buttons);

        $body = $body->implode(str_repeat(PHP_EOL, 2));

        $modal = $this->box(
            '',
            str_repeat(PHP_EOL, 2).$body.str_repeat(PHP_EOL, 2),
            '',
            'red',
        );

        $modalContent = trim($modal->output);

        $this->output = '';

        $modalLines = explode(PHP_EOL, $modalContent);

        $yStart = (int) floor(($lines->count() - count($modalLines)) / 2) - 2;

        $xBuffer = (int) floor((mb_strlen($this->stripEscapeSequences($lines->first())) - mb_strlen($this->stripEscapeSequences($modalLines[0]))) / 2);

        $lines = $lines->map(function ($line) {
            return collect(preg_split("/(\e[^m]*m)/", $line, -1, PREG_SPLIT_DELIM_CAPTURE))->map(function ($segment) {
                if (str_starts_with($segment, "\e")) {
                    return $segment;
                }

                return $this->dim($segment);
            })->join('');
        });

        foreach ($modalLines as $index => $line) {
            $currentIndex = $yStart + $index;
            $length = 0;
            $beforeModal = '';
            $afterModal = [];

            $lineSegments = preg_split("/(\e[^m]*m)/", $lines[$currentIndex], -1, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($lineSegments as $segment) {
                if (str_starts_with($segment, "\e")) {
                    $beforeModal .= $segment;

                    continue;
                }

                $length += mb_strlen($segment);

                if ($length < $xBuffer) {
                    $beforeModal .= $segment;
                } else {
                    $extraLength = $length - $xBuffer;
                    $beforeModal .= mb_substr($segment, 0, mb_strlen($segment) - $extraLength);
                    break;
                }
            }

            $length = 0;

            foreach (array_reverse($lineSegments) as $segment) {
                if (str_starts_with($segment, "\e")) {
                    $afterModal[] = $segment;

                    continue;
                }

                $length += mb_strlen($segment);

                if ($length < $xBuffer) {

                    $afterModal[] = $segment;
                } else {
                    $extraLength = $length - $xBuffer;
                    $afterModal[] = mb_substr($segment, (mb_strlen($segment) - $extraLength) * -1);
                    break;
                }
            }

            $afterModal = implode('', array_reverse($afterModal));

            $lines[$currentIndex] = $this->dim($beforeModal).$this->reset('').$line.$this->reset('').$this->dim($afterModal);
        }

        return $lines;
    }
}
