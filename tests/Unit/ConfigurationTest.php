<?php

// C7 (D-184): system timestamps in UTC; interface locale Italian.
it('keeps the application timezone in UTC', function () {
    $config = require __DIR__.'/../../config/app.php';

    expect($config['timezone'])->toBe('UTC');
});
