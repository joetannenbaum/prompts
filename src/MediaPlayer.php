<?php

namespace Laravel\Prompts;

class MediaPlayer extends Prompt
{
    use Concerns\TypedValue;

    public string $query = '';

    public array $results = [];

    public int $resultIndex = 0;

    public array $lyrics = [];

    public int $lyricsIndex = 0;

    public int $spinnerCount = 0;

    public function __construct()
    {
        $this->search();
    }

    protected function quit(): void
    {
        $this->state = 'cancel';
        exit;
    }

    protected function search(): void
    {
        $this->state = 'search';
        $this->clearListeners();

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {

                match ($key) {
                    Key::LEFT, Key::LEFT_ARROW, Key::CTRL_B => $this->cursorPosition = max(0, $this->cursorPosition - 1),
                    Key::RIGHT, Key::RIGHT_ARROW, Key::CTRL_F => $this->cursorPosition = min(mb_strlen($this->query), $this->cursorPosition + 1),
                    Key::HOME, Key::CTRL_A => $this->cursorPosition = 0,
                    Key::END, Key::CTRL_E => $this->cursorPosition = mb_strlen($this->query),
                    Key::DELETE => $this->query = mb_substr($this->query, 0, $this->cursorPosition).mb_substr($this->query, $this->cursorPosition + 1),
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->clearListeners();
                    $this->runSearch();

                    return;
                } elseif ($key === Key::BACKSPACE || $key === Key::CTRL_H) {
                    if ($this->cursorPosition === 0) {
                        return;
                    }

                    $this->query = mb_substr($this->query, 0, $this->cursorPosition - 1).mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition--;
                } elseif (ord($key) >= 32) {
                    $this->query = mb_substr($this->query, 0, $this->cursorPosition).$key.mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition++;
                }
            }
        });
    }

    protected function runSearch()
    {
        $this->state = 'searching';

        $i = 0;

        while ($i < 15) {
            $this->spinnerCount++;
            $this->render();

            usleep(100_000);

            $i++;
        }

        $this->results = [
            ['artist' => 'Noah Kahan', 'title' => 'Northern Attitude', 'key' => 'northern-attitude'],
            ['artist' => 'Noah Kahan', 'title' => 'Stick Season', 'key' => 'stick-season'],
            ['artist' => 'Noah Kahan', 'title' => 'All My Love', 'key' => 'all-my-love'],
            ['artist' => 'Noah Kahan', 'title' => 'She Calls Me Back', 'key' => 'she-calls-me-back'],
            ['artist' => 'Noah Kahan', 'title' => 'Come Over', 'key' => 'come-over'],
            ['artist' => 'Noah Kahan', 'title' => 'New Perspective', 'key' => 'new-perspective'],
            ['artist' => 'Noah Kahan', 'title' => 'Everywhere, Everything', 'key' => 'everywhere-everything'],
        ];

        $this->selectFromResults();
    }

    protected function selectFromResults()
    {
        $this->state = 'results';

        $this->clearListeners();

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {

                match ($key) {
                    Key::DOWN, Key::DOWN_ARROW, Key::CTRL_B => $this->resultIndex = min(count($this->results) - 1, $this->resultIndex + 1),
                    Key::UP, Key::UP_ARROW, Key::CTRL_F => $this->resultIndex = max(0, $this->resultIndex - 1),
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->clearListeners();
                    $this->loadLyrics();

                    return;
                }
            }
        });
    }

    public function visibleLyrics(int $availableLines): array
    {
        return array_slice($this->lyrics, $this->lyricsIndex, $availableLines);
    }

    protected function loadLyrics()
    {
        $result = $this->results[$this->resultIndex];

        $this->state = 'loadingLyrics';

        $i = 0;

        while ($i < 15) {
            $this->spinnerCount++;
            $this->render();

            usleep(100_000);

            $i++;
        }

        $this->lyrics = explode(PHP_EOL, file_get_contents(__DIR__.'/../lyrics/'.$result['key'].'.txt'));

        $this->lyricsIndex = 0;

        $this->state = 'reading';

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {
                match ($key) {
                    Key::DOWN, Key::DOWN_ARROW, Key::CTRL_B => $this->lyricsIndex = min(count($this->lyrics) - 1, $this->lyricsIndex + 1),
                    Key::UP, Key::UP_ARROW, Key::CTRL_F => $this->lyricsIndex = max(0, $this->lyricsIndex - 1),
                    Key::LEFT, Key::LEFT_ARROW => $this->selectFromResults(),
                    default => null,
                };

                return;
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->clearListeners();
                    $this->loadLyrics();

                    return;
                }
            }
        });
    }

    /**
     * Get the value of the prompt.
     */
    public function value(): bool
    {
        return true;
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
}
