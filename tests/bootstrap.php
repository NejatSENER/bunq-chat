<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Using an in-memory SQLite database for all tests
define('TEST_DB_PATH', ':memory:');
