<?php

namespace Database\Seeders;

use App\Models\MetricDefinition;
use App\Services\Metrics\MetricCatalogue;
use Illuminate\Database\Seeder;

/**
 * Syncs metric_definitions with the snapshot contract's catalogue
 * (config/metrics.php): one definition per metric a real Dot platform may report.
 *
 * Definitions are only a label and a unit. They carry no numbers, and seeding
 * them does not create any computed metric. The computation creates a missing
 * definition itself, so this seeder is a convenience, not a prerequisite.
 *
 * Definitions seeded before the contract described platforms that do not exist
 * (dot.fleet, dot.crm ...); those that never gained a value are removed.
 */
class MetricDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = new MetricCatalogue;
        $count = 0;

        foreach ($catalogue->platforms() as $platform => $definition) {
            foreach (array_keys($definition['metrics']) as $key) {
                MetricDefinition::updateOrCreate(
                    ['key' => $catalogue->fullKey($platform, $key)],
                    $catalogue->definitionAttributes($platform, $key),
                );
                $count++;
            }
        }

        $removed = MetricDefinition::query()
            ->where('source_platform', 'like', 'dot.%')
            ->whereDoesntHave('computedMetrics', fn ($q) => $q->withoutGlobalScopes())
            ->delete();

        $this->command?->info("Synced {$count} metric definitions across ".count($catalogue->platforms())." platforms; removed {$removed} legacy definition(s).");
    }
}
