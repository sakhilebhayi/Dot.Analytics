<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Provenance for generated intelligence (which snapshots / window were used) and
 *   a human-readable reason when an engine run or briefing did not produce output.
 * - A dedicated, encrypted webhook_secret column; existing plaintext secrets that
 *   lived in data_sources.config are moved into it, and copies that the audit
 *   observer made into audit_logs are redacted in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cross_platform_insights', function (Blueprint $table) {
            $table->json('provenance')->nullable()->after('supporting_metrics');
        });

        Schema::table('intelligence_engine_runs', function (Blueprint $table) {
            $table->text('status_reason')->nullable()->after('status');
            $table->json('provenance')->nullable()->after('status_reason');
        });

        Schema::table('executive_briefings', function (Blueprint $table) {
            $table->text('status_reason')->nullable()->after('status');
            $table->json('provenance')->nullable()->after('status_reason');
        });

        Schema::table('data_sources', function (Blueprint $table) {
            $table->text('webhook_secret')->nullable()->after('config');
        });

        $this->moveLegacySecrets();
        $this->redactAuditHistory();
    }

    public function down(): void
    {
        Schema::table('data_sources', fn (Blueprint $t) => $t->dropColumn('webhook_secret'));
        Schema::table('executive_briefings', fn (Blueprint $t) => $t->dropColumn(['status_reason', 'provenance']));
        Schema::table('intelligence_engine_runs', fn (Blueprint $t) => $t->dropColumn(['status_reason', 'provenance']));
        Schema::table('cross_platform_insights', fn (Blueprint $t) => $t->dropColumn('provenance'));
    }

    private function moveLegacySecrets(): void
    {
        DB::table('data_sources')->whereNotNull('config')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $config = json_decode($row->config, true);
                if (! is_array($config) || ! isset($config['webhook_secret'])) {
                    continue;
                }

                $secret = $config['webhook_secret'];
                unset($config['webhook_secret']);

                DB::table('data_sources')->where('id', $row->id)->update([
                    'webhook_secret' => is_string($secret) && $secret !== '' ? Crypt::encryptString($secret) : null,
                    'config' => json_encode($config),
                ]);
            }
        });
    }

    private function redactAuditHistory(): void
    {
        $redact = function (mixed $value) use (&$redact) {
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                return is_array($decoded) && str_contains($value, 'webhook_secret')
                    ? json_encode($redact($decoded))
                    : $value;
            }
            if (! is_array($value)) {
                return $value;
            }
            foreach ($value as $key => $item) {
                $value[$key] = is_string($key) && stripos($key, 'webhook_secret') !== false
                    ? '[redacted]'
                    : $redact($item);
            }

            return $value;
        };

        DB::table('audit_logs')
            ->where(fn ($q) => $q->where('old_values', 'like', '%webhook_secret%')->orWhere('new_values', 'like', '%webhook_secret%'))
            ->chunkById(100, function ($rows) use ($redact) {
                // audit_logs is append-only at the model layer; this deliberately
                // bypasses it once to scrub leaked secrets out of history.
                foreach ($rows as $row) {
                    DB::table('audit_logs')->where('id', $row->id)->update([
                        'old_values' => $row->old_values ? json_encode($redact(json_decode($row->old_values, true))) : null,
                        'new_values' => $row->new_values ? json_encode($redact(json_decode($row->new_values, true))) : null,
                    ]);
                }
            });
    }
};
