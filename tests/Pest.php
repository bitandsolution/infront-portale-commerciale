<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Pest configuration. Feature tests run on MariaDB 11.8 (CLAUDE.md §11), each in a transaction.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
