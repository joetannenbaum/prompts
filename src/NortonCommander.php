<?php

namespace Laravel\Prompts;

class NortonCommander extends Prompt
{
    use Concerns\TypedValue;

    public array $panels = [];

    public function __construct()
    {
        $this->panels = [
            new CommanderPanel(getcwd() . '/vendor'),
            new CommanderPanel(getcwd() . '/vendor/illuminate/contracts'),
        ];

        $this->listenForHotkeys();
    }

    protected function listenForHotkeys(): void
    {
        // $this->on('key', function ($key) {
        //     if ($key[0] === "\e") {
        //         match ($key) {
        //             Key::UP, Key::UP_ARROW => $this->index = max(0, $this->index - 1),
        //             Key::DOWN, Key::DOWN_ARROW => $this->index = min($this->perPage - 1, $this->index + 1),
        //             Key::RIGHT, Key::RIGHT_ARROW => $this->nextPage(),
        //             Key::LEFT, Key::LEFT_ARROW => $this->previousPage(),
        //             default => null,
        //         };

        //         return;
        //     }

        //     // Keys may be buffered.
        //     foreach (mb_str_split($key) as $key) {
        //         if ($key === Key::ENTER) {
        //             $this->submit();

        //             return;
        //         } else {
        //             match ($key) {
        //                 'q' => $this->quit(),
        //                 '/' => $this->search(),
        //                 'j' => $this->jump(),
        //                 default => null,
        //             };
        //         }
        //     }
        // });
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    protected function quit(): void
    {
        $this->state = 'cancel';
        exit;
    }
}
