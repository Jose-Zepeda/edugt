<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Gamification\Contracts\XpLedger;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Gamification\Models\XpLedgerEntry;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative: única fuente de XP (RB-10).
 * Append-only: el balance se deriva; el perfil materializa el total.
 */
final class XpLedgerService implements XpLedger
{
    public function award(
        int $userId,
        int $delta,
        string $source,
        string $reason,
        ?array $metadata = null,
    ): void {
        if ($delta === 0) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $delta, $source, $reason, $metadata): void {
            $profile = $this->profileFor($userId);

            XpLedgerEntry::query()->create([
                'user_id' => $userId,
                'delta' => $delta,
                'source' => $source,
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            $newTotal = max(0, $profile->xp_total + $delta);

            $profile->forceFill([
                'xp_total' => $newTotal,
                'xp_level' => $this->levelFor($newTotal),
                'xp_updated_at' => now(),
            ])->save();
        });
    }

    public function currentTotal(int $userId): int
    {
        return $this->profileFor($userId)->xp_total;
    }

    public function levelFor(int $xpTotal): int
    {
        return max(1, intdiv($xpTotal, 100) + 1);
    }

    /** Perfil 1:1 — se crea on-demand (los invitados no existen en gamificación) */
    private function profileFor(int $userId): UserXpProfile
    {
        return UserXpProfile::query()->firstOrCreate(
            ['user_id' => $userId],
            ['xp_total' => 0, 'xp_level' => 1],
        );
    }
}
