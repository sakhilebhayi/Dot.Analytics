<?php

namespace App\Console\Commands;

use App\Mail\LibraryDigest;
use App\Models\Library\Preference;
use App\Models\User;
use App\Services\Library\DigestBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLibraryDigests extends Command
{
    protected $signature = 'library:digest {frequency : daily or weekly}';

    protected $description = 'Email the library digest to people who asked for it, and only when there is something to say';

    public function handle(DigestBuilder $builder): int
    {
        $frequency = (string) $this->argument('frequency');

        if (! in_array($frequency, ['daily', 'weekly'], true)) {
            $this->error('Frequency must be daily or weekly.');

            return self::INVALID;
        }

        $sent = 0;
        $skipped = 0;

        Preference::where('digest', $frequency)->chunkById(100, function ($prefs) use ($builder, $frequency, &$sent, &$skipped) {
            foreach ($prefs as $pref) {
                $user = User::find($pref->user_id);
                $digest = $user ? $builder->for($user) : null;

                if ($digest === null) {
                    $skipped++;

                    continue;
                }

                try {
                    Mail::to($user)->send(new LibraryDigest($user, $digest, $frequency));
                    $pref->update(['last_digest_at' => now()]);
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("Digest to user {$user->id} failed: ".get_class($e));
                }
            }
        });

        $this->line("{$sent} sent, {$skipped} had nothing to say");

        return self::SUCCESS;
    }
}
