<?php

declare(strict_types=1);

namespace App\Services;

/** The authenticated user, the current organization and the user's role in it. */
final class AuthContext
{
    /** Role => abilities. "*" grants everything. */
    private const ABILITIES = [
        'owner' => ['*'],
        'admin' => ['org.manage', 'project.create', 'project.view', 'form.create', 'form.edit', 'form.publish', 'form.delete', 'data.analyze'],
        'designer' => ['project.create', 'project.view', 'form.create', 'form.edit', 'form.publish'],
        'collector' => ['project.view'],
        'analyst' => ['project.view', 'data.analyze'],
        'viewer' => ['project.view'],
    ];

    public const ROLES = ['owner', 'admin', 'designer', 'collector', 'analyst', 'viewer'];

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $organization
     */
    public function __construct(
        public readonly array $user,
        public readonly array $organization,
        public readonly string $role,
    ) {}

    public function userId(): int
    {
        return (int) $this->user['id'];
    }

    public function organizationId(): int
    {
        return (int) $this->organization['id'];
    }

    public function can(string $ability): bool
    {
        $abilities = self::ABILITIES[$this->role] ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
