<?php

namespace App\Providers;

use App\Domain\Auth\Actions\SocialiteGoogleGateway;
use App\Domain\Auth\Contracts\GoogleOAuthGateway;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Audit\Services\DatabaseAuditLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // DIP: el controlador depende del contrato; Socialite es detalle de implementación
        $this->app->bind(GoogleOAuthGateway::class, SocialiteGoogleGateway::class);
        $this->app->bind(AuditLogger::class, DatabaseAuditLogger::class);
        $this->app->bind(\App\Domain\Gamification\Contracts\XpLedger::class, \App\Domain\Gamification\Services\XpLedgerService::class);
        $this->app->bind(\App\Domain\Gamification\Contracts\StreakService::class, \App\Domain\Gamification\Services\StreakService::class);
        $this->app->bind(\App\Domain\Curriculum\Contracts\UnlockEvaluator::class, \App\Domain\Curriculum\Services\UnlockEvaluatorService::class);
        $this->app->bind(\App\Domain\Gamification\Contracts\BadgeAwarder::class, \App\Domain\Gamification\Services\BadgeAwarderService::class);
        $this->app->bind(\App\Domain\Gamification\Contracts\RankingService::class, \App\Domain\Gamification\Services\RankingServiceDb::class);
        $this->app->bind(\App\Domain\Analytics\Contracts\AnalyticsQuery::class, \App\Domain\Analytics\Services\AnalyticsQueryDb::class);
        $this->app->bind(\App\Domain\Reporting\Contracts\ReportExporter::class, \App\Domain\Reporting\Services\DompdfReportExporter::class);
    }

    public function boot(): void
    {
        // Testing: limiters definidos pero ilimitados (la suite corre desde una sola IP)
        $unlimitedInTests = $this->app->environment('testing');

        // Login: 5 intentos/min por email+IP (§7/§10.3 — seguridad de la tesis)
        RateLimiter::for('login', function (Request $request) use ($unlimitedInTests) {
            if ($unlimitedInTests) {
                return Limit::none();
            }

            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
            ];
        });

        // Registro: 1 por minuto por IP (anti-abuso)
        RateLimiter::for('register', function (Request $request) use ($unlimitedInTests) {
            if ($unlimitedInTests) {
                return Limit::none();
            }

            return [
                Limit::perMinute(1)->by($request->ip()),
            ];
        });
    }
}
