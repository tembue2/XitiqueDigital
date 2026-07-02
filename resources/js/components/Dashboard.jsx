import {
  Banknote,
  Check,
  CircleDollarSign,
  Clock3,
  Play,
  ReceiptText,
  Trash2,
  UserPlus,
  UsersRound,
} from 'lucide-react';
import { activityLabel, money, readableDate, statusLabel } from '../lib/format.js';
import { Badge } from './ui/Badge.jsx';
import { Button } from './ui/Button.jsx';
import { ConfirmDialog } from './ui/ConfirmDialog.jsx';
import { EmptyState, InlineAlert, SkeletonBlock } from './ui/Feedback.jsx';
import { Field, SelectInput, TextInput } from './ui/Field.jsx';
import { Panel } from './ui/Panel.jsx';
import { ProgressBar } from './ui/ProgressBar.jsx';

export function Dashboard({
  actionLoading,
  dashboard,
  dashboardLoading,
  onAddMember,
  onConfirmPayout,
  onRegisterPayment,
  onRemoveMember,
  onStartTurn,
}) {
  if (dashboardLoading && !dashboard) {
    return <DashboardSkeleton />;
  }

  if (!dashboard) {
    return (
      <EmptyState title="Crie ou seleccione um grupo">
        Quando criar um grupo, o painel do ciclo actual aparece aqui.
      </EmptyState>
    );
  }

  const { capabilities, current_cycle: cycle, group, role, summary } = dashboard;

  return (
    <>
      <header className="topbar">
        <div>
          <p className="eyebrow">Painel do grupo</p>
          <h2>{group.name}</h2>
          <div className="badge-row">
            <Badge>{statusLabel(group.status)}</Badge>
            {role.is_organizer ? <Badge tone="success">Organizador</Badge> : null}
            {role.is_current_beneficiary ? <Badge tone="info">Beneficiário actual</Badge> : null}
          </div>
        </div>
        <div className="actions">
          {capabilities.can_start_turn ? (
            <Button
              icon={Play}
              isLoading={actionLoading === 'start-turn'}
              onClick={onStartTurn}
              variant="gold"
            >
              {group.status === 'turn_completed' ? 'Novo turno' : 'Iniciar'}
            </Button>
          ) : null}
        </div>
      </header>

      {dashboardLoading ? <InlineAlert>Actualizando dados do grupo...</InlineAlert> : null}

      <section className="stat-grid" aria-label="Resumo do grupo">
        <StatCard icon={CircleDollarSign} label="Contribuição" value={money(group.contribution_amount, group.currency)} />
        <StatCard icon={UsersRound} label="Membros activos" value={summary.member_count} />
        <StatCard icon={ReceiptText} label="Beneficiário" value={cycle ? cycle.beneficiary_name : '-'} />
        <StatCard icon={Banknote} label="Pago no ciclo" value={money(summary.paid_total, group.currency)} />
      </section>

      <Panel title="Progresso do ciclo">
        {cycle ? (
          <div className="cycle-grid">
            <div>
              <span className="metric-label">Turno</span>
              <strong>{cycle.turn_number}</strong>
            </div>
            <div>
              <span className="metric-label">Ciclo</span>
              <strong>{cycle.cycle_number}</strong>
            </div>
            <div>
              <span className="metric-label">Prazo</span>
              <strong>{readableDate(cycle.due_date)}</strong>
            </div>
            <div>
              <span className="metric-label">Pendentes</span>
              <strong>{cycle.missing_count}</strong>
            </div>
            <ProgressBar label={`${summary.paid_count}/${summary.member_count} pagamentos`} value={summary.progress_percent} />
          </div>
        ) : (
          <InlineAlert>Não há ciclo aberto. O organizador pode iniciar ou reiniciar o turno.</InlineAlert>
        )}
      </Panel>

      <section className="grid-two">
        <Panel title="Membros">
          <MembersTable
            dashboard={dashboard}
            onRemoveMember={onRemoveMember}
            removingMemberId={actionLoading?.startsWith('remove-member:') ? Number(actionLoading.split(':')[1]) : null}
          />
        </Panel>
        <Panel title="Registar pagamento">
          {capabilities.can_register_payments && cycle ? (
            <PaymentForm
              dashboard={dashboard}
              isLoading={actionLoading === 'register-payment'}
              onSubmit={onRegisterPayment}
            />
          ) : (
            <InlineAlert>Sem ciclo aberto para pagamentos ou sem permissão de organizador.</InlineAlert>
          )}
        </Panel>
      </section>

      <section className="grid-two">
        <Panel title="Comprovativos">
          <PayoutsTable
            dashboard={dashboard}
            loadingId={actionLoading?.startsWith('confirm-payout:') ? Number(actionLoading.split(':')[1]) : null}
            onConfirmPayout={onConfirmPayout}
          />
        </Panel>
        <Panel title="Adicionar membro">
          {capabilities.can_edit_members ? (
            <MemberForm isLoading={actionLoading === 'add-member'} onSubmit={onAddMember} />
          ) : (
            <InlineAlert>Edição de membros disponível antes do turno ou entre turnos.</InlineAlert>
          )}
        </Panel>
      </section>

      <Panel title="Histórico">
        <Activities activities={dashboard.activities} />
      </Panel>
    </>
  );
}

