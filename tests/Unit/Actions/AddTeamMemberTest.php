<?php

namespace Tests\Unit\Actions;

use App\Actions\Jetstream\AddTeamMember;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Events\AddingTeamMember;
use Laravel\Jetstream\Events\TeamMemberAdded;
use Tests\TestCase;

class AddTeamMemberTest extends TestCase
{
    use RefreshDatabase;

    private AddTeamMember $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AddTeamMember;
    }

    public function test_owner_can_add_an_existing_user_to_the_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $newMember = User::factory()->create();

        $this->action->add($owner, $owner->currentTeam, $newMember->email, 'admin');

        $this->assertTrue($owner->currentTeam->fresh()->hasUserWithEmail($newMember->email));
    }

    public function test_added_member_receives_the_given_role(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $newMember = User::factory()->create();

        $this->action->add($owner, $owner->currentTeam, $newMember->email, 'editor');

        $team = $owner->currentTeam->fresh();
        $this->assertEquals('editor', $team->users->firstWhere('id', $newMember->id)->membership->role);
    }

    public function test_dispatches_adding_and_added_events(): void
    {
        Event::fake([AddingTeamMember::class, TeamMemberAdded::class]);

        $owner = User::factory()->withPersonalTeam()->create();
        $newMember = User::factory()->create();

        $this->action->add($owner, $owner->currentTeam, $newMember->email, 'admin');

        Event::assertDispatched(AddingTeamMember::class);
        Event::assertDispatched(TeamMemberAdded::class);
    }

    public function test_throws_when_email_does_not_belong_to_a_registered_user(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $this->expectException(ValidationException::class);

        $this->action->add($owner, $owner->currentTeam, 'nobody@example.com', 'admin');
    }

    public function test_throws_when_user_already_belongs_to_the_team(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $existingMember = User::factory()->create();
        $owner->currentTeam->users()->attach($existingMember, ['role' => 'admin']);

        $this->expectException(ValidationException::class);

        $this->action->add($owner, $owner->currentTeam, $existingMember->email, 'editor');
    }

    public function test_non_owner_without_permission_cannot_add_team_members(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $outsider = User::factory()->create();
        $newMember = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        $this->action->add($outsider, $owner->currentTeam, $newMember->email, 'admin');
    }

    public function test_team_member_count_increases_by_one(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $newMember = User::factory()->create();

        $before = $owner->currentTeam->users->count();
        $this->action->add($owner, $owner->currentTeam, $newMember->email, 'admin');

        $this->assertEquals($before + 1, $owner->currentTeam->fresh()->users->count());
    }
}
