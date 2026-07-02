<?php

declare(strict_types=1);

namespace Xitique\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use Xitique\Core\Cache;
use Xitique\Core\Config;
use Xitique\Core\Database;
use Xitique\Core\HttpException;

final class XitiqueService
{
    /** @return array<string, mixed> */
    public function registerUser(string $name, string $phone, string $password): array
    {
        $name = trim($name);
        $phone = $this->normalizePhone($phone);

        if ($name === '' || $phone === '' || strlen($password) < 6) {
            throw new HttpException('Informe nome, telefone e uma senha com pelo menos 6 caracteres.', 422);
        }

        try {
            Database::connection()
                ->prepare('INSERT INTO users (name, phone, password_hash) VALUES (:name, :phone, :password_hash)')
                ->execute([
                    'name' => $name,
                    'phone' => $phone,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new HttpException('Este telefone já está registado.', 409);
            }

            throw $exception;
        }

        $user = $this->findUserByPhone($phone) ?? throw new HttpException('Não foi possível criar o utilizador.', 500);
        unset($user['password_hash']);

        return $user;
    }

    /** @return array<string, mixed> */
    public function authenticate(string $phone, string $password): array
    {
        $user = $this->findUserByPhone($this->normalizePhone($phone));

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException('Telefone ou senha inválidos.', 401);
        }

        if ($user['status'] !== 'active') {
            throw new HttpException('A conta está bloqueada.', 403);
        }

        unset($user['password_hash']);

        return $user;
    }

