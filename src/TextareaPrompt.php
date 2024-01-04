<?php

namespace Laravel\Prompts;

use Closure;

class TextareaPrompt extends Prompt
{
    use Concerns\Scrolling;
    use Concerns\TypedValue;

    public int $width = 60;

    protected int $cursorOffset = 0;

    /**
     * Create a new TextareaPrompt instance.
     */
    public function __construct(
        public string $label,
        public int $rows = 5,
        public string $placeholder = '',
        public string $default = '',
        public bool|string $required = false,
        public ?Closure $validate = null,
        public string $hint = ''
    ) {
        $this->trackTypedValue(
            default: $default,
            submit: false,
            allowNewLine: true,
        );

        $this->scroll = $this->rows;

        $this->initializeScrolling();

        $this->on(
            'key',
            function ($key) {
                if ($key[0] === "\e") {
                    match ($key) {
                        Key::UP, Key::UP_ARROW, Key::CTRL_P => $this->handleUpKey(),
                        Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N => $this->handleDownKey(),
                        default => null,
                    };

                    return;
                }

                // Keys may be buffered.
                foreach (mb_str_split($key) as $key) {
                    if ($key === Key::CTRL_D) {
                        $this->submit();

                        return;
                    }
                }
            }
        );
    }

    /**
     * Handle the up keypress.
     */
    protected function handleUpKey(): void
    {
        if ($this->cursorPosition === 0) {
            return;
        }

        $lines = collect($this->lines());

        // Line length + 1 for the newline character
        $lineLengths = $lines->map(fn ($line, $index) => mb_strlen($line) + ($index === $lines->count() - 1 ? 0 : 1));

        $currentLineIndex = $this->currentLineIndex();

        if ($currentLineIndex === 0) {
            // They're already at the first line, jump them to the first position
            $this->cursorPosition = 0;

            return;
        }

        $currentLines = $lineLengths->slice(0, $currentLineIndex + 1);

        $currentColumn = $currentLines->last() - ($currentLines->sum() - $this->cursorPosition);

        $destinationLineLength = $lineLengths->get($currentLineIndex - 1) ?? $currentLines->first();

        $newColumn = min($destinationLineLength, $currentColumn);

        if ($newColumn < $currentColumn) {
            $newColumn--;
        }

        $fullLines = $currentLines->slice(0, -2);

        $this->cursorPosition = $fullLines->sum() + $newColumn;
    }

    /**
     * Handle the down keypress.
     */
    protected function handleDownKey(): void
    {
        $lines = collect($this->lines());

        // Line length + 1 for the newline character
        $lineLengths = $lines->map(fn ($line, $index) => mb_strlen($line) + ($index === $lines->count() - 1 ? 0 : 1));

        $currentLineIndex = $this->currentLineIndex();

        if ($currentLineIndex === $lines->count() - 1) {
            // They're already at the last line, jump them to the last position
            $this->cursorPosition = mb_strlen($lines->implode(PHP_EOL));

            return;
        }

        // Lines up to and including the current line
        $currentLines = $lineLengths->slice(0, $currentLineIndex + 1);

        $currentColumn = $currentLines->last() - ($currentLines->sum() - $this->cursorPosition);

        $destinationLineLength = ($lineLengths->get($currentLineIndex + 1) ?? $currentLines->last()) - 1;

        $newColumn = min(max(0, $destinationLineLength), $currentColumn);

        $this->cursorPosition = $currentLines->sum() + $newColumn;
    }

    /**
     * The currently visible options.
     *
     * @return array<int, string>
     */
    public function visible(): array
    {
        $this->adjustVisibleWindow();

        $withCursor = $this->valueWithCursor(10_000);

        return array_slice(explode(PHP_EOL, $withCursor), $this->firstVisible, $this->scroll, preserve_keys: true);
    }

    protected function adjustVisibleWindow(): void
    {
        if (count($this->lines()) < $this->scroll) {
            return;
        }

        $currentLineIndex = $this->currentLineIndex();

        if ($this->firstVisible + $this->scroll <= $currentLineIndex) {
            $this->firstVisible++;
        }

        if ($currentLineIndex === $this->firstVisible - 1) {
            $this->firstVisible = max(0, $this->firstVisible - 1);
        }

        // Make sure there are always the scroll amount visible
        if ($this->firstVisible + $this->scroll > count($this->lines())) {
            $this->firstVisible = count($this->lines()) - $this->scroll;
        }
    }

    /**
     * Get the index of the current line that the cursor is on.
     */
    protected function currentLineIndex(): int
    {
        $totalLineLength = 0;

        return (int) collect($this->lines())->search(function ($line) use (&$totalLineLength) {
            $totalLineLength += mb_strlen($line) + 1;

            return $totalLineLength > $this->cursorPosition;
        }) ?: 0;
    }

    /**
     * Get the formatted lines of the current value.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        $this->calculateCursorOffset();

        $value = wordwrap($this->value(), $this->width - 1, PHP_EOL, true);

        return explode(PHP_EOL, $value);
    }

    /**
     * Get the formatted value with a virtual cursor.
     */
    public function valueWithCursor(int $maxWidth): string
    {
        $value = implode(PHP_EOL, $this->lines());

        if ($this->value() === '') {
            return $this->dim($this->addCursor($this->placeholder, 0, 10_000));
        }

        return $this->addCursor($value, $this->cursorPosition + $this->cursorOffset, -1);
    }

    /**
     * When there are long words that wrap, the `typedValue` and the display value are different,
     * (due to the inserted new line characters from the word wrap) and the cursor calculation is off.
     * This method calculates the difference and adjusts the cursor position accordingly.
     */
    protected function calculateCursorOffset(): void
    {
        $this->cursorOffset = 0;

        preg_match_all('/\S{'.($this->width).',}/u', $this->value(), $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            if ($this->cursorPosition + $this->cursorOffset >= $match[1] + mb_strlen($match[0])) {
                $this->cursorOffset += (int) floor(mb_strlen($match[0]) / ($this->width - 1));
            }
        }
    }
}