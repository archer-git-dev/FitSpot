<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Modules\Scheduling\Events\WeeklyScheduleUpdated;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Services\ScheduleService;
use App\Modules\Scheduling\Services\WorkspaceService;
use App\Notifications\PendingEmailVerification;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainerPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->assertSame('fitspot_test', DB::connection()->getDatabaseName(), 'Use the dedicated test database.');
    }

    private function trainer(bool $verified = true): User
    {
        $u = app(CreateNewUser::class)->create(['name' => 'Анна Тренер', 'email' => uniqid().'@example.test', 'password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!']);
        if ($verified) {
            $u->forceFill(['email_verified_at' => now()])->save();
        }

        return $u;
    }

    private function serviceData(): array
    {
        return ['name' => 'Сплит онлайн', 'description' => 'Занятие вдвоём', 'type' => 'split', 'format' => 'online', 'duration' => 60, 'price' => '2500.05', 'capacity' => 2, 'location' => '', 'active' => true];
    }

    public function test_registration_creates_one_workspace_and_sends_verification(): void
    {
        $this->post('/register', ['name' => 'Олег', 'email' => '  OLEG@EXAMPLE.TEST ', 'password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!'])->assertRedirect('/app');
        $u = User::where('email', 'oleg@example.test')->firstOrFail();
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertSame(10, $u->workspace->rules->buffer_after);
        $this->assertDatabaseCount('working_intervals', 0);
        Notification::assertSentTo($u, QueuedVerifyEmail::class);
        $this->get('/app')->assertRedirect('/email/verify');
    }

    public function test_registration_rejects_short_password_and_duplicate_email(): void
    {
        $u = $this->trainer();
        $this->post('/register', ['name' => 'Анна', 'email' => $u->email, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_signup_transaction_rolls_back_if_workspace_creation_fails(): void
    {
        $this->mock(WorkspaceService::class, fn ($m) => $m->shouldReceive('create')->andThrow(new \RuntimeException('failure')));
        try {
            $this->trainer();
        } catch (\RuntimeException $e) {
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guest_cannot_open_panel(): void
    {
        $this->get('/app')->assertRedirect('/login');
    }

    public function test_verified_trainer_sees_only_own_workspace(): void
    {
        $u = $this->trainer();
        $other = $this->trainer();
        $this->actingAs($u)->get('/app')->assertInertia(fn (Assert $p) => $p->component('Panel')->where('workspace.id', $u->workspace->id)->missing('auth.user.password')->missing('workspace.photo_path'));
    }

    public function test_email_verification_is_signed_and_marks_account_verified(): void
    {
        $u = $this->trainer(false);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->actingAs($u)->get($url)->assertRedirect('/app?verified=1');
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_user_can_correct_email_but_cannot_edit_workspace(): void
    {
        $u = $this->trainer(false);
        $this->actingAs($u)->post('/email/correct', ['email' => 'new@example.test'])->assertRedirect();
        $this->assertSame('new@example.test', $u->fresh()->email);
        Notification::assertSentTo($u, QueuedVerifyEmail::class);
        $this->post('/app/profile', ['name' => 'Hacked'])->assertRedirect('/email/verify');
    }

    public function test_verification_email_is_throttled(): void
    {
        $u = $this->trainer(false);
        $this->actingAs($u)->post('/email/verification-notification')->assertRedirect();
        $this->post('/email/verification-notification')->assertStatus(429);
    }

    public function test_profile_validates_slug_and_timezone(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->post('/app/profile', ['name' => 'Анна', 'slug' => 'admin', 'timezone' => 'bad'])->assertSessionHasErrors(['slug', 'timezone']);
    }

    public function test_profile_saved_without_reassigning_owner(): void
    {
        $u = $this->trainer();
        $other = $this->trainer();
        $this->actingAs($u)->post('/app/profile', ['name' => 'Новое имя', 'slug' => 'anna-trainer', 'timezone' => 'Asia/Tokyo', 'owner_id' => $other->id])->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $u->workspace->id, 'owner_id' => $u->id, 'name' => 'Новое имя', 'timezone' => 'Asia/Tokyo']);
    }

    public function test_slug_collision_rejected(): void
    {
        $u = $this->trainer();
        $other = $this->trainer();
        $this->actingAs($u)->post('/app/profile', ['name' => 'Анна', 'slug' => $other->workspace->slug, 'timezone' => 'Europe/Moscow'])->assertSessionHasErrors('slug');
    }

    public function test_photo_is_resized_and_private(): void
    {
        Storage::fake('local');
        $u = $this->trainer();
        $this->actingAs($u)->post('/app/profile', ['name' => 'Анна', 'slug' => 'anna-photo', 'timezone' => 'Europe/Moscow', 'photo' => UploadedFile::fake()->image('photo.png', 1800, 1200)])->assertRedirect();
        $path = $u->workspace()->first()->photo_path;
        $dimensions = getimagesize(Storage::disk('local')->path($path));
        $this->assertSame(1024, $dimensions[0]);
        $this->get('/app/photo')->assertOk();
        $other = $this->trainer();
        $this->actingAs($other)->get('/app/photo')->assertNotFound();
    }

    public function test_invalid_photo_does_not_replace_existing(): void
    {
        Storage::fake('local');
        $u = $this->trainer();
        $u->workspace->update(['photo_path' => 'old.png']);
        Storage::disk('local')->put('old.png', 'old');
        $this->actingAs($u)->post('/app/profile', ['name' => 'Анна', 'slug' => 'anna-photo', 'timezone' => 'Europe/Moscow', 'photo' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->assertSame('old.png', $u->workspace()->first()->photo_path);
        Storage::disk('local')->assertExists('old.png');
    }

    public function test_schedule_merges_adjacent_intervals_and_accepts_midnight_end(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->put('/app/schedule', ['intervals' => [['day' => 1, 'start' => 540, 'end' => 780], ['day' => 1, 'start' => 780, 'end' => 900], ['day' => 2, 'start' => 1200, 'end' => 1440]]])->assertRedirect();
        $this->assertDatabaseCount('working_intervals', 2);
        $this->assertDatabaseHas('working_intervals', ['day' => 1, 'start' => 540, 'end' => 900]);
    }

    public function test_schedule_overlap_does_not_change_previous_week(): void
    {
        $u = $this->trainer();
        $u->workspace->intervals()->create(['day' => 1, 'start' => 540, 'end' => 600]);
        $this->actingAs($u)->put('/app/schedule', ['intervals' => [['day' => 2, 'start' => 500, 'end' => 700], ['day' => 2, 'start' => 650, 'end' => 800]]])->assertSessionHasErrors('intervals');
        $this->assertDatabaseCount('working_intervals', 1);
        $this->assertDatabaseHas('working_intervals', ['day' => 1, 'start' => 540]);
    }

    public function test_schedule_rejects_overnight_and_more_than_eight_intervals(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->put('/app/schedule', ['intervals' => [['day' => 1, 'start' => 1380, 'end' => 60]]])->assertSessionHasErrors();
        $items = [];
        for ($i = 0; $i < 9; $i++) {
            $items[] = ['day' => 1, 'start' => $i * 60, 'end' => $i * 60 + 30];
        }
        $this->put('/app/schedule', ['intervals' => $items])->assertSessionHasErrors('intervals');
    }

    public function test_clearing_schedule_makes_all_days_off(): void
    {
        $u = $this->trainer();
        $u->workspace->intervals()->create(['day' => 1, 'start' => 540, 'end' => 600]);
        $this->actingAs($u)->put('/app/schedule', ['intervals' => []])->assertRedirect();
        $this->assertDatabaseCount('working_intervals', 0);
    }

    public function test_invalid_booking_rules_rejected(): void
    {
        $u = $this->trainer();
        $data = $u->workspace->rules->only(['buffer_before', 'buffer_after', 'lead_minutes', 'horizon_days', 'slot_step', 'cancel_minutes', 'reschedule_minutes']);
        $data['lead_minutes'] = 1440;
        $data['horizon_days'] = 1;
        $data['slot_step'] = 17;
        $this->actingAs($u)->put('/app/rules', $data)->assertSessionHasErrors('slot_step');
        $data['slot_step'] = 15;
        $this->put('/app/rules', $data)->assertSessionHasErrors('lead_minutes');
    }

    public function test_booking_rules_saved_for_owner(): void
    {
        $u = $this->trainer();
        $data = $u->workspace->rules->only(['buffer_before', 'buffer_after', 'lead_minutes', 'horizon_days', 'slot_step', 'cancel_minutes', 'reschedule_minutes']);
        $data['buffer_after'] = 20;
        $this->actingAs($u)->put('/app/rules', $data)->assertRedirect();
        $this->assertSame(20, $u->workspace->rules()->first()->buffer_after);
    }

    public function test_service_price_is_exact_and_location_cleared_online(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->post('/app/services', $this->serviceData())->assertRedirect();
        $this->assertDatabaseHas('training_services', ['workspace_id' => $u->workspace->id, 'price_kopecks' => 250005, 'capacity' => 2, 'location' => null]);
    }

    public function test_service_rejects_incorrect_capacity_duration_and_price(): void
    {
        $u = $this->trainer();
        $data = $this->serviceData();
        $data['capacity'] = 3;
        $data['duration'] = 61;
        $data['price'] = '1000000.01';
        $this->actingAs($u)->post('/app/services', $data)->assertSessionHasErrors(['capacity', 'duration', 'price']);
        $this->assertDatabaseCount('training_services', 0);
    }

    public function test_another_trainer_cannot_edit_or_disable_service(): void
    {
        $u = $this->trainer();
        $other = $this->trainer();
        $item = $other->workspace->services()->create([...collect($this->serviceData())->except('price')->all(), 'price_kopecks' => 100]);
        $this->actingAs($u)->put('/app/services/'.$item->id, $this->serviceData())->assertNotFound();
        $this->patch('/app/services/'.$item->id.'/status', ['active' => false])->assertNotFound();
        $this->assertTrue($item->fresh()->active);
    }

    public function test_disabling_service_keeps_record(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->post('/app/services', $this->serviceData());
        $id = $u->workspace->services()->first()->id;
        $this->patch('/app/services/'.$id.'/status', ['active' => false])->assertRedirect();
        $this->assertDatabaseHas('training_services', ['id' => $id, 'active' => false]);
    }

    public function test_events_not_dispatched_on_rollback(): void
    {
        $u = $this->trainer();
        $observed = [];
        Event::listen(WeeklyScheduleUpdated::class, function ($e) use (&$observed) {
            $observed[] = $e;
        });
        try {
            DB::transaction(function () use ($u) {
                app(ScheduleService::class)->replace($u->workspace, [['day' => 1, 'start' => 540, 'end' => 600]]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertSame([], $observed);
        $this->assertDatabaseCount('working_intervals', 0);
    }

    public function test_password_reset_response_does_not_reveal_account_existence(): void
    {
        $u = $this->trainer();
        $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status', 'Если аккаунт существует, письмо отправлено.');
        Notification::assertSentTo($u, QueuedResetPassword::class);
        RateLimiter::clear('auth:forgot-password:127.0.0.1');
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertSessionHas('status', 'Если аккаунт существует, письмо отправлено.');
    }

    public function test_password_reset_link_is_single_use(): void
    {
        $u = $this->trainer();
        $token = Password::createToken($u);
        $data = ['token' => $token, 'email' => $u->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('NewPassword123!', $u->fresh()->password));
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_expired_password_reset_link_rejected(): void
    {
        $u = $this->trainer();
        $token = Password::createToken($u);
        DB::table('password_reset_tokens')->where('email', $u->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_existing_identity(): void
    {
        $u = $this->trainer();
        $data = ['password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'];
        $this->actingAs($u)->post('/app/access/password', $data)->assertSessionHasErrors('current_password');
        $this->post('/app/access/password', [...$data, 'current_password' => 'TrainerPass123!'])->assertRedirect();
        $this->assertTrue(Hash::check('NewPassword123!', $u->fresh()->password));
    }

    public function test_telegram_cannot_be_unlinked_without_password(): void
    {
        $u = $this->trainer();
        $u->forceFill(['password' => null])->save();
        $u->telegram()->create(['subject' => '111']);
        $this->actingAs($u)->withSession(['access_confirmed_at' => time()])->delete('/app/access/telegram')->assertSessionHasErrors('telegram');
        $this->assertDatabaseCount('telegram_identities', 1);
    }

    public function test_telegram_unlink_preserves_email_access(): void
    {
        $u = $this->trainer();
        $u->telegram()->create(['subject' => '111']);
        $this->actingAs($u)->delete('/app/access/telegram', ['current_password' => 'TrainerPass123!'])->assertRedirect();
        $this->assertDatabaseCount('telegram_identities', 0);
    }

    public function test_email_change_keeps_old_email_until_signed_confirmation(): void
    {
        $u = $this->trainer();
        $this->actingAs($u)->post('/app/access/email', ['email' => 'replacement@example.test', 'current_password' => 'TrainerPass123!'])->assertRedirect();
        $this->assertNotSame('replacement@example.test', $u->fresh()->email);
        Notification::assertSentOnDemand(PendingEmailVerification::class);
        $url = URL::temporarySignedRoute('access.email.confirm', now()->addMinutes(30), ['user' => $u->id, 'hash' => hash('sha256', 'replacement@example.test')]);
        $this->get($url)->assertRedirect('/app/access');
        $this->assertSame('replacement@example.test', $u->fresh()->email);
        $this->get($url)->assertForbidden();
    }

    public function test_uniqueness_enforced_by_postgres(): void
    {
        $u = $this->trainer();
        $this->expectException(QueryException::class);
        Workspace::create(['owner_id' => $u->id, 'name' => 'Duplicate', 'slug' => 'different-slug']);
    }

    public function test_login_and_logout(): void
    {
        $u = $this->trainer();
        $this->post('/login', ['email' => strtoupper($u->email), 'password' => 'TrainerPass123!'])->assertRedirect('/app');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
