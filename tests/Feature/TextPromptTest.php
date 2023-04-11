<?php

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use function Laravel\Prompts\text;

it('returns the input', function () {
    Prompt::fake(['J', 'e', 's', 's', Key::ENTER]);

    $result = text(label: 'What is your name?');

    expect($result)->toBe('Jess');
});

it('accepts a default value', function () {
    Prompt::fake([Key::ENTER]);

    $result = text(
        label: 'What is your name?',
        default: 'Jess'
    );

    expect($result)->toBe('Jess');
});

it('validates', function () {
    Prompt::fake(['J', 'e', 's', Key::ENTER, 's', Key::ENTER])
        ->shouldReceive('write')
        ->with(Mockery::on(fn ($value) => str_contains($value, 'Invalid name.')));

    $result = text(
        label: 'What is your name?',
        validate: fn ($value) => $value !== 'Jess' ? 'Invalid name.' : '',
    );

    expect($result)->toBe('Jess');
});

it('cancels', function () {
    Prompt::fake([Key::CTRL_C])
        ->expects('write')
        ->with(Mockery::on(fn ($value) => str_contains($value, 'Cancelled.')));

    text(label: 'What is your name?');
});

test('the backspace key removes a character', function () {
    Prompt::fake(['J', 'e', 'z', Key::BACKSPACE, 's', 's', Key::ENTER]);

    $result = text(label: 'What is your name?');

    expect($result)->toBe('Jess');
});

test('the delete key removes a character', function () {
    Prompt::fake(['J', 'e', 'z', Key::LEFT, Key::DELETE, 's', 's', Key::ENTER]);

    $result = text(label: 'What is your name?');

    expect($result)->toBe('Jess');
});
