<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\OrganizationRepository;
use App\Models\UserRepository;

final class AuthService
{
    public const MIN_PASSWORD_LENGTH = 10;
    private const MAX_FAILURES_PER_EMAIL = 5;
    private const MAX_FAILURES_PER_IP = 20;
    private const THROTTLE_MINUTES = 15;

    private ?AuthContext $current = null;
    private bool $resolved = false;
    private UserRepository $users;
    private OrganizationRepository $organizations;

    public function __construct(private Database $db, private Session $session, private AuditLogger $audit)
    {
        $this->users = new UserRepository($db);
        $this->organizations = new OrganizationRepository($db);
    }

    /**
     * Creates the user, their organization (free plan), a default workspace and the owner membership.
     *
     * @throws ValidationException
     */
    public function register(string $name, string $email, string $password, string $organizationName, string $locale, string $ip = ''): AuthContext
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        $organizationName = trim($organizationName);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'validation.name';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 190) {
            $errors['email'] = 'validation.email';
        } elseif ($this->users->findByEmail($email) !== null) {
            $errors['email'] = 'validation.email_taken';
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH || strlen($password) > 4096) {
            $errors['password'] = 'validation.password';
        }
        if ($organizationName === '' || mb_strlen($organizationName) > 150) {
            $errors['organization'] = 'validation.organization';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $userId = $this->db->transaction(function () use ($name, $email, $password, $organizationName, $locale, $ip): int {
            $orgId = $this->organizations->create($organizationName, 'free', $locale);
            $this->organizations->createWorkspace($orgId, 'Principal');
            $userId = $this->users->create($name, $email, password_hash($password, PASSWORD_DEFAULT), $locale);
            $this->organizations->addMember($orgId, $userId, 'owner');
            $this->audit->log($orgId, $userId, 'user.registered', 'user', $userId, [], $ip);

            return $userId;
        });

        $this->loginUser($userId);
        $ctx = $this->current();
        if ($ctx === null) {
            throw new \LogicException('Registration did not produce a session');
        }

        return $ctx;
    }

    /** @throws TooManyAttemptsException */
    public function attempt(string $email, string $password, string $ip = ''): bool
    {
        $email = mb_strtolower(trim($email));
        $emailHash = hash('sha256', $email);
        if ($this->tooManyAttempts($emailHash, $ip)) {
            throw new TooManyAttemptsException();
        }

        $user = $this->users->findByEmail($email);
        // Verify against a dummy hash when the user does not exist to keep timing uniform.
        $hash = $user !== null ? (string) $user['password_hash'] : '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $ok = password_verify($password, $hash) && $user !== null && $user['status'] === 'active';

        $this->db->insert('login_attempts', [
            'email_hash' => $emailHash,
            'ip' => mb_substr($ip, 0, 45),
            'success' => $ok ? 1 : 0,
        ]);
        if (!$ok) {
            return false;
        }
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        $this->loginUser((int) $user['id']);
        $ctx = $this->current();
        $this->audit->log($ctx?->organizationId(), (int) $user['id'], 'user.login', 'user', (int) $user['id'], [], $ip);

        return $ctx !== null;
    }

    public function current(): ?AuthContext
    {
        if ($this->resolved) {
            return $this->current;
        }
        $this->resolved = true;
        $userId = $this->session->get('user_id');
        $orgId = $this->session->get('organization_id');
        if (!is_int($userId) || !is_int($orgId)) {
            return null;
        }
        $user = $this->users->find($userId);
        $membership = $user !== null && $user['status'] === 'active' ? $this->organizations->membership($orgId, $userId) : null;
        if ($user === null || $membership === null) {
            $this->logout();

            return null;
        }
        $organization = $this->organizations->find($orgId);
        if ($organization === null) {
            $this->logout();

            return null;
        }

        return $this->current = new AuthContext($user, $organization, (string) $membership['role']);
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->current = null;
        $this->resolved = true;
    }

    private function loginUser(int $userId): void
    {
        $memberships = $this->organizations->membershipsForUser($userId);
        if ($memberships === []) {
            throw new \RuntimeException('User has no organization');
        }
        $this->session->regenerate();
        $this->session->set('user_id', $userId);
        $this->session->set('organization_id', (int) $memberships[0]['organization_id']);
        $this->users->touchLogin($userId);
        $this->resolved = false;
        $this->current = null;
    }

    private function tooManyAttempts(string $emailHash, string $ip): bool
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::THROTTLE_MINUTES * 60);
        $byEmail = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE email_hash = ? AND success = 0 AND attempted_at >= ?',
            [$emailHash, $since],
        );
        if ($byEmail >= self::MAX_FAILURES_PER_EMAIL) {
            return true;
        }
        if ($ip === '') {
            return false;
        }
        $byIp = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at >= ?',
            [$ip, $since],
        );

        return $byIp >= self::MAX_FAILURES_PER_IP;
    }
}
