<?php

namespace App\Providers;

use App\Replay\ReplayGateway;
use App\Replay\ReplayScripts;
use Fissible\Verdict\Approvals\ApproverAudience;
use Fissible\Verdict\Context\DataClass;
use Fissible\Verdict\Context\ReleasePolicy;
use Fissible\Verdict\Context\Trust;
use Fissible\Verdict\VerdictManager;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ReplayScripts::class, fn (): ReplayScripts => ReplayScripts::default());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Replay by default (#237 design): the app-owned gateway substitutes only
        // the model step of the laravel/ai pipeline. Live mode is ordinary provider
        // configuration — one env change away (DEMO_MODE=live).
        if (config('demo.mode') === 'replay') {
            Ai::textProvider((string) config('ai.default'))
                ->useTextGateway(new ReplayGateway($this->app->make(ReplayScripts::class)));
        }

        // Approver provenance disclosure (ADR 0026): Verdict ships the route
        // vocabulary but deliberately registers no policy — what an approver may
        // see is the application's decision. Without this, the approval screen
        // shows no provenance for the proposals a human authorizes, and
        // verdict:validate says so. Internal data over any trust level is right
        // for this app: everything here is synthetic demo data, and the approval
        // walkthrough exists to SHOW the provenance an approver gets.
        $this->app->make(VerdictManager::class)->releasePolicy(
            ReleasePolicy::between(ApproverAudience::source(), ApproverAudience::destination())
                ->allow(DataClass::Internal)
                ->whenTrustIs(Trust::Untrusted, Trust::Trusted),
        );
    }
}
