<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Audit
{
    public static function log(string $entity, int|string|null $entityId, string $action, ?array $before = null, ?array $after = null): void
    {
        $changes = null;
        if ($before !== null || $after !== null) {
            $diff = [];
            foreach (($after ?? []) as $k => $v) {
                if (in_array($k, ['password_hash', 'updated_at', 'created_at'], true)) {
                    continue;
                }
                $old = $before[$k] ?? null;
                if ((string) $old !== (string) $v) {
                    $diff[$k] = ['from' => $old, 'to' => $v];
                }
            }
            $changes = $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null;
        }
        try {
            Database::insert('audit_logs', [
                'company_id' => auth()->companyId(),
                'user_id'    => auth()->id(),
                'entity'     => $entity,
                'entity_id'  => $entityId === null ? null : (string) $entityId,
                'action'     => $action,
                'changes'    => $changes,
                'ip_address' => client_ip(),
                'created_at' => now_utc(),
            ]);
        } catch (\Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
