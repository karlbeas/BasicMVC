<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\OrganizationRepository;
use App\Models\UserRepository;

/** Rebuilds an AuthContext outside a web session (queued jobs), re-checking the membership. */
final class ContextFactory
{
    public static function forUser(Database $db, int $organizationId, int $userId): AuthContext
    {
        $orgs = new OrganizationRepository($db);
        $user = (new UserRepository($db))->find($userId);
        $membership = $orgs->membership($organizationId, $userId);
        $organization = $orgs->find($organizationId);
        if ($user === null || $user['status'] !== 'active' || $membership === null || $organization === null) {
            throw new \DomainException('User is no longer a member of this organization');
        }

        return new AuthContext($user, $organization, (string) $membership['role']);
    }
}
