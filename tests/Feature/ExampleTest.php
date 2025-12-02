<?php

declare(strict_types=1);

it('returns a successful response from homepage', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200);
});
