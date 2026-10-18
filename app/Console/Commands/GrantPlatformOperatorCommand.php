<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Platform-operator status is never grantable through the web app or mass
 * assignment; it is set here, by someone with shell access to the deployment.
 */
class GrantPlatformOperatorCommand extends Command
{
    protected $signature = 'analytics:operator
                                {email : Email of the user}
                                {--revoke : Remove operator status instead of granting it}';

    protected $description = 'Grant or revoke platform-operator status (global feature flags) for a user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_platform_operator' => $grant])->save();

        $this->info(($grant ? 'Granted' : 'Revoked')." platform-operator status for {$user->email}.");

        return self::SUCCESS;
    }
}
