<?php

use Illuminate\Support\Facades\Auth as AuthFacade;

if (! function_exists('clean_guard')) {
    /**
     * Limpia el RequestGuard cacheado de Sanctum entre requests dentro del mismo test.
     * Sin esto, el segundo request del test hereda el usuario del primero.
     */
    function clean_guard(): void
    {
        auth()->forgetGuards();
        AuthFacade::guard('sanctum')->forgetUser();
    }
}
