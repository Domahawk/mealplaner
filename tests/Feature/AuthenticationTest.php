<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'authentication-fixture-123';

    private string $sessionDirectory;

    private ?string $xsrf = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->sessionDirectory = sys_get_temp_dir().'/mealplaner-auth-test-'.bin2hex(random_bytes(8));
        mkdir($this->sessionDirectory, 0700);
        config([
            'session.driver' => 'file',
            'session.files' => $this->sessionDirectory,
            'session.lottery' => [0, 100],
            'hashing.bcrypt.rounds' => 4,
        ]);
        $this->withCredentials();
        // Exercise real CSRF checks while retaining testing mode and database guards.
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->sessionDirectory)) {
            File::deleteDirectory($this->sessionDirectory);
        }
        parent::tearDown();
    }

    public function test_guest_bootstrap_and_private_routes_have_the_correct_boundaries(): void
    {
        $this->browserRequest('GET', '/api/auth/session')->assertOk()
            ->assertExactJson(['user' => null])->assertHeader('Cache-Control', 'no-store, private');
        $this->browserRequest('POST', '/api/auth/logout')->assertUnauthorized();
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
        $this->get('/account')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('<div id="app"></div>', false);
        $this->get('/register')->assertOk()->assertSee('<div id="app"></div>', false);
    }

    public function test_registration_hashes_the_password_signs_in_and_rotates_session_credentials(): void
    {
        $before = $this->browserRequest('GET', '/api/auth/session');
        $oldId = $before->getCookie(config('session.cookie'))->getValue();
        $oldToken = session()->token();

        $response = $this->browserRequest('POST', '/api/auth/register', $this->registration());
        $response->assertCreated()->assertJsonPath('user.name', 'Test Planner')
            ->assertJsonPath('user.email', 'planner@example.test')
            ->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.remember_token')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotSame($oldId, $response->getCookie(config('session.cookie'))->getValue());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertFileDoesNotExist($this->sessionDirectory.'/'.$oldId);
        $user = User::query()->sole();
        $this->assertNotSame(self::PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->browserRequest('GET', '/api/auth/user')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->get('/account')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_login_persists_through_fresh_guard_and_session_instances(): void
    {
        $user = $this->createUser();
        $before = $this->browserRequest('GET', '/api/auth/session');
        $oldId = $before->getCookie(config('session.cookie'))->getValue();
        $oldToken = session()->token();

        $response = $this->browserRequest('POST', '/api/auth/login', ['email' => 'PLANNER@EXAMPLE.TEST', 'password' => self::PASSWORD]);
        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertNotSame($oldId, $response->getCookie(config('session.cookie'))->getValue());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertFileDoesNotExist($this->sessionDirectory.'/'.$oldId);
        $this->browserRequest('GET', '/api/auth/session')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->browserRequest('GET', '/api/auth/user')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_invalidates_the_session_and_rejects_replaying_its_old_cookie(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $login = $this->browserRequest('POST', '/api/auth/login', ['email' => 'planner@example.test', 'password' => self::PASSWORD]);
        $oldCookie = $login->getCookie(config('session.cookie'), false)->getValue();
        $oldId = $login->getCookie(config('session.cookie'))->getValue();
        $oldToken = session()->token();
        session()->put('private-test-value', 'must-be-cleared');
        session()->save();

        $response = $this->browserRequest('POST', '/api/auth/logout');
        $response->assertNoContent()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNotSame($oldId, $response->getCookie(config('session.cookie'))->getValue());
        $this->assertNotSame($oldToken, session()->token());
        $this->assertFalse(session()->has('private-test-value'));
        $this->assertFileDoesNotExist($this->sessionDirectory.'/'.$oldId);
        $this->browserRequest('GET', '/api/auth/session')->assertExactJson(['user' => null]);
        $this->withUnencryptedCookie(config('session.cookie'), $oldCookie);
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_invalid_credentials_are_generic_and_do_not_authenticate(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        foreach (['planner@example.test', 'missing@example.test'] as $email) {
            $this->browserRequest('POST', '/api/auth/login', ['email' => $email, 'password' => 'incorrect-fixture-123'])
                ->assertUnprocessable()->assertExactJson([
                    'message' => 'The provided credentials are incorrect.',
                    'errors' => ['email' => ['The provided credentials are incorrect.']],
                ]);
        }
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
    }

    public function test_registration_validation_and_normalized_duplicate_email_are_rejected(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $this->browserRequest('POST', '/api/auth/register')->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
        $this->browserRequest('POST', '/api/auth/register', array_replace($this->registration(), [
            'password' => 'short', 'password_confirmation' => 'different',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->browserRequest('POST', '/api/auth/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
    }

    #[DataProvider('unsafePasswords')]
    public function test_bcrypt_unsafe_passwords_are_validation_errors(string $password): void
    {
        $this->browserRequest('GET', '/api/auth/session');
        foreach (['register', 'login'] as $action) {
            $this->browserRequest('POST', '/api/auth/'.$action, array_replace($this->registration(), [
                'password' => $password, 'password_confirmation' => $password,
            ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{string}> */
    public static function unsafePasswords(): array
    {
        return [
            'too many ASCII bytes' => [str_repeat('a', 73).'1'],
            'too many multibyte bytes' => [str_repeat('é', 37).'1'],
            'null byte' => ["fixture-password-123\0"],
        ];
    }

    public function test_real_account_mutations_require_csrf_credentials(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        foreach (['register', 'login', 'logout'] as $action) {
            $this->browserRequest('POST', '/api/auth/'.$action, $this->registration(), false)->assertStatus(419);
        }
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
        $this->browserRequest('POST', '/api/auth/logout', [], false)->assertStatus(419);
        $this->browserRequest('GET', '/api/auth/user')->assertOk();
        $this->browserRequest('POST', '/api/auth/logout')->assertNoContent();
    }

    public function test_login_and_registration_are_rate_limited(): void
    {
        $this->browserRequest('GET', '/api/auth/session');
        foreach (['login', 'register'] as $action) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this->browserRequest('POST', '/api/auth/'.$action)->assertUnprocessable();
            }
            $this->browserRequest('POST', '/api/auth/'.$action)->assertStatus(429)->assertHeader('Retry-After');
        }
    }

    public function test_signed_in_users_cannot_register_or_switch_accounts_through_login(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertStatus(409);
        $this->browserRequest('POST', '/api/auth/register', array_replace($this->registration(), ['email' => 'other@example.test']))->assertStatus(409);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_cannot_overwrite_another_user_through_supplied_identifiers(): void
    {
        $existing = User::create(['name' => 'Existing Fixture', 'email' => 'existing@example.test', 'password' => self::PASSWORD]);
        $this->browserRequest('GET', '/api/auth/session');

        $response = $this->browserRequest('POST', '/api/auth/register', array_merge($this->registration(), [
            'id' => (string) $existing->id,
            'user_id' => (string) $existing->id,
            'owner_id' => (string) $existing->id,
        ]));

        $response->assertCreated()->assertJsonPath('user.email', 'planner@example.test');
        $this->assertNotSame($existing->id, $response->json('user.id'));
        $this->assertSame('Existing Fixture', $existing->refresh()->name);
        $this->assertSame('existing@example.test', $existing->email);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_forged_user_ids_cannot_switch_identity_or_log_out_another_session(): void
    {
        $alice = $this->createUser();
        $bob = User::create(['name' => 'Bob Fixture', 'email' => 'bob@example.test', 'password' => self::PASSWORD]);

        $this->browserRequest('GET', '/api/auth/session');
        $aliceLogin = $this->browserRequest('POST', '/api/auth/login', $this->registration());
        $aliceCookie = $aliceLogin->getCookie(config('session.cookie'), false)->getValue();
        $aliceXsrf = $this->xsrf;

        // A separate browser cookie jar starts Bob's independent authenticated session.
        $this->withUnencryptedCookie(config('session.cookie'), '');
        $this->xsrf = null;
        $this->browserRequest('GET', '/api/auth/session');
        $bobLogin = $this->browserRequest('POST', '/api/auth/login', [
            'email' => $bob->email, 'password' => self::PASSWORD, 'user_id' => (string) $alice->id,
        ])->assertOk()->assertJsonPath('user.id', $bob->id);
        $bobCookie = $bobLogin->getCookie(config('session.cookie'), false)->getValue();
        $bobXsrf = $this->xsrf;

        $this->withUnencryptedCookie(config('session.cookie'), $aliceCookie);
        $this->xsrf = $aliceXsrf;
        $this->browserRequest('POST', '/api/auth/logout', ['user_id' => (string) $bob->id])
            ->assertNoContent();
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();

        $this->withUnencryptedCookie(config('session.cookie'), $bobCookie);
        $this->xsrf = $bobXsrf;
        $this->browserRequest('GET', '/api/auth/user?user_id='.$alice->id)
            ->assertOk()->assertJsonPath('user.id', $bob->id);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_a_hash_shaped_password_is_hashed_as_plain_input_and_round_trips_through_login(): void
    {
        $underlying = 'underlying-password-fixture-123';
        $password = Hash::make($underlying);
        $this->browserRequest('GET', '/api/auth/session');
        $this->browserRequest('POST', '/api/auth/register', array_replace($this->registration(), [
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertCreated();

        $stored = User::query()->sole()->password;
        $this->assertNotSame($password, $stored);
        $this->assertTrue(Hash::check($password, $stored));
        $this->assertFalse(Hash::check($underlying, $stored));
        $this->browserRequest('POST', '/api/auth/logout')->assertNoContent();
        $this->browserRequest('POST', '/api/auth/login', ['email' => 'planner@example.test', 'password' => $underlying])->assertUnprocessable();
        $this->browserRequest('POST', '/api/auth/login', ['email' => 'planner@example.test', 'password' => $password])->assertOk();
    }

    #[DataProvider('sessionLockStores')]
    public function test_same_session_requests_respect_the_lock_which_serializes_logout(string $store): void
    {
        config([
            'session.block_store' => $store,
            'cache.stores.file.path' => $this->sessionDirectory.'/cache',
            'cache.stores.file.lock_path' => $this->sessionDirectory.'/cache',
        ]);
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $login = $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
        $id = $login->getCookie(config('session.cookie'))->getValue();
        $lock = Cache::store(config('session.block_store'))->lock('session:'.$id, 30);
        $this->assertTrue($lock->get());
        config(['session.block_wait_seconds' => 0]);
        $this->getJson('/api/health')->assertOk();
        $this->withUnencryptedCookie(config('session.cookie'), '');
        $this->browserRequest('GET', '/api/auth/session')->assertOk()->assertExactJson(['user' => null]);
        $this->withUnencryptedCookie(config('session.cookie'), $login->getCookie(config('session.cookie'), false)->getValue());
        $this->withoutExceptionHandling();

        $blocked = false;
        try {
            $this->browserRequest('GET', '/api/auth/user');
        } catch (LockTimeoutException) {
            $blocked = true;
        } finally {
            $lock->release();
        }
        $this->assertTrue($blocked, 'A second request must not read/write a session while its lock is held.');
        $this->withExceptionHandling();

        $this->browserRequest('GET', '/api/auth/user')->assertOk();
        $this->browserRequest('POST', '/api/auth/logout')->assertNoContent();
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
    }

    /** @return array<string, array{string}> */
    public static function sessionLockStores(): array
    {
        return ['test array cache' => ['array'], 'local file cache' => ['file']];
    }

    public function test_login_throttle_survives_email_case_and_session_changes_then_expires(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $email = $attempt % 2 === 0 ? ' PLANNER@EXAMPLE.TEST ' : 'planner@example.test';
            $this->browserRequest('POST', '/api/auth/login', ['email' => $email, 'password' => 'incorrect-fixture-123'])->assertUnprocessable();
        }

        $this->withUnencryptedCookie(config('session.cookie'), '');
        $this->xsrf = null;
        $this->browserRequest('GET', '/api/auth/session');
        $this->browserRequest('POST', '/api/auth/login', $this->registration())
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();

        $this->travel(61)->seconds();
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
    }

    public function test_login_ip_limit_blocks_breadth_first_attempts_without_blocking_other_ips(): void
    {
        $this->browserRequest('GET', '/api/auth/session');
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->browserRequest('POST', '/api/auth/login', ['email' => 'attempt'.$attempt.'@example.test'])
                ->assertUnprocessable();
        }
        $this->browserRequest('POST', '/api/auth/login', ['email' => 'next@example.test'])->assertStatus(429);
        $this->getJson('/api/health')->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20']);
        $this->browserRequest('POST', '/api/auth/login', ['email' => 'next@example.test'])->assertUnprocessable();
    }

    public function test_rotated_csrf_tokens_cannot_log_out_or_log_back_into_a_session(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $guestXsrf = $this->xsrf;
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
        $authenticatedXsrf = $this->xsrf;

        $this->xsrf = $guestXsrf;
        $this->browserRequest('POST', '/api/auth/logout')->assertStatus(419);
        $this->browserRequest('GET', '/api/auth/user')->assertOk();
        $this->xsrf = $authenticatedXsrf;
        $this->browserRequest('POST', '/api/auth/logout')->assertNoContent();

        $this->xsrf = $authenticatedXsrf;
        $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertStatus(419);
        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
    }

    public function test_an_expired_file_session_cannot_restore_authentication(): void
    {
        $this->createUser();
        $this->browserRequest('GET', '/api/auth/session');
        $login = $this->browserRequest('POST', '/api/auth/login', $this->registration())->assertOk();
        $id = $login->getCookie(config('session.cookie'))->getValue();
        touch($this->sessionDirectory.'/'.$id, time() - (config('session.lifetime') + 1) * 60);

        $this->browserRequest('GET', '/api/auth/user')->assertUnauthorized();
        $this->browserRequest('GET', '/api/auth/session')->assertExactJson(['user' => null]);
    }

    #[DataProvider('malformedAccountInputs')]
    public function test_malformed_credentials_are_validation_errors_and_create_no_account(string $field, mixed $value): void
    {
        $this->browserRequest('GET', '/api/auth/session');
        foreach (['register', 'login'] as $action) {
            $this->browserRequest('POST', '/api/auth/'.$action, array_replace($this->registration(), [$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{string, mixed}> */
    public static function malformedAccountInputs(): array
    {
        return [
            'email array' => ['email', ['unexpected']],
            'email null' => ['email', null],
            'invalid email' => ['email', 'not-an-email'],
            'password array' => ['password', ['unexpected']],
            'password number' => ['password', 123456789012],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_each_registration_password_requirement_is_enforced(string $password, string $confirmation): void
    {
        $this->browserRequest('GET', '/api/auth/session');
        $this->browserRequest('POST', '/api/auth/register', array_replace($this->registration(), [
            'password' => $password, 'password_confirmation' => $confirmation,
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, array{string, string}> */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['short-123', 'short-123'],
            'no letters' => ['123456789012345', '123456789012345'],
            'no numbers' => ['long-password-fixture', 'long-password-fixture'],
            'confirmation mismatch' => [self::PASSWORD, 'different-fixture-123'],
        ];
    }

    public function test_get_requests_cannot_perform_authentication_mutations(): void
    {
        foreach (['register', 'login', 'logout'] as $action) {
            $this->getJson('/api/auth/'.$action)->assertStatus(405);
        }
        $this->assertDatabaseCount('users', 0);
    }

    /** @return array<string, string> */
    private function registration(): array
    {
        return ['name' => 'Test Planner', 'email' => ' PLANNER@example.test ', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
    }

    private function createUser(): User
    {
        return User::create(['name' => 'Test Planner', 'email' => 'planner@example.test', 'password' => self::PASSWORD]);
    }

    /** @param array<string, mixed> $fields Malformed credential fixtures intentionally include non-string values. */
    private function browserRequest(string $method, string $path, array $fields = [], bool $csrf = true): TestResponse
    {
        // Each request must recover identity from the file/cookie, not a cached guard.
        Auth::forgetGuards();
        $this->app->make('session')->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $headers = ['Sec-Fetch-Site' => 'cross-site'];
        if ($csrf && $this->xsrf !== null) {
            $headers['X-XSRF-TOKEN'] = $this->xsrf;
        }
        $response = $this->json($method, $path, $fields, $headers);
        $cookie = $response->getCookie(config('session.cookie'), false);
        if ($cookie !== null) {
            $this->withUnencryptedCookie(config('session.cookie'), $cookie->getValue());
        }
        $this->xsrf = $response->getCookie('XSRF-TOKEN', false)?->getValue() ?? $this->xsrf;

        return $response;
    }
}
