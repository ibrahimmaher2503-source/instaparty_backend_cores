<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Casts\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;

it('casts minor units to a Money object on get', function () {
    $cast = new MoneyCast('price');
    $model = Mockery::mock(Model::class);

    $money = $cast->get($model, 'price', null, [
        'price_minor' => 10000,
        'price_currency' => 'EGP',
    ]);

    expect($money)->toBeInstanceOf(Money::class)
        ->and($money->getMinorAmount()->toInt())->toBe(10000)
        ->and((string) $money->getCurrency())->toBe('EGP');
})->group('shared');

it('serialises a Money object to minor units on set', function () {
    $cast = new MoneyCast('price');
    $model = Mockery::mock(Model::class);
    $money = Money::ofMinor(5050, 'EGP');

    $result = $cast->set($model, 'price', $money, []);

    expect($result)->toBe([
        'price_minor' => 5050,
        'price_currency' => 'EGP',
    ]);
})->group('shared');

it('throws when a float is provided', function () {
    $cast = new MoneyCast('price');
    $model = Mockery::mock(Model::class);

    $cast->set($model, 'price', 99.99, ['price_currency' => 'EGP']);
})->throws(InvalidArgumentException::class)->group('shared');

it('throws when minor or currency is null', function () {
    $cast = new MoneyCast('price');
    $model = Mockery::mock(Model::class);

    $cast->get($model, 'price', null, []);
})->throws(UnexpectedValueException::class)->group('shared');
