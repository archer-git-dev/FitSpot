<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Modules\Scheduling\Services\WorkspaceService;
use App\Services\Auth\TelegramTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TelegramAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private $privateKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->assertSame('fitspot_test', DB::connection()->getDatabaseName());
        config(['fitspot.telegram_client_id' => '12345']);
        $this->privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $key = openssl_pkey_get_details($this->privateKey);
        Http::fake(['oauth.telegram.org/*' => Http::response(['keys' => [['kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig', 'n' => JWT::urlsafeB64Encode($key['rsa']['n']), 'e' => JWT::urlsafeB64Encode($key['rsa']['e'])]]])]);
    }

    private function token(string $nonce, array $changes = []): string
    {
        return JWT::encode(array_replace(['iss' => 'https://oauth.telegram.org', 'aud' => '12345', 'iat' => time(), 'exp' => time() + 300, 'sub' => '555111', 'nonce' => $nonce, 'name' => 'Тренер Telegram'], $changes), $this->privateKey, 'RS256', 'test-key');
    }

    private function verifyToken(array $changes = [], ?string $nonce = null): array
    {
        $n = $nonce ?? bin2hex(random_bytes(12));

        return app(TelegramTokenVerifier::class)->verify($this->token($n, $changes), $n);
    }

    public function test_valid_signature_and_nonce_accepted_and_reuse_rejected(): void
    {
        $nonce = 'unique-nonce';
        $result = $this->verifyToken([], $nonce);
        $this->assertSame('555111', $result['subject']);
        $this->expectException(ValidationException::class);
        $this->verifyToken([], $nonce);
    }

    public function test_expired_token_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->verifyToken(['exp' => time() - 5]);
    }

    public function test_wrong_audience_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->verifyToken(['aud' => 'wrong']);
    }

    public function test_wrong_issuer_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->verifyToken(['iss' => 'https://attacker.test']);
    }

    public function test_wrong_nonce_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(TelegramTokenVerifier::class)->verify($this->token('actual'), 'expected');
    }

    public function test_bad_signature_rejected(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048]);
        $token = JWT::encode(['iss' => 'https://oauth.telegram.org', 'aud' => '12345', 'iat' => time(), 'exp' => time() + 300, 'sub' => '555111', 'nonce' => 'nonce'], $other, 'RS256', 'test-key');
        $this->expectException(ValidationException::class);
        app(TelegramTokenVerifier::class)->verify($token, 'nonce');
    }

    public function test_invalid_token_creates_no_account(): void
    {
        $this->post('/auth/telegram/nonce', ['mode' => 'login'])->assertOk();
        $this->post('/auth/telegram/verify', ['id_token' => 'fake'])->assertSessionHasErrors('telegram');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_new_telegram_signup_requires_email_and_is_unverified(): void
    {
        $nonce = $this->post('/auth/telegram/nonce', ['mode' => 'login'])->json('nonce');
        $this->post('/auth/telegram/verify', ['id_token' => $this->token($nonce)])->assertRedirect('/auth/telegram/complete');
        $this->assertDatabaseCount('users', 0);
        $this->post('/auth/telegram/complete', ['name' => 'Telegram тренер', 'email' => 'telegram@example.test'])->assertRedirect('/email/verify');
        $u = User::where('email', 'telegram@example.test')->firstOrFail();
        $this->assertNull($u->password);
        $this->assertFalse($u->hasVerifiedEmail());
        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseHas('telegram_identities', ['user_id' => $u->id, 'subject' => '555111']);
    }

    public function test_existing_email_does_not_merge_accounts(): void
    {
        $u = app(CreateNewUser::class)->create(['name' => 'Тренер', 'email' => 'existing@example.test', 'password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!']);
        $nonce = $this->post('/auth/telegram/nonce', ['mode' => 'login'])->json('nonce');
        $this->post('/auth/telegram/verify', ['id_token' => $this->token($nonce)]);
        $this->post('/auth/telegram/complete', ['name' => 'Telegram тренер', 'email' => $u->email])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('telegram_identities', 0);
    }

    public function test_existing_identity_logs_into_correct_user(): void
    {
        $u = app(CreateNewUser::class)->create(['name' => 'Тренер', 'email' => 'existing@example.test', 'password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!']);
        $u->telegram()->create(['subject' => '555111']);
        $nonce = $this->post('/auth/telegram/nonce', ['mode' => 'login'])->json('nonce');
        $this->post('/auth/telegram/verify', ['id_token' => $this->token($nonce)])->assertRedirect('/app');
        $this->assertAuthenticatedAs($u);
    }

    public function test_identity_owned_by_another_user_cannot_be_linked(): void
    {
        $data = ['name' => 'Тренер', 'password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!'];
        $u = app(CreateNewUser::class)->create([...$data, 'email' => 'first@example.test']);
        $other = app(CreateNewUser::class)->create([...$data, 'email' => 'second@example.test']);
        $u->forceFill(['email_verified_at' => now()])->save();
        $other->telegram()->create(['subject' => '555111']);
        $nonce = $this->actingAs($u)->post('/auth/telegram/nonce', ['mode' => 'link'])->json('nonce');
        $this->post('/auth/telegram/verify', ['id_token' => $this->token($nonce)])->assertSessionHasErrors('telegram');
        $this->assertFalse($u->telegram()->exists());
    }

    public function test_temporary_signup_expires(): void
    {
        $this->withSession(['telegram.signup' => ['subject' => '555111', 'name' => 'Тренер', 'expires' => time() - 1]])->post('/auth/telegram/complete', ['name' => 'Тренер', 'email' => 'new@example.test'])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_telegram_unconfigured_returns_unavailable(): void
    {
        config(['fitspot.telegram_client_id' => null]);
        $this->post('/auth/telegram/nonce', ['mode' => 'login'])->assertStatus(503);
    }

    public function test_fresh_telegram_confirmation_allows_setting_first_password(): void
    {
        $u = User::create(['name' => 'Тренер', 'email' => 'tg@example.test', 'password' => null]);
        app(WorkspaceService::class)->create($u);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->telegram()->create(['subject' => '555111']);
        $nonce = $this->actingAs($u)->post('/auth/telegram/nonce', ['mode' => 'reauth'])->json('nonce');
        $this->post('/auth/telegram/verify', ['id_token' => $this->token($nonce)])->assertRedirect('/app/access');
        $this->post('/app/access/password', ['password' => 'TrainerPass123!', 'password_confirmation' => 'TrainerPass123!'])->assertRedirect();
        $this->assertNotNull($u->fresh()->password);
    }

    public function test_duplicate_telegram_subject_rejected_by_database(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        TelegramIdentity::create(['user_id' => $u->id, 'subject' => '555111']);
        $this->expectException(QueryException::class);
        TelegramIdentity::create(['user_id' => $other->id, 'subject' => '555111']);
    }
}
