<?php

namespace Laravel\Prompts;

class CommanderPanel
{
    public string $currentDir;

    public array $files = [];

    public int $page = 1;

    public int $selectedIndex = 0;

    public function __construct(string $startingDir = null)
    {
        $this->currentDir = $startingDir ?? getcwd() . '/vendor';
        $this->files = $this->getFiles();
    }

    public function getFiles(): array
    {
        return collect(scandir($this->currentDir))
            ->reject(fn ($file) => in_array($file, ['.', '..']))
            ->map(fn ($file) => [
                'name'      => $file,
                'type'      => is_dir($this->currentDir . '/' . $file) ? 'dir' : 'file',
                'extension' => pathinfo($file, PATHINFO_EXTENSION),
                'filename'  => pathinfo($file, PATHINFO_FILENAME),
            ])
            ->sortBy(fn ($file) => $file['type'] === 'dir' ? 0 : 1)
            ->values()
            ->all();
    }

    public function visible(int $perChunk)
    {
        return collect($this->files)
            ->chunk($perChunk)
            ->slice($this->page - 1, $this->page + 2);
    }
}
