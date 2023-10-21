<?php

use function Laravel\Prompts\kanban;

require __DIR__ . '/../vendor/autoload.php';

$value = kanban();

var_dump($value);