function DashboardSkeleton() {
  return (
    <section aria-label="A carregar painel">
      <SkeletonBlock rows={6} />
    </section>
  );
}

function StatCard({ icon: Icon, label, value }) {
  return (
    <div className="stat">
      <span>
        <Icon aria-hidden="true" />
        {label}
      </span>
      <strong>{value}</strong>
    </div>
  );
}

function MembersTable({ dashboard, onRemoveMember, removingMemberId }) {
  const canEdit = dashboard.capabilities.can_edit_members;
  const organizerId = Number(dashboard.group.organizer_user_id);

  if (!dashboard.members.length) {
    return <InlineAlert>Nenhum membro registado.</InlineAlert>;
  }

  return (
    <div className="table-wrap">
      <table>
        <caption>Membros do grupo e estado de pagamento do ciclo actual</caption>
        <thead>
          <tr>
            <th scope="col">Posição</th>
            <th scope="col">Nome</th>
            <th scope="col">Telefone</th>
            <th scope="col">Estado</th>
            <th scope="col">Pagamento</th>
            <th scope="col"><span className="sr-only">Acções</span></th>
          </tr>
        </thead>
        <tbody>
          {dashboard.members.map((member) => (
            <tr key={member.id}>
              <td>{member.position}</td>
              <td>{member.name}</td>
              <td>{member.phone}</td>
              <td>{member.status === 'active' ? 'Activo' : 'Removido'}</td>
              <td>
                {member.payment_id ? <Badge tone="success">Pago</Badge> : <Badge tone="warning">Pendente</Badge>}
              </td>
              <td>
                <div className="actions">
                  {canEdit && Number(member.user_id) !== organizerId && member.status === 'active' ? (
                    <ConfirmDialog
                      confirmLabel="Remover"
                      description={`Isto remove ${member.name} do grupo. A acção só é permitida antes de iniciar ou entre turnos.`}
                      isLoading={removingMemberId === Number(member.id)}
                      onConfirm={() => onRemoveMember(member.id)}
                      title="Remover membro?"
                    >
                      <Button icon={Trash2} size="sm" variant="danger">
                        Remover
                      </Button>
                    </ConfirmDialog>
                  ) : null}
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function PaymentForm({ dashboard, isLoading, onSubmit }) {
  const unpaidMembers = dashboard.members.filter((member) => member.status === 'active' && !member.payment_id);

  if (!unpaidMembers.length) {
    return <InlineAlert tone="success">Todos os membros já pagaram este ciclo.</InlineAlert>;
  }

  return (
    <form className="inline-form" onSubmit={onSubmit}>
      <Field className="full" id="payment-member" label="Membro" required>
        {({ describedBy }) => (
          <SelectInput describedBy={describedBy} id="payment-member" name="member_id" required>
            {unpaidMembers.map((member) => (
              <option key={member.id} value={member.id}>
                {member.position}. {member.name}
              </option>
            ))}
          </SelectInput>
        )}
      </Field>
      <Field id="payment-amount" label="Valor" required>
        {({ describedBy }) => (
          <TextInput
            defaultValue={dashboard.current_cycle.contribution_amount_snapshot}
            describedBy={describedBy}
            id="payment-amount"
            min="1"
            name="amount"
            required
            step="0.01"
            type="number"
          />
        )}
      </Field>
      <Field id="payment-method" label="Método" required>
        {({ describedBy }) => (
          <SelectInput describedBy={describedBy} id="payment-method" name="method" required>
            <option value="cash">Dinheiro</option>
            <option value="mpesa">M-Pesa</option>
            <option value="emola">e-Mola</option>
            <option value="bank_transfer">Transferência</option>
            <option value="other">Outro</option>
          </SelectInput>
        )}
      </Field>
      <Field className="full" id="payment-reference" hint="Opcional: código M-Pesa, nota ou referência interna." label="Referência">
        {({ describedBy }) => (
          <TextInput describedBy={describedBy} id="payment-reference" name="reference" />
        )}
      </Field>
      <Button className="full" icon={Check} isLoading={isLoading} type="submit" variant="primary">
        Confirmar pagamento
      </Button>
    </form>
  );
}

function MemberForm({ isLoading, onSubmit }) {
  return (
    <form className="inline-form" onSubmit={onSubmit}>
      <Field id="member-name" label="Nome" required>
        {({ describedBy }) => (
          <TextInput describedBy={describedBy} id="member-name" name="name" required />
        )}
      </Field>
      <Field id="member-phone" label="Telefone" required>
        {({ describedBy }) => (
          <TextInput describedBy={describedBy} id="member-phone" name="phone" required />
        )}
      </Field>
      <Button className="full" icon={UserPlus} isLoading={isLoading} type="submit" variant="primary">
        Adicionar
      </Button>
    </form>
  );
}

function PayoutsTable({ dashboard, loadingId, onConfirmPayout }) {
  if (!dashboard.payouts.length) {
    return <InlineAlert>Ainda não há comprovativos.</InlineAlert>;
  }

  return (
    <div className="table-wrap">
      <table>
        <caption>Comprovativos de recebimento por ciclo</caption>
        <thead>
          <tr>
            <th scope="col">Ciclo</th>
            <th scope="col">Beneficiário</th>
            <th scope="col">Valor</th>
            <th scope="col">Código</th>
            <th scope="col">Estado</th>
            <th scope="col"><span className="sr-only">Acções</span></th>
          </tr>
        </thead>
        <tbody>
          {dashboard.payouts.map((payout) => {
            const canConfirm = payout.status === 'available'
              && (dashboard.role.is_organizer || Number(payout.beneficiary_user_id) === Number(dashboard.role.user_id));

            return (
              <tr key={payout.id}>
                <td>T{payout.turn_number} · C{payout.cycle_number}</td>
                <td>{payout.beneficiary_name}</td>
                <td>{money(payout.amount, dashboard.group.currency)}</td>
                <td><code>{payout.receipt_code}</code></td>
                <td>{payout.status === 'received' ? <Badge tone="success">Recebido</Badge> : <Badge tone="warning">Disponível</Badge>}</td>
                <td>
                  <div className="actions">
                    {canConfirm ? (
                      <Button
                        icon={Check}
                        isLoading={loadingId === Number(payout.id)}
                        onClick={() => onConfirmPayout(payout.id)}
                        size="sm"
                        variant="primary"
                      >
                        Recebido
                      </Button>
                    ) : null}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

function Activities({ activities }) {
  if (!activities.length) {
    return <InlineAlert>Sem eventos registados.</InlineAlert>;
  }

  return (
    <ol className="timeline">
      {activities.map((activity) => (
        <li className="timeline-item" key={activity.id}>
          <Clock3 aria-hidden="true" />
          <div>
            <strong>{activityLabel(activity.action)}</strong>
            <span>{activity.actor_name || 'Sistema'} · {readableDate(activity.created_at)}</span>
          </div>
        </li>
      ))}
    </ol>
  );
}
