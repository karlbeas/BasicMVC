<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\TooManyAttemptsException;
use App\Services\ValidationException;

final class AuthServiceTest extends DatabaseTestCase
{
    public function testRegistrationCreatesOrganizationWorkspaceAndOwnerMembership(): void
    {
        $ctx = $this->newAccount();

        self::assertSame('owner', $ctx->role);
        self::assertSame('free', $ctx->organization['plan_code']);
        self::assertTrue($ctx->can('form.publish'));
        self::assertSame(1, (int) $this->db()->fetchValue('SELECT COUNT(*) FROM workspaces WHERE organization_id = ?', [$ctx->organizationId()]));
        $hash = (string) $this->db()->fetchValue('SELECT password_hash FROM users WHERE id = ?', [$ctx->userId()]);
        self::assertTrue(password_verify('correct-horse-battery', $hash));
        self::assertArrayNotHasKey('password_hash', $ctx->user, 'The password hash must not be loaded in the auth context');
    }

    public function testRegistrationValidation(): void
    {
        $existing = $this->newAccount();
        try {
            $this->newAuthService()->register('', (string) $existing->user['email'], 'short', '', 'fr');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['name', 'email', 'password', 'organization'], array_keys($e->errors));
            self::assertSame('validation.email_taken', $e->errors['email']);
        }
    }

    public function testLoginSucceedsAndFails(): void
    {
        $email = (string) $this->newAccount()->user['email'];

        self::assertFalse($this->newAuthService()->attempt($email, 'wrong-password', '10.0.0.1'));
        $auth = $this->newAuthService();
        self::assertTrue($auth->attempt(strtoupper($email), 'correct-horse-battery', '10.0.0.1'));
        self::assertSame($email, $auth->current()?->user['email']);
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $email = (string) $this->newAccount()->user['email'];
        for ($i = 0; $i < 5; ++$i) {
            self::assertFalse($this->newAuthService()->attempt($email, 'nope-' . $i, '10.0.0.2'));
        }

        $this->expectException(TooManyAttemptsException::class);
        $this->newAuthService()->attempt($email, 'correct-horse-battery', '10.0.0.2');
    }
}
