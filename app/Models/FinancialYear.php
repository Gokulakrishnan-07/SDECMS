<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class FinancialYear extends Model
{
    protected string $table = 'financial_years';

    public function active(): ?array
    {
        $row = $this->db()->query(
            'SELECT * FROM financial_years WHERE is_active = 1 LIMIT 1'
        )->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Resolve the FY to operate on: explicit id or the active year.
     */
    public function resolve(?int $id): ?array
    {
        return $id ? $this->find($id) : $this->active();
    }

    /**
     * Activate one financial year and deactivate the rest (single transaction).
     */
    public function activate(int $id): void
    {
        \App\Core\Database::transaction(function ($pdo) use ($id) {
            $pdo->exec('UPDATE financial_years SET is_active = 0');
            $pdo->prepare('UPDATE financial_years SET is_active = 1 WHERE id = ?')->execute([$id]);
        });
    }

    /** Periods defined for a financial year (Quarters / Halves / custom). */
    public function periods(int $financialYearId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, name, sort_order FROM financial_year_periods
             WHERE financial_year_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$financialYearId]);
        return $stmt->fetchAll();
    }

    /**
     * Replace the set of periods for a financial year (also clears any
     * per-period budget allocations tied to the old periods).
     */
    public function setPeriods(int $financialYearId, array $names): void
    {
        \App\Core\Database::transaction(function ($pdo) use ($financialYearId, $names) {
            $pdo->prepare('DELETE FROM financial_year_periods WHERE financial_year_id = ?')
                ->execute([$financialYearId]);
            $ins = $pdo->prepare(
                'INSERT INTO financial_year_periods (financial_year_id, name, sort_order) VALUES (?, ?, ?)'
            );
            $i = 0;
            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name !== '') {
                    $ins->execute([$financialYearId, mb_substr($name, 0, 50), $i++]);
                }
            }
        });
    }
}
