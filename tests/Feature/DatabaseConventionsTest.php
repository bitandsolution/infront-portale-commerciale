<?php

use Illuminate\Support\Facades\DB;

// C1 (D-184): MariaDB 11.8 with utf8mb4 and the uca1400 collation.
it('runs on MariaDB 11.8 with utf8mb4_uca1400_ai_ci', function () {
    $version = (string) DB::selectOne('SELECT VERSION() AS v')->v;

    expect($version)->toContain('MariaDB')
        ->and($version)->toStartWith('11.8')
        ->and(DB::connection()->getConfig('collation'))->toBe('utf8mb4_uca1400_ai_ci');
});
