<?php

declare(strict_types=1);

it('renders the spotlight mount on panel pages for authenticated users', function () {
    $this->actingAs(makeUser());

    $this->get('/admin')
        ->assertOk()
        ->assertSee('filamentSpotlight', escape: false);
});

it('does not render the spotlight mount for guests', function () {
    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('filamentSpotlight', escape: false);
});
