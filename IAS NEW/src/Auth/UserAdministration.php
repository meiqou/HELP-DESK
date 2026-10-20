<?php

namespace App\Auth;

use App\Repositories\UserRepository;
use App\Security\AuditLog;
use App\Security\RateLimiter;

/**
 * Administrator actions on other accounts, with the safety rules that protect
 * the system from lock-out and privilege mistakes.
 */
final class UserAdministration
{
    /**
     * @return array{0: string, 1: string} flash type and message
     */
    public static function perform(array $admin, int $targetId, string $action, string $newRole = ''): array
    {
        $target = UserRepository::find($targetId);
        if (!$target) {
            return ['error', 'User not found.'];
        }

        $name = $target['username'];
        $id = (int) $target['id'];

        // An administrator cannot demote, deactivate or delete their own account.
        if ($id === (int) $admin['id'] && $action !== 'unlock') {
            return ['error', 'You cannot change your own account from this page.'];
        }

        // The system must always keep at least one active administrator.
        $isLastAdmin = $target['role'] === 'admin' && $target['is_active'] && UserRepository::countActiveAdmins() <= 1;

        switch ($action) {
            case 'role':
                if (!Rbac::isRole($newRole)) {
                    return ['error', 'Invalid role.'];
                }
                if ($isLastAdmin && $newRole !== 'admin') {
                    return ['error', 'At least one active administrator is required.'];
                }
                if ($newRole === $target['role']) {
                    return ['info', "{$name} already has that role."];
                }
                UserRepository::setRole($id, $newRole);   // also signs the user out everywhere
                AuditLog::record('role_changed', "{$name}: {$target['role']} to {$newRole}");
                return ['success', "{$name} is now " . Rbac::label($newRole) . '.'];

            case 'toggle_active':
                if ($isLastAdmin) {
                    return ['error', 'At least one active administrator is required.'];
                }
                $active = !$target['is_active'];
                UserRepository::setActive($id, $active);
                AuditLog::record($active ? 'user_activated' : 'user_deactivated', $name);
                return ['success', $active ? "{$name} has been reactivated." : "{$name} has been deactivated and signed out."];

            case 'reset_2fa':
                UserRepository::disableTotp($id, true);
                AuditLog::record('2fa_reset_by_admin', $name);
                return ['success', "Two-factor authentication was reset for {$name}."];

            case 'unlock':
                RateLimiter::clear($name);
                AuditLog::record('user_unlocked', $name);
                return ['success', "Sign-in lockout cleared for {$name}."];

            case 'revoke_sessions':
                UserRepository::bumpSessionVersion($id);
                AuditLog::record('sessions_revoked_by_admin', $name);
                return ['success', "All sessions for {$name} were signed out."];

            case 'delete':
                if ($isLastAdmin) {
                    return ['error', 'At least one active administrator is required.'];
                }
                UserRepository::delete($id);
                AuditLog::record('user_deleted', "{$name} ({$target['email']})");
                return ['success', "{$name} was deleted."];
        }

        return ['error', 'Unknown action.'];
    }
}
