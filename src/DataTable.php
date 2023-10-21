<?php

namespace Laravel\Prompts;

use Illuminate\Support\Collection;

class DataTable extends Prompt
{
    use Concerns\TypedValue;

    /**
     * The table headers.
     *
     * @var array<int, string|array<int, string>>
     */
    public array $headers;

    /**
     * The table rows.
     *
     * @var array<int, array<int, string>>
     */
    public array $rows;

    public int $perPage = 10;

    public int $page = 1;

    public int $index = 0;

    public string $query = '';

    public int $totalPages;

    public string $jumpToPage = '';

    /**
     * Create a new Table instance.
     *
     * @param  array<int, string|array<int, string>>|Collection<int, string|array<int, string>>  $headers
     * @param  array<int, array<int, string>>|Collection<int, array<int, string>>  $rows
     *
     * @phpstan-param ($rows is null ? list<list<string>>|Collection<int, list<string>> : list<string|list<string>>|Collection<int, string|list<string>>) $headers
     */
    public function __construct(array|Collection $headers = [], array|Collection $rows = null)
    {
        if ($rows === null) {
            $rows = $headers;
            $headers = [];
        }

        $this->headers = $headers instanceof Collection ? $headers->all() : $headers;
        $this->rows = $rows instanceof Collection ? $rows->all() : $rows;

        $this->totalPages = (int) ceil(count($this->rows) / $this->perPage);

        $this->listenForHotkeys();
    }

    protected function quit(): void
    {
        $this->state = 'cancel';
        exit;
    }

    protected function listenForHotkeys(): void
    {
        $this->on('key', function ($key) {
            if ($key[0] === "\e") {
                match ($key) {
                    Key::UP, Key::UP_ARROW => $this->index = max(0, $this->index - 1),
                    Key::DOWN, Key::DOWN_ARROW => $this->index = min($this->perPage - 1, $this->index + 1),
                    Key::RIGHT, Key::RIGHT_ARROW => $this->nextPage(),
                    Key::LEFT, Key::LEFT_ARROW => $this->previousPage(),
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->submit();

                    return;
                } else {
                    match ($key) {
                        'q'     => $this->quit(),
                        '/'     => $this->search(),
                        'j'     => $this->jump(),
                        default => null,
                    };
                }
            }
        });
    }

    protected function search(): void
    {
        $this->state = 'search';
        $this->clearListeners();

        $this->index = 0;

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {

                match ($key) {
                    Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B => $this->cursorPosition = max(0, $this->cursorPosition - 1),
                    Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F => $this->cursorPosition = min(mb_strlen($this->query), $this->cursorPosition + 1),
                    Key::HOME, Key::CTRL_A => $this->cursorPosition = 0,
                    Key::END, Key::CTRL_E => $this->cursorPosition = mb_strlen($this->query),
                    Key::DELETE => $this->query = mb_substr($this->query, 0, $this->cursorPosition) . mb_substr($this->query, $this->cursorPosition + 1),
                    default     => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->clearListeners();
                    $this->state = 'select';
                    $this->listenForHotkeys();

                    return;
                } elseif ($key === Key::BACKSPACE || $key === Key::CTRL_H) {
                    if ($this->cursorPosition === 0) {
                        return;
                    }

                    $this->query = mb_substr($this->query, 0, $this->cursorPosition - 1) . mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition--;
                } elseif (ord($key) >= 32) {
                    $this->query = mb_substr($this->query, 0, $this->cursorPosition) . $key . mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition++;
                }
            }
        });
    }

    protected function jump(): void
    {
        $this->state = 'jump';
        $this->clearListeners();

        $this->index = 0;

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {

                match ($key) {
                    Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B => $this->cursorPosition = max(0, $this->cursorPosition - 1),
                    Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F => $this->cursorPosition = min(mb_strlen($this->jumpToPage), $this->cursorPosition + 1),
                    Key::HOME, Key::CTRL_A => $this->cursorPosition = 0,
                    Key::END, Key::CTRL_E => $this->cursorPosition = mb_strlen($this->jumpToPage),
                    Key::DELETE => $this->jumpToPage = mb_substr($this->jumpToPage, 0, $this->cursorPosition) . mb_substr($this->jumpToPage, $this->cursorPosition + 1),
                    default     => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->page = (int) $this->jumpToPage;

                    $this->jumpToPage = '';

                    $this->clearListeners();
                    $this->state = 'select';
                    $this->listenForHotkeys();

                    return;
                } elseif ($key === Key::BACKSPACE || $key === Key::CTRL_H) {
                    if ($this->cursorPosition === 0) {
                        return;
                    }

                    $this->jumpToPage = mb_substr($this->jumpToPage, 0, $this->cursorPosition - 1) . mb_substr($this->jumpToPage, $this->cursorPosition);
                    $this->cursorPosition--;
                } elseif (ord($key) >= 32) {
                    $this->jumpToPage = mb_substr($this->jumpToPage, 0, $this->cursorPosition) . $key . mb_substr($this->jumpToPage, $this->cursorPosition);
                    $this->cursorPosition++;
                }
            }
        });
    }

    protected function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
        $this->index = 0;
    }

    protected function nextPage(): void
    {
        $this->page = min($this->totalPages, $this->page + 1);
        $this->index = 0;
    }

    public function visible(): array
    {
        if ($this->query !== '') {
            $filtered = array_filter($this->rows, function ($row) {
                return str_contains(mb_strtolower(implode(' ', $row)), mb_strtolower($this->query));
            });

            $this->totalPages = (int) ceil(count($filtered) / $this->perPage);

            return array_slice($filtered, ($this->page - 1) * $this->perPage, $this->perPage);
        }

        $this->totalPages = (int) ceil(count($this->rows) / $this->perPage);

        return array_slice($this->rows, ($this->page - 1) * $this->perPage, $this->perPage);
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): array
    {
        return $this->visible()[$this->index];
    }

    /**
     * Get the entered value with a virtual cursor.
     */
    public function valueWithCursor(int $maxWidth): string
    {
        if ($this->query === '') {
            return $this->dim($this->addCursor('', 0, $maxWidth));
        }

        return $this->addCursor($this->query, $this->cursorPosition, $maxWidth);
    }

    public function jumpValueWithCursor(int $maxWidth): string
    {
        if ($this->jumpToPage === '') {
            return $this->dim($this->addCursor('', 0, $maxWidth));
        }

        return $this->addCursor($this->jumpToPage, $this->cursorPosition, $maxWidth);
    }
}
