<?php

namespace App\Domain\Gamification\Services;

use App\Domain\Gamification\Contracts\StreakService as StreakServiceContract;
use App\Domain\Gamification\Models\UserXpProfile;
use App\Domain\Users\Models\User;
use Illuminate\Support\Carbon;

/**
 * RB-16: la racha se mide en día local America/Guatemala.
 * markActivity es idempotente por día; rompe la racha si hubo gap ≥ 2 días.
 */
final class StreakService implements StreakServiceContract
{
    public const TZ = 'America/Guatemala';

    public function markActivity(int $userId, ?Carbon $localDate = null): void
    {
        $today = ($localDate ?? Carbon::now(self::TZ))->toDateString();

        \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $today, $localDate): void {
            $profile = UserXpProfile::query()->where('user_id', $userId)->lockForUpdate()->first();
            if ($profile === null) {
                $profile = UserXpProfile::query()->create([
                    'user_id' => $userId, 'xp_total' => 0, 'xp_level' => 1,
                ]);
            }

            $last = $profile->streak_last_active_date;

            if ($last !== null && $last->eq(\Illuminate\Support\Carbon::parse($today))) {
                return; // idempotente: ya contó hoy
            }

            $continues = $last !== null
                && $last->eq(Carbon::parse($today)->subDay());
            $current = $continues ? $profile->streak_current + 1 : 1;

            $profile->forceFill([
                'streak_current' => $current,
                'streak_longest' => max($profile->streak_longest, $current),
                'streak_last_active_date' => $today,
            ])->save();
        });
    }

    public function current(int $userId): int
    {
        return (int) (UserXpProfile::query()->where('user_id', $userId)->value('streak_current') ?? 0);
    }

    public function longest(int $userId): int
    {
        return (int) (UserXpProfile::query()->where('user_id', $userId)->value('streak_longest') ?? 0);
    }
}