    /** @return array<int, array<string, mixed>> */
    public function listGroups(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                g.*,
                (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id AND gm.status = "active") AS member_count,
                c.turn_number,
                c.cycle_number,
                bu.name AS beneficiary_name
            FROM groups g
            LEFT JOIN cycles c ON c.id = g.current_cycle_id
            LEFT JOIN group_members bm ON bm.id = c.beneficiary_member_id
            LEFT JOIN users bu ON bu.id = bm.user_id
            WHERE g.organizer_user_id = :organizer_id
                OR EXISTS (
                    SELECT 1 FROM group_members own
                    WHERE own.group_id = g.id AND own.user_id = :member_user_id AND own.status = "active"
                )
            ORDER BY g.updated_at DESC, g.id DESC'
        );
        $statement->execute([
            'organizer_id' => $userId,
            'member_user_id' => $userId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public function createGroup(int $organizerId, array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $amount = (float) ($data['contribution_amount'] ?? 0);
        $frequency = (string) ($data['frequency'] ?? 'monthly');
        $startDate = trim((string) ($data['start_date'] ?? date('Y-m-d')));

        if ($name === '' || $amount <= 0 || !in_array($frequency, ['weekly', 'monthly'], true)) {
            throw new HttpException('Informe nome, valor de contribuição e frequência válida.', 422);
        }

        $groupId = Database::transaction(function (PDO $pdo) use ($organizerId, $name, $description, $amount, $frequency, $startDate): int {
            $insertGroup = $pdo->prepare(
                'INSERT INTO groups (name, description, contribution_amount, frequency, organizer_user_id, start_date, status)
                 VALUES (:name, :description, :contribution_amount, :frequency, :organizer_user_id, :start_date, "draft")'
            );
            $insertGroup->execute([
                'name' => $name,
                'description' => $description,
                'contribution_amount' => $amount,
                'frequency' => $frequency,
                'organizer_user_id' => $organizerId,
                'start_date' => $startDate !== '' ? $startDate : date('Y-m-d'),
            ]);

            $groupId = (int) $pdo->lastInsertId();

            $insertMember = $pdo->prepare(
                'INSERT INTO group_members (group_id, user_id, position) VALUES (:group_id, :user_id, 1)'
            );
            $insertMember->execute(['group_id' => $groupId, 'user_id' => $organizerId]);

            $this->log($pdo, $groupId, $organizerId, 'group_created', [
                'name' => $name,
                'contribution_amount' => $amount,
                'frequency' => $frequency,
            ]);

            return $groupId;
        });

        return $this->dashboard($groupId, $organizerId, bypassCache: true);
    }

    /** @param array<string, mixed> $data */
    public function addMember(int $groupId, int $organizerId, array $data): array
    {
        $result = Database::transaction(function (PDO $pdo) use ($groupId, $organizerId, $data): array {
            $group = $this->requireOrganizer($pdo, $groupId, $organizerId);

            if (!in_array($group['status'], ['draft', 'turn_completed'], true)) {
                throw new HttpException('Só é possível adicionar membros antes de iniciar o turno ou entre turnos.', 409);
            }

            $name = trim((string) ($data['name'] ?? ''));
            $phone = $this->normalizePhone((string) ($data['phone'] ?? ''));

            if ($name === '' || $phone === '') {
                throw new HttpException('Informe nome e telefone do membro.', 422);
            }

            $user = $this->findUserByPhone($phone, $pdo);
            $temporaryPassword = null;

            if (!$user) {
                $temporaryPassword = trim((string) ($data['temporary_password'] ?? ''));

                if ($temporaryPassword === '') {
                    $temporaryPassword = 'xq' . random_int(100000, 999999);
                }

                $createUser = $pdo->prepare(
                    'INSERT INTO users (name, phone, password_hash) VALUES (:name, :phone, :password_hash)'
                );
                $createUser->execute([
                    'name' => $name,
                    'phone' => $phone,
                    'password_hash' => password_hash($temporaryPassword, PASSWORD_DEFAULT),
                ]);

                $user = [
                    'id' => (int) $pdo->lastInsertId(),
                    'name' => $name,
                    'phone' => $phone,
                ];
            }

            $membership = $this->membershipForUser($pdo, $groupId, (int) $user['id']);

            if ($membership && $membership['status'] === 'active') {
                throw new HttpException('Este utilizador já pertence ao grupo.', 409);
            }

            if ($membership) {
                $update = $pdo->prepare(
                    'UPDATE group_members SET status = "active", removed_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $update->execute(['id' => $membership['id']]);
                $memberId = (int) $membership['id'];
                $position = (int) $membership['position'];
            } else {
                $position = $this->nextMemberPosition($pdo, $groupId);
                $insert = $pdo->prepare(
                    'INSERT INTO group_members (group_id, user_id, position) VALUES (:group_id, :user_id, :position)'
                );
                $insert->execute([
                    'group_id' => $groupId,
                    'user_id' => $user['id'],
                    'position' => $position,
                ]);
                $memberId = (int) $pdo->lastInsertId();
            }

            $this->touchGroup($pdo, $groupId);
            $this->log($pdo, $groupId, $organizerId, 'member_added', [
                'member_id' => $memberId,
                'user_id' => $user['id'],
                'position' => $position,
            ]);

            return [
                'member' => [
                    'id' => $memberId,
                    'user_id' => (int) $user['id'],
                    'name' => (string) $user['name'],
                    'phone' => (string) $user['phone'],
                    'position' => $position,
                ],
                'temporary_password' => $temporaryPassword,
            ];
        });

        $this->invalidateGroup($groupId);

        return $result;
    }

    public function removeMember(int $groupId, int $memberId, int $organizerId): array
    {
        $result = Database::transaction(function (PDO $pdo) use ($groupId, $memberId, $organizerId): array {
            $group = $this->requireOrganizer($pdo, $groupId, $organizerId);

            if (!in_array($group['status'], ['draft', 'turn_completed'], true)) {
                throw new HttpException('Só é possível remover membros antes de iniciar o turno ou entre turnos.', 409);
            }

            $member = $this->memberById($pdo, $groupId, $memberId);

            if (!$member) {
                throw new HttpException('Membro não encontrado.', 404);
            }

            if ((int) $member['user_id'] === (int) $group['organizer_user_id']) {
                throw new HttpException('O organizador não pode ser removido do próprio grupo.', 409);
            }

            $update = $pdo->prepare(
                'UPDATE group_members SET status = "removed", removed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $update->execute(['id' => $memberId]);

            $this->touchGroup($pdo, $groupId);
            $this->log($pdo, $groupId, $organizerId, 'member_removed', ['member_id' => $memberId]);

            return ['removed' => true];
        });

        $this->invalidateGroup($groupId);

        return $result;
    }

    public function startGroup(int $groupId, int $organizerId): array
    {
        $result = Database::transaction(function (PDO $pdo) use ($groupId, $organizerId): array {
            $group = $this->requireOrganizer($pdo, $groupId, $organizerId);

            if (!in_array($group['status'], ['draft', 'turn_completed'], true)) {
                throw new HttpException('O grupo já tem um turno activo.', 409);
            }

            $activeCount = $this->activeMemberCount($pdo, $groupId);

            if ($activeCount < 2) {
                throw new HttpException('Adicione pelo menos 2 membros para iniciar o xitique.', 422);
            }

            $turnNumber = $group['status'] === 'turn_completed'
                ? $this->nextTurnNumber($pdo, $groupId)
                : 1;

            $beneficiary = $this->firstActiveMember($pdo, $groupId);
            $cycleId = $this->createCycle($pdo, $group, $turnNumber, 1, (int) $beneficiary['id'], date('Y-m-d'));

            $updateGroup = $pdo->prepare(
                'UPDATE groups SET status = "active", current_cycle_id = :cycle_id, updated_at = CURRENT_TIMESTAMP WHERE id = :group_id'
            );
            $updateGroup->execute(['cycle_id' => $cycleId, 'group_id' => $groupId]);

            $this->log($pdo, $groupId, $organizerId, 'turn_started', [
                'turn_number' => $turnNumber,
                'cycle_id' => $cycleId,
                'beneficiary_member_id' => $beneficiary['id'],
            ]);

            return ['cycle_id' => $cycleId, 'turn_number' => $turnNumber];
        });

        $this->invalidateGroup($groupId);

        return $result;
    }

    /** @return array<string, mixed> */
    public function dashboard(int $groupId, int $userId, bool $bypassCache = false): array
    {
        $key = "group_{$groupId}_dashboard_user_{$userId}";
        $ttl = Config::int('CACHE_TTL_SECONDS', 45);

        if ($bypassCache) {
            return $this->buildDashboard($groupId, $userId);
        }

        return Cache::remember($key, $ttl, fn (): array => $this->buildDashboard($groupId, $userId));
    }

    /** @param array<string, mixed> $data */
    public function registerPayment(int $groupId, int $organizerId, array $data): array
    {
        $result = Database::transaction(function (PDO $pdo) use ($groupId, $organizerId, $data): array {
            $group = $this->requireOrganizer($pdo, $groupId, $organizerId);

            if ($group['status'] !== 'active' || empty($group['current_cycle_id'])) {
                throw new HttpException('O grupo não tem ciclo activo.', 409);
            }

            $cycle = $this->cycleById($pdo, (int) $group['current_cycle_id']);

            if (!$cycle || $cycle['status'] !== 'open') {
                throw new HttpException('O ciclo actual não está aberto.', 409);
            }

            $memberId = (int) ($data['member_id'] ?? 0);
            $member = $this->memberById($pdo, $groupId, $memberId);

            if (!$member || $member['status'] !== 'active') {
                throw new HttpException('Membro activo não encontrado.', 404);
            }

            $amount = (float) ($data['amount'] ?? $cycle['contribution_amount_snapshot']);

            if ($amount <= 0) {
                throw new HttpException('O valor do pagamento deve ser positivo.', 422);
            }

            $method = (string) ($data['method'] ?? 'cash');

            if (!in_array($method, ['cash', 'mpesa', 'emola', 'bank_transfer', 'other'], true)) {
                throw new HttpException('Método de pagamento inválido.', 422);
            }

            $reference = trim((string) ($data['reference'] ?? ''));
            $paidAt = trim((string) ($data['paid_at'] ?? date('Y-m-d H:i:s')));
            $notes = trim((string) ($data['notes'] ?? ''));

            $existing = $pdo->prepare(
                'SELECT id FROM payments WHERE cycle_id = :cycle_id AND member_id = :member_id LIMIT 1'
            );
            $existing->execute(['cycle_id' => $cycle['id'], 'member_id' => $memberId]);
            $payment = $existing->fetch(PDO::FETCH_ASSOC);

            if ($payment) {
                $update = $pdo->prepare(
                    'UPDATE payments
                     SET amount = :amount, method = :method, reference = :reference, paid_at = :paid_at,
                         notes = :notes, status = "confirmed", updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $update->execute([
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference !== '' ? $reference : null,
                    'paid_at' => $paidAt,
                    'notes' => $notes !== '' ? $notes : null,
                    'id' => $payment['id'],
                ]);
                $paymentId = (int) $payment['id'];
                $action = 'payment_updated';
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO payments (
                        cycle_id, member_id, amount, method, reference, confirmed_by_user_id, paid_at, notes
                    ) VALUES (
                        :cycle_id, :member_id, :amount, :method, :reference, :confirmed_by_user_id, :paid_at, :notes
                    )'
                );
                $insert->execute([
                    'cycle_id' => $cycle['id'],
                    'member_id' => $memberId,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference !== '' ? $reference : null,
                    'confirmed_by_user_id' => $organizerId,
                    'paid_at' => $paidAt,
                    'notes' => $notes !== '' ? $notes : null,
                ]);
                $paymentId = (int) $pdo->lastInsertId();
                $action = 'payment_confirmed';
            }

            $this->log($pdo, $groupId, $organizerId, $action, [
                'payment_id' => $paymentId,
                'cycle_id' => $cycle['id'],
                'member_id' => $memberId,
                'amount' => $amount,
                'method' => $method,
            ]);

            $completion = $this->maybeCompleteCycle($pdo, $group, $cycle, $organizerId);
            $this->touchGroup($pdo, $groupId);

            return [
                'payment_id' => $paymentId,
                'cycle_completed' => $completion['completed'],
                'next_cycle_id' => $completion['next_cycle_id'] ?? null,
                'turn_completed' => $completion['turn_completed'] ?? false,
                'payout' => $completion['payout'] ?? null,
            ];
        });

        $this->invalidateGroup($groupId);

        return $result;
    }

    public function confirmPayout(int $payoutId, int $userId): array
    {
        $result = Database::transaction(function (PDO $pdo) use ($payoutId, $userId): array {
            $statement = $pdo->prepare(
                'SELECT
                    p.*,
                    c.group_id,
                    g.organizer_user_id,
                    gm.user_id AS beneficiary_user_id
                 FROM payouts p
                 JOIN cycles c ON c.id = p.cycle_id
                 JOIN groups g ON g.id = c.group_id
                 JOIN group_members gm ON gm.id = p.beneficiary_member_id
                 WHERE p.id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $payoutId]);
            $payout = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$payout) {
                throw new HttpException('Comprovativo de recebimento não encontrado.', 404);
            }

            $canConfirm = (int) $payout['organizer_user_id'] === $userId
                || (int) $payout['beneficiary_user_id'] === $userId;

            if (!$canConfirm) {
                throw new HttpException('Não tem permissão para confirmar este recebimento.', 403);
            }

            if ($payout['status'] !== 'received') {
                $update = $pdo->prepare(
                    'UPDATE payouts SET status = "received", received_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
                );
                $update->execute(['id' => $payoutId]);

                $this->log($pdo, (int) $payout['group_id'], $userId, 'payout_received', [
                    'payout_id' => $payoutId,
                    'receipt_code' => $payout['receipt_code'],
                ]);
                $this->touchGroup($pdo, (int) $payout['group_id']);
            }

            return [
                'id' => $payoutId,
                'status' => 'received',
                'receipt_code' => $payout['receipt_code'],
            ];
        });

        if (isset($result['id'])) {
            $groupId = $this->groupIdForPayout($payoutId);
            $this->invalidateGroup($groupId);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function buildDashboard(int $groupId, int $userId): array
    {
        $pdo = Database::connection();
        $group = $this->groupVisibleToUser($pdo, $groupId, $userId);
        $currentMember = $this->membershipForUser($pdo, $groupId, $userId);
        $currentCycle = null;
        $payments = [];
        $paidCount = 0;
        $paidTotal = 0.0;

        if (!empty($group['current_cycle_id'])) {
            $currentCycle = $this->currentCycleSummary($pdo, (int) $group['current_cycle_id']);
            $payments = $this->paymentsForCycle($pdo, (int) $group['current_cycle_id']);
            $paidCount = count($payments);
            $paidTotal = array_sum(array_map(static fn (array $payment): float => (float) $payment['amount'], $payments));
            $currentCycle['paid_count'] = $paidCount;
            $currentCycle['paid_total'] = $paidTotal;
            $currentCycle['missing_count'] = max(0, $this->activeMemberCount($pdo, $groupId) - $paidCount);
        }

        $members = $this->membersForGroup($pdo, $groupId, $group['current_cycle_id'] ? (int) $group['current_cycle_id'] : null);
        $payouts = $this->payoutsForGroup($pdo, $groupId);
        $activities = $this->activitiesForGroup($pdo, $groupId);
        $isOrganizer = (int) $group['organizer_user_id'] === $userId;
        $isBeneficiary = $currentCycle && $currentMember
            ? (int) $currentCycle['beneficiary_member_id'] === (int) $currentMember['id']
            : false;

        return [
            'group' => $group,
            'role' => [
                'is_organizer' => $isOrganizer,
                'is_member' => $currentMember !== null,
                'is_current_beneficiary' => $isBeneficiary,
                'member_id' => $currentMember['id'] ?? null,
            ],
            'current_cycle' => $currentCycle,
            'members' => $members,
            'payments' => $payments,
            'payouts' => $payouts,
            'activities' => $activities,
            'summary' => [
                'member_count' => count(array_filter($members, static fn (array $member): bool => $member['status'] === 'active')),
                'paid_count' => $paidCount,
                'paid_total' => $paidTotal,
                'expected_total' => $currentCycle['expected_total'] ?? 0,
                'progress_percent' => $currentCycle
                    ? (int) round(($paidCount / max(1, count(array_filter($members, static fn (array $member): bool => $member['status'] === 'active')))) * 100)
                    : 0,
            ],
            'capabilities' => [
                'can_manage' => $isOrganizer,
                'can_edit_members' => $isOrganizer && in_array($group['status'], ['draft', 'turn_completed'], true),
                'can_start_turn' => $isOrganizer && in_array($group['status'], ['draft', 'turn_completed'], true),
                'can_register_payments' => $isOrganizer && $group['status'] === 'active',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function maybeCompleteCycle(PDO $pdo, array $group, array $cycle, int $actorId): array
    {
        $groupId = (int) $group['id'];
        $activeCount = $this->activeMemberCount($pdo, $groupId);
        $paidStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM payments WHERE cycle_id = :cycle_id AND status = "confirmed"'
        );
        $paidStatement->execute(['cycle_id' => $cycle['id']]);
        $paidCount = (int) $paidStatement->fetchColumn();

        if ($paidCount < $activeCount) {
            return ['completed' => false];
        }

        $totalStatement = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE cycle_id = :cycle_id AND status = "confirmed"'
        );
        $totalStatement->execute(['cycle_id' => $cycle['id']]);
        $total = (float) $totalStatement->fetchColumn();

        $updateCycle = $pdo->prepare(
            'UPDATE cycles SET status = "completed", completed_at = CURRENT_TIMESTAMP, expected_total = :total,
                updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $updateCycle->execute(['total' => $total, 'id' => $cycle['id']]);

        $payout = $this->createPayout($pdo, $cycle, $total);

        $this->log($pdo, $groupId, $actorId, 'cycle_completed', [
            'cycle_id' => $cycle['id'],
            'turn_number' => $cycle['turn_number'],
            'cycle_number' => $cycle['cycle_number'],
            'payout_id' => $payout['id'],
            'amount' => $total,
        ]);

        if ((int) $cycle['cycle_number'] >= $activeCount) {
            $updateGroup = $pdo->prepare(
                'UPDATE groups SET status = "turn_completed", current_cycle_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $updateGroup->execute(['id' => $groupId]);

            $this->log($pdo, $groupId, $actorId, 'turn_completed', [
                'turn_number' => $cycle['turn_number'],
            ]);

            return [
                'completed' => true,
                'turn_completed' => true,
                'payout' => $payout,
            ];
        }

        $nextBeneficiary = $this->nextBeneficiary($pdo, $groupId, (int) $cycle['beneficiary_member_id']);
        $nextCycleId = $this->createCycle(
            $pdo,
            $group,
            (int) $cycle['turn_number'],
            (int) $cycle['cycle_number'] + 1,
            (int) $nextBeneficiary['id'],
            (string) $cycle['due_date']
        );

        $updateGroup = $pdo->prepare(
            'UPDATE groups SET current_cycle_id = :cycle_id, updated_at = CURRENT_TIMESTAMP WHERE id = :group_id'
        );
        $updateGroup->execute(['cycle_id' => $nextCycleId, 'group_id' => $groupId]);

        $this->log($pdo, $groupId, $actorId, 'cycle_opened', [
            'cycle_id' => $nextCycleId,
            'beneficiary_member_id' => $nextBeneficiary['id'],
        ]);

        return [
            'completed' => true,
            'turn_completed' => false,
            'next_cycle_id' => $nextCycleId,
            'payout' => $payout,
        ];
    }

    /** @return array<string, mixed> */
    private function createPayout(PDO $pdo, array $cycle, float $total): array
    {
        $receiptCode = $this->receiptCode($pdo);
        $insert = $pdo->prepare(
            'INSERT INTO payouts (cycle_id, beneficiary_member_id, amount, receipt_code)
             VALUES (:cycle_id, :beneficiary_member_id, :amount, :receipt_code)'
        );
        $insert->execute([
            'cycle_id' => $cycle['id'],
            'beneficiary_member_id' => $cycle['beneficiary_member_id'],
            'amount' => $total,
            'receipt_code' => $receiptCode,
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'amount' => $total,
            'receipt_code' => $receiptCode,
            'status' => 'available',
        ];
    }

    private function createCycle(PDO $pdo, array $group, int $turnNumber, int $cycleNumber, int $beneficiaryMemberId, string $baseDate): int
    {
        $activeCount = $this->activeMemberCount($pdo, (int) $group['id']);
        $dueDate = $this->nextDueDate($baseDate, (string) $group['frequency']);
        $expectedTotal = $activeCount * (float) $group['contribution_amount'];

        $insert = $pdo->prepare(
            'INSERT INTO cycles (
                group_id, turn_number, cycle_number, beneficiary_member_id, due_date,
                contribution_amount_snapshot, expected_total, status
            ) VALUES (
                :group_id, :turn_number, :cycle_number, :beneficiary_member_id, :due_date,
                :contribution_amount_snapshot, :expected_total, "open"
            )'
        );
        $insert->execute([
            'group_id' => $group['id'],
            'turn_number' => $turnNumber,
            'cycle_number' => $cycleNumber,
            'beneficiary_member_id' => $beneficiaryMemberId,
            'due_date' => $dueDate,
            'contribution_amount_snapshot' => $group['contribution_amount'],
            'expected_total' => $expectedTotal,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @return array<string, mixed> */
    private function groupVisibleToUser(PDO $pdo, int $groupId, int $userId): array
    {
        $statement = $pdo->prepare('SELECT * FROM groups WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $groupId]);
        $group = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$group) {
            throw new HttpException('Grupo não encontrado.', 404);
        }

        if ((int) $group['organizer_user_id'] === $userId) {
            return $group;
        }

        $membership = $this->membershipForUser($pdo, $groupId, $userId);

        if (!$membership || $membership['status'] !== 'active') {
            throw new HttpException('Não tem acesso a este grupo.', 403);
        }

        return $group;
    }

    /** @return array<string, mixed> */
    private function requireOrganizer(PDO $pdo, int $groupId, int $userId): array
    {
        $statement = $pdo->prepare('SELECT * FROM groups WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $groupId]);
        $group = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$group) {
            throw new HttpException('Grupo não encontrado.', 404);
        }

        if ((int) $group['organizer_user_id'] !== $userId) {
            throw new HttpException('Apenas o organizador pode executar esta acção.', 403);
        }

        return $group;
    }

    /** @return array<string, mixed>|null */
    private function findUserByPhone(string $phone, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare('SELECT * FROM users WHERE phone = :phone LIMIT 1');
        $statement->execute(['phone' => $phone]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /** @return array<string, mixed>|null */
    private function membershipForUser(PDO $pdo, int $groupId, int $userId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT * FROM group_members WHERE group_id = :group_id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute(['group_id' => $groupId, 'user_id' => $userId]);
        $membership = $statement->fetch(PDO::FETCH_ASSOC);

        return $membership ?: null;
    }

    /** @return array<string, mixed>|null */
    private function memberById(PDO $pdo, int $groupId, int $memberId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT gm.*, u.name, u.phone
             FROM group_members gm
             JOIN users u ON u.id = gm.user_id
             WHERE gm.group_id = :group_id AND gm.id = :member_id
             LIMIT 1'
        );
        $statement->execute(['group_id' => $groupId, 'member_id' => $memberId]);
        $member = $statement->fetch(PDO::FETCH_ASSOC);

        return $member ?: null;
    }

    /** @return array<string, mixed>|null */
    private function cycleById(PDO $pdo, int $cycleId): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM cycles WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $cycleId]);
        $cycle = $statement->fetch(PDO::FETCH_ASSOC);

        return $cycle ?: null;
    }

    /** @return array<string, mixed> */
    private function currentCycleSummary(PDO $pdo, int $cycleId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                c.*,
                gm.position AS beneficiary_position,
                u.name AS beneficiary_name,
                u.phone AS beneficiary_phone
             FROM cycles c
             JOIN group_members gm ON gm.id = c.beneficiary_member_id
             JOIN users u ON u.id = gm.user_id
             WHERE c.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $cycleId]);
        $cycle = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$cycle) {
            throw new HttpException('Ciclo actual não encontrado.', 404);
        }

        return $cycle;
    }

    /** @return array<int, array<string, mixed>> */
    private function membersForGroup(PDO $pdo, int $groupId, ?int $cycleId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                gm.id,
                gm.user_id,
                gm.position,
                gm.status,
                gm.joined_at,
                gm.removed_at,
                u.name,
                u.phone,
                p.id AS payment_id,
                p.amount AS paid_amount,
                p.method AS payment_method,
                p.reference AS payment_reference,
                p.paid_at
             FROM group_members gm
             JOIN users u ON u.id = gm.user_id
             LEFT JOIN payments p ON p.member_id = gm.id AND p.cycle_id = :cycle_id AND p.status = "confirmed"
             WHERE gm.group_id = :group_id
             ORDER BY gm.position ASC'
        );
        $statement->execute([
            'group_id' => $groupId,
            'cycle_id' => $cycleId ?? 0,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function paymentsForCycle(PDO $pdo, int $cycleId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                p.*,
                gm.position,
                u.name AS member_name,
                u.phone AS member_phone,
                confirmer.name AS confirmed_by_name
             FROM payments p
             JOIN group_members gm ON gm.id = p.member_id
             JOIN users u ON u.id = gm.user_id
             JOIN users confirmer ON confirmer.id = p.confirmed_by_user_id
             WHERE p.cycle_id = :cycle_id AND p.status = "confirmed"
             ORDER BY p.paid_at DESC, p.id DESC'
        );
        $statement->execute(['cycle_id' => $cycleId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function payoutsForGroup(PDO $pdo, int $groupId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                p.*,
                c.turn_number,
                c.cycle_number,
                gm.user_id AS beneficiary_user_id,
                u.name AS beneficiary_name,
                u.phone AS beneficiary_phone
             FROM payouts p
             JOIN cycles c ON c.id = p.cycle_id
             JOIN group_members gm ON gm.id = p.beneficiary_member_id
             JOIN users u ON u.id = gm.user_id
             WHERE c.group_id = :group_id
             ORDER BY p.available_at DESC'
        );
        $statement->execute(['group_id' => $groupId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array<string, mixed>> */
    private function activitiesForGroup(PDO $pdo, int $groupId): array
    {
        $statement = $pdo->prepare(
            'SELECT
                a.*,
                u.name AS actor_name
             FROM activity_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.group_id = :group_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT 30'
        );
        $statement->execute(['group_id' => $groupId]);

        $activities = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activities as &$activity) {
            $activity['payload'] = json_decode((string) $activity['payload_json'], true) ?: [];
            unset($activity['payload_json']);
        }

        return $activities;
    }

    private function activeMemberCount(PDO $pdo, int $groupId): int
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM group_members WHERE group_id = :group_id AND status = "active"'
        );
        $statement->execute(['group_id' => $groupId]);

        return (int) $statement->fetchColumn();
    }

    private function nextMemberPosition(PDO $pdo, int $groupId): int
    {
        $statement = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM group_members WHERE group_id = :group_id');
        $statement->execute(['group_id' => $groupId]);

        return (int) $statement->fetchColumn();
    }

    /** @return array<string, mixed> */
    private function firstActiveMember(PDO $pdo, int $groupId): array
    {
        $statement = $pdo->prepare(
            'SELECT * FROM group_members WHERE group_id = :group_id AND status = "active" ORDER BY position ASC LIMIT 1'
        );
        $statement->execute(['group_id' => $groupId]);
        $member = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new HttpException('Nenhum membro activo encontrado.', 422);
        }

        return $member;
    }

    /** @return array<string, mixed> */
    private function nextBeneficiary(PDO $pdo, int $groupId, int $currentBeneficiaryMemberId): array
    {
        $current = $this->memberById($pdo, $groupId, $currentBeneficiaryMemberId);

        if (!$current) {
            throw new HttpException('Beneficiário actual não encontrado.', 404);
        }

        $statement = $pdo->prepare(
            'SELECT * FROM group_members
             WHERE group_id = :group_id AND status = "active" AND position > :position
             ORDER BY position ASC
             LIMIT 1'
        );
        $statement->execute([
            'group_id' => $groupId,
            'position' => $current['position'],
        ]);
        $next = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$next) {
            throw new HttpException('Próximo beneficiário não encontrado.', 409);
        }

        return $next;
    }

    private function nextTurnNumber(PDO $pdo, int $groupId): int
    {
        $statement = $pdo->prepare('SELECT COALESCE(MAX(turn_number), 0) + 1 FROM cycles WHERE group_id = :group_id');
        $statement->execute(['group_id' => $groupId]);

        return (int) $statement->fetchColumn();
    }

    private function groupIdForPayout(int $payoutId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT c.group_id FROM payouts p JOIN cycles c ON c.id = p.cycle_id WHERE p.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $payoutId]);

        return (int) $statement->fetchColumn();
    }

    private function receiptCode(PDO $pdo): string
    {
        do {
            $code = 'XTQ-' . date('Ymd') . '-' . random_int(100000, 999999);
            $statement = $pdo->prepare('SELECT COUNT(*) FROM payouts WHERE receipt_code = :code');
            $statement->execute(['code' => $code]);
        } while ((int) $statement->fetchColumn() > 0);

        return $code;
    }

    private function nextDueDate(string $baseDate, string $frequency): string
    {
        $date = new DateTimeImmutable($baseDate);

        return $date->modify($frequency === 'weekly' ? '+1 week' : '+1 month')->format('Y-m-d');
    }

    /** @param array<string, mixed> $payload */
    private function log(PDO $pdo, int $groupId, ?int $userId, string $action, array $payload = []): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO activity_logs (group_id, user_id, action, payload_json)
             VALUES (:group_id, :user_id, :action, :payload_json)'
        );
        $statement->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
            'action' => $action,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function touchGroup(PDO $pdo, int $groupId): void
    {
        $statement = $pdo->prepare('UPDATE groups SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $statement->execute(['id' => $groupId]);
    }

    private function invalidateGroup(int $groupId): void
    {
        Cache::forgetPrefix("group_{$groupId}_");
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if (str_starts_with($phone, '+258')) {
            return substr($phone, 4);
        }

        if (str_starts_with($phone, '258') && strlen($phone) === 12) {
            return substr($phone, 3);
        }

        return $phone;
    }
}
