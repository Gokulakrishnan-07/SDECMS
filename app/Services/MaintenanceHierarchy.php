<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class MaintenanceHierarchy
{
    public const CATEGORIES = ['Civil', 'Electrical', 'Plumbing'];

    public static function isHousekeeping(int $departmentId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM departments WHERE id = ? AND (code = 'HKP' OR name = 'Housekeeping') LIMIT 1"
        );
        $stmt->execute([$departmentId]);
        return $stmt->fetch() !== false;
    }

    public static function isMaintenance(int $departmentId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT 1 FROM departments WHERE id = ? AND (code = 'MNT' OR name IN ('Civil Department', 'Civil Works', 'Maintenance Department')) LIMIT 1"
        );
        $stmt->execute([$departmentId]);
        return $stmt->fetch() !== false;
    }

    /** @return array<int,array{value:string,label:string,type:string,id:int}> */
    public static function locations(): array
    {
        $sql = "SELECT CONCAT('department:', d.id) AS value, d.name AS label, 'department' AS type, d.id
                FROM departments d WHERE d.is_active = 1
                UNION ALL
                SELECT CONCAT('unit:', u.id) AS value, u.unit_name AS label, 'unit' AS type, u.id
                FROM department_units u JOIN departments d ON d.id = u.department_id
                WHERE u.status = 'active' AND d.is_active = 1
                ORDER BY label";
        return Database::connection()->query($sql)->fetchAll();
    }

    /** @return array{type:string,id:int,label:string}|null */
    public static function resolve(?string $value): ?array
    {
        if (!is_string($value) || !preg_match('/^(department|unit):(\d+)$/', $value, $m)) {
            return null;
        }
        $id = (int) $m[2];
        if ($m[1] === 'department') {
            $stmt = Database::connection()->prepare('SELECT id, name AS label FROM departments WHERE id = ? AND is_active = 1');
        } else {
            $stmt = Database::connection()->prepare(
                "SELECT u.id, u.unit_name AS label FROM department_units u JOIN departments d ON d.id = u.department_id WHERE u.id = ? AND u.status = 'active' AND d.is_active = 1"
            );
        }
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : ['type' => $m[1], 'id' => $id, 'label' => $row['label']];
    }

    /** @return array{type:string,id:?int,label:?string,other:?string}|null */
    public static function resolveForDepartment(int $departmentId, ?string $value, ?string $other = null): ?array
    {
        if (self::isHousekeeping($departmentId) && $value === 'other') {
            $other = trim((string) $other);
            return $other === '' ? null : ['type' => 'other', 'id' => null, 'label' => 'Others', 'other' => $other];
        }
        $location = self::resolve($value);
        return $location === null ? null : [...$location, 'other' => null];
    }
}
