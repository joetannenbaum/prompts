<?php

namespace Laravel\Prompts;

class ConfirmPrompt extends Prompt
{
    /**
     * Whether the prompt has been confirmed.
     */
    public bool $confirmed;

    /**
     * Create a new ConfirmPrompt instance.
     */
    public function __construct(
        public string $label,
        bool $default = true,
        public string $yes = 'Yes',
        public string $no = 'No',
    ) {
        $this->confirmed = $default;

        $this->on('key', fn ($key) => match ($key) {
            'y' => $this->confirmed = true,
            'n' => $this->confirmed = false,
            Key::UP, Key::DOWN, Key::LEFT, Key::RIGHT, 'h', 'j', 'k', 'l' => $this->confirmed = ! $this->confirmed,
            default => null,
        });
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return $this->confirmed;
    }

    /**
     * Get the label of the selected option.
     */
    public function label(): string
    {
        return $this->confirmed ? $this->yes : $this->no;
    }
}
