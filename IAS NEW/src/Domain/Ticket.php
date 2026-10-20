<?php

namespace App\Domain;

/**
 * Ticket vocabulary and validation. Form values are checked against these
 * whitelists before anything reaches the database.
 */
final class Ticket
{
    public const CATEGORIES = [
        'Wi-Fi / Network'      => 'Wi-Fi and campus network',
        'LMS / Portal'         => 'LMS / student portal',
        'Hardware / Lab PC'    => 'Hardware / computer laboratory',
        'Software / Licensing' => 'Software / Microsoft 365 licensing',
        'Account / Password'   => 'Institutional account and password',
        'Other'                => 'Other',
    ];

    public const PRIORITIES = ['Low', 'Normal', 'High', 'Critical'];

    public const STATUSES = ['Open', 'In Progress', 'Resolved'];

    /**
     * @return string[] validation errors for a new ticket
     */
    public static function validate(array $input): array
    {
        $errors = [];
        if (!array_key_exists($input['category'], self::CATEGORIES)) {
            $errors[] = 'Choose a category.';
        }
        if (!in_array($input['priority'], self::PRIORITIES, true)) {
            $errors[] = 'Choose a priority.';
        }
        $subject = mb_strlen($input['subject']);
        if ($subject < 5 || $subject > 150) {
            $errors[] = 'The subject must be between 5 and 150 characters.';
        }
        $description = mb_strlen($input['description']);
        if ($description < 10 || $description > 5000) {
            $errors[] = 'The description must be between 10 and 5,000 characters.';
        }
        return $errors;
    }

    public static function isStatus(mixed $value): bool
    {
        return in_array($value, self::STATUSES, true);
    }

    public static function isPriority(mixed $value): bool
    {
        return in_array($value, self::PRIORITIES, true);
    }
}
