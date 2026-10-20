<?php

namespace App\Auth;

/**
 * Role-based access control. Pages ask for a permission, never for a role,
 * so every access rule is defined here and only here.
 */
final class Rbac
{
    public const ROLES = [
        'user'       => 'Student / Staff',
        'technician' => 'IT Technician',
        'admin'      => 'Administrator',
    ];

    public const PERMISSIONS = [
        'ticket.create'   => ['user'],
        'ticket.view_own' => ['user'],
        'ticket.manage'   => ['technician', 'admin'],
        'admin.dashboard' => ['technician', 'admin'],
        'user.manage'     => ['admin'],
        'audit.view'      => ['admin'],
    ];

    public static function allows(?string $role, string $permission): bool
    {
        return $role !== null && in_array($role, self::PERMISSIONS[$permission] ?? [], true);
    }

    public static function isRole(mixed $role): bool
    {
        return is_string($role) && array_key_exists($role, self::ROLES);
    }

    public static function label(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    public static function homeFor(string $role): string
    {
        return self::allows($role, 'admin.dashboard') ? 'admin_dashboard.php' : 'dashboard.php';
    }
}
