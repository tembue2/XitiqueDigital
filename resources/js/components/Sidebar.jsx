import { LogOut, Plus, UsersRound } from 'lucide-react';
import { statusLabel } from '../lib/format.js';
import { Button } from './ui/Button.jsx';
import { Field, SelectInput, TextInput } from './ui/Field.jsx';

export function Sidebar({
  creatingGroup,
  groups,
  onCreateGroup,
  onLogout,
  onSelectGroup,
  selectedGroupId,
  user,
}) {
  return (
    <aside className="sidebar" aria-label="Grupos e conta">
      <div className="brand-row">
        <div>
          <p className="eyebrow">Xitique</p>
          <h1>Xitique Digital</h1>
        </div>
        <Button icon={LogOut} onClick={onLogout} size="sm" variant="ghost">
          Sair
        </Button>
      </div>

      <div className="account">
        <strong>{user.name}</strong>
        <span>{user.phone}</span>
      </div>

      <nav aria-label="Grupos">
        <div className="group-list">
          {groups.length ? groups.map((group) => (
            <button
              aria-current={Number(group.id) === Number(selectedGroupId) ? 'page' : undefined}
              className={Number(group.id) === Number(selectedGroupId) ? 'group-tab active' : 'group-tab'}
              data-group-id={group.id}
              key={group.id}
              onClick={() => onSelectGroup(group.id)}
              type="button"
            >
              <span className="group-tab-title">
                <UsersRound aria-hidden="true" />
                <strong>{group.name}</strong>
              </span>
              <span>{group.member_count} membros · {group.cycle_number ? `Turno ${group.turn_number}, ciclo ${group.cycle_number}` : statusLabel(group.status)}</span>
            </button>
          )) : (
            <p className="muted muted-inverse">Ainda não participa em nenhum grupo.</p>
          )}
        </div>
      </nav>

      <form className="sidebar-form" onSubmit={onCreateGroup}>
        <strong>Novo grupo</strong>
        <Field id="group-name" label="Nome" required>
          {({ describedBy }) => (
            <TextInput describedBy={describedBy} id="group-name" name="name" required />
          )}
        </Field>
        <Field id="group-amount" label="Valor por membro" required>
          {({ describedBy }) => (
            <TextInput
              defaultValue="1000"
              describedBy={describedBy}
              id="group-amount"
              min="1"
              name="contribution_amount"
              required
              step="0.01"
              type="number"
            />
          )}
        </Field>
        <Field id="group-frequency" label="Frequência" required>
          {({ describedBy }) => (
            <SelectInput describedBy={describedBy} id="group-frequency" name="frequency" required>
              <option value="monthly">Mensal</option>
              <option value="weekly">Semanal</option>
            </SelectInput>
          )}
        </Field>
        <Button icon={Plus} isLoading={creatingGroup} type="submit" variant="primary">
          Criar
        </Button>
      </form>
    </aside>
  );
}
