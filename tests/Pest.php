<?php

declare(strict_types=1);
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Architecture');
uses(TestCase::class)->in('Unit');

require_once __DIR__.'/Feature/Modules/Booking/BookingTestHelpers.php';
require_once __DIR__.'/Feature/Modules/Payments/PaymentsTestHelpers.php';
