<?php

namespace Laravel\Prompts;

use Illuminate\Support\Collection;

class Kanban extends Prompt
{
    public array $items = [
        [
            [
                'title' => 'Make Kanban Board',
                'description' => 'But in the terminal?',
            ],
            [
                'title' => 'Eat Pizza',
                'description' => '(Whole pie).',
            ]
        ],
        [
            [
                'title' => 'Get Milk',
                'description' => 'From the store (whole).',
            ],
            [
                'title' => 'Learn Go',
                'description' => 'Charm CLI looks dope.',
            ],
            [
                'title' => 'Submit Statamic PR',
                'description' => 'Nocache tag fix.',
            ],
        ],
        [
            [
                'title' => 'Wait Patiently',
                'description' => 'For the next prompt.',
            ],
        ],
    ];

    public int $itemIndex = 0;

    public int $columnIndex = 0;

    public string $deleteAction = 'confirm';

    /**
     * Create a new Table instance.
     *
     * @param  array<int, string|array<int, string>>|Collection<int, string|array<int, string>>  $headers
     * @param  array<int, array<int, string>>|Collection<int, array<int, string>>  $rows
     *
     * @phpstan-param ($rows is null ? list<list<string>>|Collection<int, list<string>> : list<string|list<string>>|Collection<int, string|list<string>>) $headers
     */
    public function __construct()
    {
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
                    Key::UP, Key::UP_ARROW => $this->itemIndex = max(0, $this->itemIndex - 1),
                    Key::DOWN, Key::DOWN_ARROW => $this->itemIndex = min(count($this->items[$this->columnIndex]) - 1, $this->itemIndex + 1),
                    Key::RIGHT, Key::RIGHT_ARROW => $this->nextColumn(),
                    Key::LEFT, Key::LEFT_ARROW => $this->previousColumn(),
                    Key::DELETE => $this->pendingDelete(),
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->moveCurrentItem();

                    return;
                } else {
                    match ($key) {
                        'q' => $this->quit(),
                        'n' => $this->addNewItem(),
                        default => null,
                    };
                }
            }
        });
    }

    protected function nextColumn(): void
    {
        $this->columnIndex = min(count($this->items) - 1, $this->columnIndex + 1);
        $this->itemIndex = 0;
    }

    protected function previousColumn(): void
    {
        $this->columnIndex = max(0, $this->columnIndex - 1);
        $this->itemIndex = 0;
    }

    protected function addNewItem(): void
    {
        $this->clearListeners();
        $this->capturePreviousNewLines();
        $this->resetCursorPosition();
        $this->eraseDown();

        $title = text('Title', 'Title of task');

        $description = text('Description', 'Description of task');

        $this->items[$this->columnIndex][] = [
            'title' => $title,
            'description' => $description,
        ];

        $this->listenForHotkeys();
        $this->prompt();
    }

    protected function pendingDelete()
    {
        $this->clearListeners();
        $this->deleteAction = 'confirm';
        $this->state = 'pendingDelete';

        $this->on('key', function ($key) {
            if ($key[0] === "\e") {
                match ($key) {
                    Key::RIGHT, Key::RIGHT_ARROW,
                    Key::LEFT, Key::LEFT_ARROW => $this->deleteAction === 'confirm' ? $this->deleteAction = 'cancel' : $this->deleteAction = 'confirm',
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    if ($this->deleteAction === 'confirm') {
                        unset($this->items[$this->columnIndex][$this->itemIndex]);
                    }

                    $this->itemIndex = max(0, $this->itemIndex - 1);

                    $this->clearListeners();
                    $this->state = 'anything';
                    $this->listenForHotkeys();

                    return;
                }
            }
        });
    }

    protected function moveCurrentItem(): void
    {
        $newColumnIndex = $this->columnIndex + 1;

        if ($newColumnIndex >= count($this->items)) {
            $newColumnIndex = 0;
        }

        $this->items[$newColumnIndex][] = $this->items[$this->columnIndex][$this->itemIndex];

        unset($this->items[$this->columnIndex][$this->itemIndex]);

        $this->items[$this->columnIndex] = array_values($this->items[$this->columnIndex]);

        $this->itemIndex = max(0, $this->itemIndex - 1);
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }
}
