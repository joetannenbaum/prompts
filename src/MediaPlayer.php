<?php

namespace Laravel\Prompts;

use Illuminate\Support\Collection;

class MediaPlayer extends Prompt
{
    use Concerns\TypedValue;

    public string $query = '';

    public array $results = [];

    public int $resultIndex = 0;

    public array $lyrics = [];

    public int $lyricsIndex = 0;

    public int $spinnerCount = 0;

    public bool $playing = false;

    public int $currentTrackIndex;

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
                    Key::DELETE => $this->query = mb_substr($this->query, 0, $this->cursorPosition) . mb_substr($this->query, $this->cursorPosition + 1),
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

                    $this->query = mb_substr($this->query, 0, $this->cursorPosition - 1) . mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition--;
                } elseif (ord($key) >= 32) {
                    $this->query = mb_substr($this->query, 0, $this->cursorPosition) . $key . mb_substr($this->query, $this->cursorPosition);
                    $this->cursorPosition++;
                }
            }
        });
    }

    protected function handlePlayPause()
    {
        if ($this->playing) {
            $this->playing = false;
            exec('spotify pause');
        } else if (($this->currentTrackIndex ?? -1) === $this->resultIndex) {
            $this->playing = true;
            exec('spotify play');
        } else {
            $this->playing = true;
            $this->currentTrackIndex = $this->resultIndex;
            exec('spotify play uri ' . $this->results[$this->currentTrackIndex]['url']);
        }
    }

    protected function showPlayingIndicator()
    {
        while (true) {
            $this->spinnerCount++;

            $fh = fopen('php://stdin', 'r');
            $read = [$fh];
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 0) === 1) {
                fclose($fh);
                break;
            }

            fclose($fh);

            $this->render();
            usleep(100_000);
        }
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
            [
                'artist' => 'Noah Kahan',
                'title' => 'Northern Attitude',
                'key' => 'northern-attitude',
                'url' => 'https://open.spotify.com/track/4O2rRsoSPb5aN7N3tG6Y3v?si=4762b991973841c6',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'Stick Season',
                'key' => 'stick-season',
                'url' => 'https://open.spotify.com/track/0mflMxspEfB0VbI1kyLiAv?si=6021de620f1e487f',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'All My Love',
                'key' => 'all-my-love',
                'url' => 'https://open.spotify.com/track/7ByxizhA4GgEf7Sxomxhze?si=8b801627660f48e3',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'She Calls Me Back',
                'key' => 'she-calls-me-back',
                'url' => 'https://open.spotify.com/track/1LvU6IFqQnXOIwJyBDb2io?si=d8b910a7354349d2',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'Come Over',
                'key' => 'come-over',
                'url' => 'https://open.spotify.com/track/2NmaDAnnP9zspaHLc5aSjb?si=4b70d74235154d2f',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'New Perspective',
                'key' => 'new-perspective',
                'url' => 'https://open.spotify.com/track/1M39ETXmej4g9EMSeXPUgj?si=7d1a78311e74404a',
            ],
            [
                'artist' => 'Noah Kahan',
                'title' => 'Everywhere, Everything',
                'key' => 'everywhere-everything',
                'url' => 'https://open.spotify.com/track/32iNr3J93tqFkxaMYwdRYi?si=b7e0c6014226456d',
            ],
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

        $this->lyrics = explode(PHP_EOL, file_get_contents(__DIR__ . '/../lyrics/' . $result['key'] . '.txt'));

        $this->lyricsIndex = 0;

        $this->state = 'reading';

        $this->playing = false;

        $this->on('key', function ($key) {
            if ($key[0] === "\e" || in_array($key, [Key::CTRL_B, Key::CTRL_F, Key::CTRL_A, Key::CTRL_E])) {
                match ($key) {
                    Key::DOWN, Key::DOWN_ARROW, Key::CTRL_B => $this->lyricsIndex = min(count($this->lyrics) - 1, $this->lyricsIndex + 1),
                    Key::UP, Key::UP_ARROW, Key::CTRL_F => $this->lyricsIndex = max(0, $this->lyricsIndex - 1),
                    Key::LEFT, Key::LEFT_ARROW => $this->selectFromResults(),
                    default => null,
                };
            }

            // Keys may be buffered.
            foreach (mb_str_split($key) as $key) {
                if ($key === Key::ENTER) {
                    $this->clearListeners();
                    $this->loadLyrics();

                    return;
                }

                if ($key === 'p') {
                    $this->handlePlayPause();
                    $this->showPlayingIndicator();
                } else if ($this->playing) {
                    $this->showPlayingIndicator();
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
