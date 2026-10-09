<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tokens carry their own expires_at (SanctumTokenIssuer), so an expired one is
// already refused; this only deletes the dead rows a day after they expire.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
