<?php
declare(strict_types=1);

final class Department
{
    public const POSITIONS = [
        'assembly-technician' => 'Assembly technician',
        'bodyshop-coating-technician' => 'Bodyshop coating technician',
        'production-employee-drones' => 'Production employee drones',
        'mbo-hbo-internship' => 'MBO/HBO internship',
        'open-application' => 'Open application',
    ];

    public const DEPARTMENTS = [
        'assembly-technician' => 'Assembly technician',
        'bodyshop-coating-technician' => 'Bodyshop coating technician',
        'production-employee-drones' => 'Production employee drones',
        'open/intern' => 'open/intern',
    ];

    public static function validate(mixed $value): string
    {
        if (!is_string($value) || !array_key_exists($value, self::DEPARTMENTS)) {
            throw new DomainException('Kies een geldige afdeling.', 422);
        }
        return $value;
    }

    public static function validatePosition(mixed $value): string
    {
        if (!is_string($value) || !array_key_exists($value, self::POSITIONS)) {
            throw new DomainException('Kies de functie of afdeling waarvoor je wilt solliciteren.', 422);
        }
        return $value;
    }
}
