<?php

declare(strict_types=1);

it('performs basic assertions', function (): void {
    expect(true)->toBeTrue();
    expect(1)->toBeOne();
});

it('can work with arrays', function (): void {
    $array = ['name' => 'Dental Clinic', 'version' => '1.0.0'];

    expect($array)
        ->toBeArray()
        ->toHaveKeys(['name', 'version'])
        ->name->toBe('Dental Clinic');
});
