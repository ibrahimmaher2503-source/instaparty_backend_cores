<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Architecture');
uses(TestCase::class)->in('Unit');

require_once __DIR__.'/Feature/Modules/Booking/BookingTestHelpers.php';
require_once __DIR__.'/Feature/Modules/Payments/PaymentsTestHelpers.php';
require_once __DIR__.'/Feature/Modules/Settlement/SettlementTestHelpers.php';
require_once __DIR__.'/Feature/Modules/Communication/CommunicationTestHelpers.php';
