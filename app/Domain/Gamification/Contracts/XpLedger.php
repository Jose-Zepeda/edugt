<?php

namespace App\Domain\Gamification\Contracts;

/**
 * DIP §4: lógica central de XP — el libro contable append-only.
 * Implementación: XpLedgerService (server-authoritative, RB-10).
 */
interface XpLedger
{
    /**
     * Otorga XP registrando en el ledger y actualizando el perfil.
     * RB-06/15: delta <= 0 para re-multiplicadores vía líneas negativas; floor aquí.
     */
    public function award(
        int $userId,
        int $delta,
        string $source,
        string $reason,
        ?array $metadata = null,
    ): void;

    public function currentTotal(int $userId): int;

    /** Nivel XP derivado: nivel N ⇔ XP acumulado >= 100 * N ( tendrá 100 por nivel) */
    public function levelFor(int $xpTotal): int;
}
