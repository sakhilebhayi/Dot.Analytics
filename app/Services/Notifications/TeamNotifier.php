<?php

namespace App\Services\Notifications;

use App\Models\AnalyticsAlert;
use App\Models\CrossPlatformInsight;
use App\Models\ExecutiveBriefing;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CriticalAlertTriggered;
use App\Notifications\ExecutiveBriefingReady;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Decides whether something that just happened is worth telling a team about, and tells
 * every member of exactly that team.
 *
 * Only real things notify. The engines record no_data / skipped / failed runs and the
 * briefing job records `unavailable` briefings without producing alerts or insights, and
 * none of those reach here. What does:
 *
 *  - a critical alert that traces back to ingested data: a metric anomaly (computed from
 *    the team's own daily points, never demo data) or the alert of a critical insight whose
 *    provenance names the snapshots it was derived from;
 *  - a briefing that reached `ready`, which the job only does from real insights/metrics.
 *
 * Recipients are the whole team, viewers included: a viewer reads the intelligence layer
 * and is exactly who an alert should reach; receiving one spends no AI budget.
 *
 * Notifying is a side effect of work that already succeeded (an alert exists, a briefing
 * is written). It is therefore never allowed to fail that work: each member is attempted
 * on its own, and a failure is reported and skipped.
 */
class TeamNotifier
{
    public function criticalAlert(AnalyticsAlert $alert): int
    {
        if ($alert->severity !== 'critical' || $alert->status !== 'open' || ! $this->alertHasProvenance($alert)) {
            return 0;
        }

        $team = Team::find($alert->team_id);

        return $team ? $this->send($team, fn () => new CriticalAlertTriggered($alert)) : 0;
    }

    public function briefingReady(ExecutiveBriefing $briefing): int
    {
        $provenance = $briefing->provenance ?? [];
        $grounded = ! empty($provenance['insight_ids']) || ! empty($provenance['metric_keys']);

        if ($briefing->status !== 'ready' || trim((string) $briefing->summary) === '' || ! $grounded) {
            return 0;
        }

        $team = Team::find($briefing->team_id);

        return $team ? $this->send($team, fn () => new ExecutiveBriefingReady($briefing)) : 0;
    }

    private function alertHasProvenance(AnalyticsAlert $alert): bool
    {
        $context = $alert->context ?? [];

        if (($context['is_demo'] ?? false) === true) {
            return false;
        }

        return match ($context['source'] ?? null) {
            'metric_anomaly' => filled($context['metric_key'] ?? null) && ! empty($context['window']['values']),
            'cross_platform_intelligence' => $this->insightHasSnapshots($alert),
            default => false,
        };
    }

    private function insightHasSnapshots(AnalyticsAlert $alert): bool
    {
        $insightId = $alert->context['insight_id'] ?? null;
        if (! $insightId) {
            return false;
        }

        $insight = CrossPlatformInsight::withoutGlobalScopes()
            ->where('team_id', $alert->team_id)
            ->find($insightId);

        return ! empty($insight?->provenance['snapshot_ids']);
    }

    /** @param  callable(): Notification  $make */
    private function send(Team $team, callable $make): int
    {
        $sent = 0;

        foreach ($team->allUsers()->unique('id') as $user) {
            /** @var User $user */
            try {
                $user->notify($make());
                $sent++;
            } catch (Throwable $e) {
                // Class only: transport errors can embed hosts and credentials.
                report(new \RuntimeException('Team notification failed for one member ('.class_basename($e).').'));
            }
        }

        return $sent;
    }
}
