<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
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
            foreach (glob($this->sessionDirectory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->sessionDirectory);
        }
        parent::tearDown();
    }

    public function test_guest_bootstrap_and_private_routes_have_the_correct_boundaries(): void
    {
        $this->browserRequest('GET', '/api/auth/session')->assertOk()
            ->assertExactJson(['user' => null])->assertHeader('Cache-Control', 'no-store, private');
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

    /** @return array<string, string> */
    private function registration(): array
    {
        return ['name' => 'Test Planner', 'email' => ' PLANNER@example.test ', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
    }

    private function createUser(): User
    {
        return User::create(['name' => 'Test Planner', 'email' => 'planner@example.test', 'password' => self::PASSWORD]);
    }

    /** @param array<string, string> $fields */
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
