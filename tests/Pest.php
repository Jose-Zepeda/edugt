<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

require __DIR__.'/helpers.php';

/*
|--------------------------------------------------------------------------
| Isolation: limpiar guards cacheados entre tests
|--------------------------------------------------------------------------
| Sanctum guarda el usuario resuelto en el guard durante un request; sin
| esta limpieza, un test puede "heredar" el usuario del test anterior en el
| mismo proceso y autenticar con el usuario equivocado.
*/

beforeEach(function () {
    auth()->forgetGuards();
    \Illuminate\Support\Facades\Auth::guard('sanctum')->forgetUser();
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));
