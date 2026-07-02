<?php

declare(strict_types=1);

namespace Xitique\Services;

use PDO;

final class DatabaseSeeder
{
    public static function seed(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        if ($count > 0) {
            return;
        }

        $pdo->beginTransaction();

        try {
            $password = password_hash('password', PASSWORD_DEFAULT);
            $users = [
                ['Dona Amélia', '840000001'],
                ['Mateus Nhantumbo', '840000002'],
                ['Carla Zandamela', '840000003'],
                ['Inês Mabunda', '840000004'],
                ['Jorge Cossa', '840000005'],
            ];

            $insertUser = $pdo->prepare(
                'INSERT INTO users (name, phone, password_hash) VALUES (:name, :phone, :password_hash)'
            );

            $userIds = [];

            foreach ($users as [$name, $phone]) {
                $insertUser->execute([
                    'name' => $name,
                    'phone' => $phone,
                    'password_hash' => $password,
                ]);
                $userIds[] = (int) $pdo->lastInsertId();
            }

            $insertGroup = $pdo->prepare(
                'INSERT INTO groups (name, description, contribution_amount, frequency, organizer_user_id, start_date, status)
                 VALUES (:name, :description, :contribution_amount, :frequency, :organizer_user_id, :start_date, :status)'
            );
            $insertGroup->execute([
                'name' => 'Xitique da Família',
                'description' => 'Grupo mensal de demonstração com 5 membros.',
                'contribution_amount' => 1000,
                'frequency' => 'monthly',
                'organizer_user_id' => $userIds[0],
                'start_date' => date('Y-m-d'),
                'status' => 'active',
            ]);
            $groupId = (int) $pdo->lastInsertId();

            $insertMember = $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, position) VALUES (:group_id, :user_id, :position)'
            );

            $memberIds = [];

            foreach ($userIds as $index => $userId) {
                $insertMember->execute([
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'position' => $index + 1,
                ]);
                $memberIds[] = (int) $pdo->lastInsertId();
            }

            $expectedTotal = count($memberIds) * 1000;
            $insertCycle = $pdo->prepare(
                'INSERT INTO cycles (
                    group_id, turn_number, cycle_number, beneficiary_member_id, due_date,
                    contribution_amount_snapshot, expected_total, status
                ) VALUES (
                    :group_id, 1, 1, :beneficiary_member_id, :due_date,
                    :contribution_amount_snapshot, :expected_total, "open"
                )'
            );
            $insertCycle->execute([
                'group_id' => $groupId,
                'beneficiary_member_id' => $memberIds[0],
                'due_date' => date('Y-m-d', strtotime('+1 month')),
                'contribution_amount_snapshot' => 1000,
                'expected_total' => $expectedTotal,
            ]);
            $cycleId = (int) $pdo->lastInsertId();

            $updateGroup = $pdo->prepare('UPDATE groups SET current_cycle_id = :cycle_id WHERE id = :group_id');
            $updateGroup->execute(['cycle_id' => $cycleId, 'group_id' => $groupId]);

            $insertPayment = $pdo->prepare(
                'INSERT INTO payments (cycle_id, member_id, amount, method, reference, confirmed_by_user_id, paid_at, notes)
                 VALUES (:cycle_id, :member_id, :amount, :method, :reference, :confirmed_by_user_id, :paid_at, :notes)'
            );

            foreach (array_slice($memberIds, 0, 3) as $index => $memberId) {
                $insertPayment->execute([
                    'cycle_id' => $cycleId,
                    'member_id' => $memberId,
                    'amount' => 1000,
                    'method' => $index === 0 ? 'cash' : 'mpesa',
                    'reference' => $index === 0 ? null : 'MPESA-DEMO-' . ($index + 1),
                    'confirmed_by_user_id' => $userIds[0],
                    'paid_at' => date('Y-m-d H:i:s'),
                    'notes' => 'Pagamento de demonstração.',
                ]);
            }

            $log = $pdo->prepare(
                'INSERT INTO activity_logs (group_id, user_id, action, payload_json)
                 VALUES (:group_id, :user_id, :action, :payload_json)'
            );
            $log->execute([
                'group_id' => $groupId,
                'user_id' => $userIds[0],
                'action' => 'demo_created',
                'payload_json' => json_encode(['message' => 'Grupo de demonstração criado.'], JSON_UNESCAPED_UNICODE),
            ]);

            $pdo->commit();
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }
}

