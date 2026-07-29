<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Department sub-units (Transport → School/College/Trust Transport;
 * Civil Works → Study Centre / Auditorium / Learning Centre / Conference Hall).
 * Data-driven so more units can be added without code changes.
 */
class DepartmentUnit extends Model
{
    protected string $table = 'department_units';

    /** Active units for a department, ordered by name. */
    public function forDepartment(int $departmentId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, department_id, unit_code, unit_name, status
             FROM department_units
             WHERE department_id = ? AND status = "active"
             ORDER BY id'
        );
        $stmt->execute([$departmentId]);
        return $stmt->fetchAll();
    }

    /** All units across departments (for report filters). */
    public function allWithDepartment(): array
    {
        return $this->db()->query(
            'SELECT u.id, u.department_id, u.unit_code, u.unit_name, u.status, d.name AS department_name
             FROM department_units u
             JOIN departments d ON d.id = u.department_id
             WHERE u.status = "active"
             ORDER BY d.name, u.id'
        )->fetchAll();
    }

    public function belongsToDepartment(int $unitId, int $departmentId): bool
    {
        $stmt = $this->db()->prepare(
            'SELECT 1 FROM department_units WHERE id = ? AND department_id = ? AND status = "active" LIMIT 1'
        );
        $stmt->execute([$unitId, $departmentId]);
        return $stmt->fetch() !== false;
    }
}
