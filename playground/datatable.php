<?php

use function Laravel\Prompts\datatable;

require __DIR__ . '/../vendor/autoload.php';

$data = file_get_contents(__DIR__ . '/datatable.json');

$value = datatable(
    ['name' => 'Name', 'email' => 'Email', 'address' => 'Address'],
    collect(json_decode($data, true))
        ->map(fn ($user) => array_merge($user, ['address' => str_replace(PHP_EOL, ' ', $user['address'])]))->all(),
);

var_dump($value);
