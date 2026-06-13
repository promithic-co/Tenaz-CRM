<?php

use Illuminate\Support\Facades\Schema;

it('does not keep the abandoned lead evaluations table', function () {
    expect(Schema::hasTable('lead_evaluations'))->toBeFalse();
});
