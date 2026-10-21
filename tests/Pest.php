<?php

use Tests\TestCase;

/*
| Pest configuration. Feature tests run on MariaDB 11.8 (CLAUDE.md §11).
*/

pest()->extend(TestCase::class)->in('Feature');
