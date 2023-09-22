<?php

namespace Laravel\Prompts;

use Closure;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class Progress extends Prompt
{
    /**
     * The current progress bar item count.
     */
    public int $progress = 0;

    /**
     * The total number of items.
     */
    public int $total = 0;

    /**
     * The label for the current item.
     */
    public string $itemLabel = '';

    /**
     * The items to iterate over.
     *
     * @var array<mixed>
     */
    public array $items;

    /**
     * The original value of pcntl_async_signals
     */
    protected bool $originalAsync;

    /**
     * Create a new ProgressBar instance.
     *
     * @param  array<mixed>|Collection<int, mixed>  $items
     * @param  ?Closure(string): ?string  $callback
     */
    public function __construct(public string $label, array|Collection $items, public ?Closure $callback = null)
    {
        $this->items = $items instanceof Collection ? $items->all() : $items;
        $this->total = count($this->items);

        if ($this->total === 0) {
            throw new InvalidArgumentException('Progress bar must have at least one item.');
        }
    }

    /**
     * Display the progress bar.
     */
    public function display(): static|null
    {
        $this->capturePreviousNewLines();

        if ($this->callback === null) {
            // They want to control the progress bar manually
            return $this;
        }

        $this->start();

        try {
            foreach ($this->items as $item) {
                $result = ($this->callback)($item);
                $this->advance(is_scalar($result) ? (string) $result : '');
            }
        } catch (Throwable $e) {
            $this->state = 'error';
            $this->render();
            $this->restoreCursor();
            $this->resetTerminal();

            throw $e;
        }

        if ($this->itemLabel !== '') {
            // Just pause for one moment to show the final item label
            // so it doesn't look like it was skipped
            usleep(250_000);
        }

        $this->finish();

        return null;
    }

    /**
     * Start the progress bar.
     */
    public function start(): void
    {
        if (function_exists('pcntl_signal')) {
            $this->originalAsync = pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => exit());
        }

        $this->state = 'active';
        $this->hideCursor();
        $this->render();
    }

    /**
     * Advance the progress bar.
     */
    public function advance(string $itemLabel = ''): void
    {
        $this->itemLabel = $itemLabel;
        $this->progress++;
        $this->render();
    }

    /**
     * Finish the progress bar.
     */
    public function finish(): void
    {
        $this->state = 'submit';
        $this->render();
        $this->restoreCursor();
        $this->resetTerminal();
    }

    /**
     * Get the completion percentage.
     */
    public function percentage(): int|float
    {
        return $this->progress / $this->total;
    }

    /**
     * Disable prompting for input.
     *
     * @throws \RuntimeException
     */
    public function prompt(): never
    {
        throw new RuntimeException('Progress Bar cannot be prompted.');
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return true;
    }

    /**
     * Reset the terminal.
     */
    public function resetTerminal(): void
    {
        if (isset($this->originalAsync)) {
            pcntl_async_signals($this->originalAsync);
            pcntl_signal(SIGINT, SIG_DFL);
        }
    }

    /**
     * Restore the cursor.
     */
    public function __destruct()
    {
        $this->restoreCursor();
    }
}
