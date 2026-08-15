<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Generates unique, gap-free document numbers.
 *
 * Sanctions:          <DEPT>-<YEAR>-<NNN>      e.g. COL-2026-001
 * Purchase Requests:  PR-<DEPT>-<YEAR>-<NNN>   e.g. PR-COL-2026-001
 * Purchase Orders:    PO-<YEAR>-<NNN>          e.g. PO-2026-001
 *
 * Sanction numbers use a locked counter row per department + financial year,
 * so concurrent requests can never produce duplicates.
 */
class NumberService
{
    /**
     * Next sanction number. MUST be called inside an open transaction on $pdo.
     */
    public static function nextSanctionNo(PDO $pdo, int $departmentId, int $financialYearId): string
    {
        [$code, $year] = self::prefixParts($pdo, $departmentId, $financialYearId);

        // Ensure a counter row exists, then lock and increment it.
        $pdo->prepare(
            'INSERT IGNORE INTO sanction_counters (department_id, financial_year_id, last_number)
             VALUES (?, ?, 0)'
        )->execute([$departmentId, $financialYearId]);

        $stmt = $pdo->prepare(
            'SELECT last_number FROM sanction_counters
             WHERE department_id = ? AND financial_year_id = ? FOR UPDATE'
        );
        $stmt->execute([$departmentId, $financialYearId]);
        $next = (int) $stmt->fetch()['last_number'] + 1;

        $pdo->prepare(
            'UPDATE sanction_counters SET last_number = ?
             WHERE department_id = ? AND financial_year_id = ?'
        )->execute([$next, $departmentId, $financialYearId]);

        return sprintf('%s-%d-%03d', $code, $year, $next);
    }

    /**
 * Generates the next purchase requisition number for a sanction.
 *
 * Examples:
 * GRD-2026-001-01
 * GRD-2026-001-02
 * GRD-2026-001-03
 *
 * There is no upper limit.
 * Must be called inside an open transaction.
 */
     public static function nextSubdivision(PDO $pdo, int $sanctionId): array
{
    $stmt = $pdo->prepare(
        'SELECT sanction_no, last_subdivision FROM sanctions WHERE id = ? FOR UPDATE'
    );
    $stmt->execute([$sanctionId]);

    $row = $stmt->fetch();

    if ($row === false) {
        throw new \RuntimeException('Parent sanction not found.');
    }

    $next = (int)$row['last_subdivision'] + 1;

    // 01,02,03...09,10...99,100...
    $number = str_pad((string)$next, 2, '0', STR_PAD_LEFT);

    $pdo->prepare(
        'UPDATE sanctions
         SET last_subdivision = ?
         WHERE id = ?'
    )->execute([$next, $sanctionId]);

    return [
        $number,
        $row['sanction_no'] . '-' . $number
    ];
}
    /**
     * Next purchase order number (global running number per financial year).
     */
    public static function nextPoNo(int $financialYearId): string
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare('SELECT year_code FROM financial_years WHERE id = ?');
        $stmt->execute([$financialYearId]);
        $year   = (int) ($stmt->fetch()['year_code'] ?? date('Y'));
        $prefix = sprintf('PO-%d-', $year);

        $stmt = $pdo->prepare(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(po_no, "-", -1) AS UNSIGNED)), 0) AS n
             FROM purchase_orders WHERE po_no LIKE ?'
        );
        $stmt->execute([$prefix . '%']);
        return $prefix . sprintf('%03d', (int) $stmt->fetch()['n'] + 1);
    }

    /** @return array{0:string, 1:int} department code and FY year code */
    private static function prefixParts(PDO $pdo, int $departmentId, int $financialYearId): array
    {
        // Maintenance uses its official MNT prefix for new numbers while
        // existing historical numbers remain unchanged.
        $stmt = $pdo->prepare(
            "SELECT CASE WHEN code = 'MNT' OR name IN ('Civil Department', 'Civil Works', 'Maintenance Department')
                         THEN 'MNT' ELSE code END AS code
             FROM departments WHERE id = ?"
        );
        $stmt->execute([$departmentId]);
        $code = (string) ($stmt->fetch()['code'] ?? 'GEN');

        $stmt = $pdo->prepare('SELECT year_code FROM financial_years WHERE id = ?');
        $stmt->execute([$financialYearId]);
        $year = (int) ($stmt->fetch()['year_code'] ?? date('Y'));

        return [$code, $year];
    }
}
