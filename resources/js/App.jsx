import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError, apiRequest, readForm } from './lib/api.js';
import { AuthScreen } from './components/AuthScreen.jsx';
import { Sidebar } from './components/Sidebar.jsx';
import { Dashboard } from './components/Dashboard.jsx';
import { EmptyState, Spinner, Toast } from './components/ui/Feedback.jsx';

export function App() {
  const [actionLoading, setActionLoading] = useState(null);
  const [authMode, setAuthMode] = useState('login');
  const [booting, setBooting] = useState(true);
  const [dashboard, setDashboard] = useState(null);
  const [dashboardLoading, setDashboardLoading] = useState(false);
  const [groups, setGroups] = useState([]);
  const [notice, setNotice] = useState(null);
  const [selectedGroupId, setSelectedGroupId] = useState(null);
  const [user, setUser] = useState(null);

  const showNotice = useCallback((message, type = 'success') => {
    setNotice({ message, type });
    window.clearTimeout(showNotice.timeoutId);
    showNotice.timeoutId = window.setTimeout(() => setNotice(null), 4500);
  }, []);

  const showError = useCallback((error) => {
    if (error instanceof ApiError && error.status === 419) {
      showNotice('Sessão expirada. Recarregue a página e tente novamente.', 'error');
      return;
    }

    showNotice(error.message || 'Não foi possível concluir a operação.', 'error');
  }, [showNotice]);

  const refreshGroups = useCallback(async () => {
    const payload = await apiRequest('api/groups');
    setGroups(payload.groups || []);
    return payload.groups || [];
  }, []);

  const loadDashboard = useCallback(async (groupId, { quiet = false } = {}) => {
    if (!groupId) {
      setDashboard(null);
      return null;
    }

    setDashboardLoading(true);

    try {
      const payload = await apiRequest(`api/groups/${groupId}`);
      setSelectedGroupId(Number(groupId));
      setDashboard(payload.dashboard);
      return payload.dashboard;
    } finally {
      if (!quiet) {
        setDashboardLoading(false);
      } else {
        setDashboardLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    let ignore = false;

    async function boot() {
      try {
        const payload = await apiRequest('api/me');

        if (ignore) {
          return;
        }

        setUser(payload.user);
        setGroups(payload.groups || []);

        if (payload.user && payload.groups?.length) {
          const firstGroupId = payload.groups[0].id;
          setSelectedGroupId(Number(firstGroupId));
          await loadDashboard(firstGroupId, { quiet: true });
        }
      } catch (error) {
        if (!ignore) {
          showError(error);
        }
      } finally {
        if (!ignore) {
          setBooting(false);
        }
      }
    }

    boot();

    return () => {
      ignore = true;
    };
  }, [loadDashboard, showError]);

  async function handleLogin(event) {
    event.preventDefault();
    setActionLoading('login');

    try {
      const payload = await apiRequest('api/auth/login', {
        method: 'POST',
        body: readForm(event.currentTarget),
      });
      setUser(payload.user);
      const nextGroups = await refreshGroups();
      if (nextGroups[0]) {
        await loadDashboard(nextGroups[0].id, { quiet: true });
      } else {
        setDashboard(null);
        setSelectedGroupId(null);
      }
      showNotice('Sessão iniciada.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleRegister(event) {
    event.preventDefault();
    setActionLoading('register');

    try {
      const payload = await apiRequest('api/auth/register', {
        method: 'POST',
        body: readForm(event.currentTarget),
      });
      setUser(payload.user);
      setGroups([]);
      setDashboard(null);
      setSelectedGroupId(null);
      showNotice('Conta criada.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleLogout() {
    setActionLoading('logout');

    try {
      await apiRequest('api/auth/logout', { method: 'POST', body: {} });
      setUser(null);
      setGroups([]);
      setDashboard(null);
      setSelectedGroupId(null);
      setAuthMode('login');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleCreateGroup(event) {
    event.preventDefault();
    setActionLoading('create-group');

    try {
      const payload = await apiRequest('api/groups', {
        method: 'POST',
        body: readForm(event.currentTarget),
      });
      setDashboard(payload.dashboard);
      setSelectedGroupId(Number(payload.dashboard.group.id));
      await refreshGroups();
      event.currentTarget.reset();
      showNotice('Grupo criado.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleSelectGroup(groupId) {
    try {
      await loadDashboard(groupId);
    } catch (error) {
      showError(error);
    }
  }

  async function handleStartTurn() {
    setActionLoading('start-turn');

    try {
      await apiRequest(`api/groups/${selectedGroupId}/start`, { method: 'POST', body: {} });
      await loadDashboard(selectedGroupId, { quiet: true });
      await refreshGroups();
      showNotice('Turno iniciado.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleAddMember(event) {
    event.preventDefault();
    setActionLoading('add-member');

    try {
      const payload = await apiRequest(`api/groups/${selectedGroupId}/members`, {
        method: 'POST',
        body: readForm(event.currentTarget),
      });
      await loadDashboard(selectedGroupId, { quiet: true });
      await refreshGroups();
      event.currentTarget.reset();
      const password = payload.temporary_password ? ` Senha inicial: ${payload.temporary_password}` : '';
      showNotice(`Membro adicionado.${password}`);
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleRemoveMember(memberId) {
    setActionLoading(`remove-member:${memberId}`);

    try {
      await apiRequest(`api/groups/${selectedGroupId}/members/${memberId}`, { method: 'DELETE' });
      await loadDashboard(selectedGroupId, { quiet: true });
      await refreshGroups();
      showNotice('Membro removido.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleRegisterPayment(event) {
    event.preventDefault();
    setActionLoading('register-payment');

    try {
      await apiRequest(`api/groups/${selectedGroupId}/payments`, {
        method: 'POST',
        body: readForm(event.currentTarget),
      });
      await loadDashboard(selectedGroupId, { quiet: true });
      await refreshGroups();
      event.currentTarget.reset();
      showNotice('Pagamento confirmado.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  async function handleConfirmPayout(payoutId) {
    setActionLoading(`confirm-payout:${payoutId}`);

    try {
      await apiRequest(`api/payouts/${payoutId}/confirm`, { method: 'POST', body: {} });
      await loadDashboard(selectedGroupId, { quiet: true });
      showNotice('Recebimento confirmado.');
    } catch (error) {
      showError(error);
    } finally {
      setActionLoading(null);
    }
  }

  const selectedGroupExists = useMemo(
    () => groups.some((group) => Number(group.id) === Number(selectedGroupId)),
    [groups, selectedGroupId],
  );

  if (booting) {
    return (
      <>
        <SkipLink />
        <main className="loading-screen" id="conteudo">
          <Spinner label="A carregar o Xitique Digital" />
        </main>
      </>
    );
  }

  return (
    <>
      <SkipLink />
      <Toast notice={notice} />
      {!user ? (
        <AuthScreen
          authMode={authMode}
          onLogin={handleLogin}
          onRegister={handleRegister}
          setAuthMode={setAuthMode}
          submitting={actionLoading}
        />
      ) : (
        <div className="layout">
          <Sidebar
            creatingGroup={actionLoading === 'create-group'}
            groups={groups}
            onCreateGroup={handleCreateGroup}
            onLogout={handleLogout}
            onSelectGroup={handleSelectGroup}
            selectedGroupId={selectedGroupId}
            user={user}
          />
          <main className="content" id="conteudo">
            {!selectedGroupExists && groups.length ? (
              <EmptyState title="Grupo indisponível">
                Seleccione outro grupo na lista lateral para continuar.
              </EmptyState>
            ) : (
              <Dashboard
                actionLoading={actionLoading}
                dashboard={dashboard}
                dashboardLoading={dashboardLoading}
                onAddMember={handleAddMember}
                onConfirmPayout={handleConfirmPayout}
                onRegisterPayment={handleRegisterPayment}
                onRemoveMember={handleRemoveMember}
                onStartTurn={handleStartTurn}
              />
            )}
          </main>
        </div>
      )}
    </>
  );
}

function SkipLink() {
  return (
    <a className="skip-link" href="#conteudo">
      Saltar para o conteúdo principal
    </a>
  );
}

