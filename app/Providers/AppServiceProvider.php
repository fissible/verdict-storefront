<?php

namespace App\Providers;

use Fissible\Verdict\Approvals\ApproverAudience;
use Fissible\Verdict\Context\DataClass;
use Fissible\Verdict\Context\ReleasePolicy;
use Fissible\Verdict\Context\Trust;
use Fissible\Verdict\VerdictManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
