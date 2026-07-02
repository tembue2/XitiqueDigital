(function () {
    const root = document.getElementById('app');
    const scriptBase = new URL('../', document.currentScript.src);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    const state = {
        user: null,
        groups: [],
        selectedGroupId: null,
        dashboard: null,
        notice: null,
    };

    const money = (value, currency = 'MZN') => {
        try {
            return new Intl.NumberFormat('pt-MZ', { style: 'currency', currency }).format(Number(value || 0));
        } catch (error) {
            return `${Number(value || 0).toFixed(2)} ${currency}`;
        }
    };

    const escape = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const apiUrl = (path) => new URL(path.replace(/^\//, ''), scriptBase).toString();

    async function api(path, options = {}) {
        const response = await fetch(apiUrl(path), {
            method: options.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || 'Não foi possível concluir a operação.');
        }

        return payload;
    }

    function setNotice(message, type = 'success') {
        state.notice = { message, type };
        render();
        setTimeout(() => {
            if (state.notice && state.notice.message === message) {
                state.notice = null;
                render();
            }
        }, 4200);
    }

    async function bootstrap() {
        try {
            const payload = await api('api/me');
            state.user = payload.user;
            state.groups = payload.groups || [];

            if (state.user && state.groups.length > 0) {
                state.selectedGroupId = state.groups[0].id;
                await loadDashboard(state.selectedGroupId, false);
            }
        } catch (error) {
            state.user = null;
            state.groups = [];
        }

        render();
    }

    async function refreshGroups() {
        const payload = await api('api/groups');
        state.groups = payload.groups || [];

        if (!state.selectedGroupId && state.groups[0]) {
            state.selectedGroupId = state.groups[0].id;
        }
    }

    async function loadDashboard(groupId, shouldRender = true) {
        const payload = await api(`api/groups/${groupId}`);
        state.selectedGroupId = groupId;
        state.dashboard = payload.dashboard;

        if (shouldRender) {
            render();
        }
    }

    function render() {
        if (!state.user) {
            root.innerHTML = noticeHtml() + authHtml();
            return;
        }

        root.innerHTML = noticeHtml() + layoutHtml();
    }

    function noticeHtml() {
        if (!state.notice) {
            return '';
        }

        return `<div class="toast ${state.notice.type === 'error' ? 'error' : ''}">${escape(state.notice.message)}</div>`;
    }

    function authHtml() {
        return `
            <main class="auth-screen">
                <section class="auth-panel">
                    <div class="auth-brand">
                        <div>
                            <h1>Xitique Digital</h1>
                            <p>Transparência para grupos de poupança rotativa, com histórico completo de pagamentos e recebimentos.</p>
                        </div>
                        <p class="demo-line">Demo: 840000001 / password</p>
                    </div>
                    <div class="auth-forms">
                        <form class="form-block" data-action="login">
                            <h2>Entrar</h2>
                            <div class="form-grid">
                                <label>Telefone
                                    <input name="phone" value="840000001" autocomplete="username" required>
                                </label>
                                <label>Senha
                                    <input name="password" type="password" value="password" autocomplete="current-password" required>
                                </label>
                                <button class="btn primary" type="submit">Entrar</button>
                            </div>
                        </form>
                        <form class="form-block" data-action="register">
                            <h2>Criar conta</h2>
                            <div class="form-grid">
                                <label>Nome
                                    <input name="name" autocomplete="name" required>
                                </label>
                                <label>Telefone
                                    <input name="phone" autocomplete="tel" required>
                                </label>
                                <label>Senha
                                    <input name="password" type="password" minlength="6" autocomplete="new-password" required>
                                </label>
                                <button class="btn ghost" type="submit">Registar</button>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        `;
    }

    function layoutHtml() {
        return `
            <div class="layout">
                <aside class="sidebar">
                    <div class="brand-row">
                        <h1>Xitique Digital</h1>
                        <button class="btn ghost small" data-action="logout" type="button">Sair</button>
                    </div>
                    <div class="account">
                        <strong>${escape(state.user.name)}</strong>
                        <span>${escape(state.user.phone)}</span>
                    </div>
                    <div class="group-list">
                        ${state.groups.map(groupTabHtml).join('') || '<p class="muted">Sem grupos.</p>'}
                    </div>
                    <form class="sidebar-form" data-action="create-group">
                        <strong>Novo grupo</strong>
                        <label>Nome
                            <input name="name" required>
                        </label>
                        <label>Valor por membro
                            <input name="contribution_amount" type="number" min="1" step="0.01" value="1000" required>
                        </label>
                        <label>Frequência
                            <select name="frequency">
                                <option value="monthly">Mensal</option>
                                <option value="weekly">Semanal</option>
                            </select>
                        </label>
                        <button class="btn primary" type="submit">Criar</button>
                    </form>
                </aside>
                <main class="content">
                    ${state.dashboard ? dashboardHtml(state.dashboard) : emptyHtml()}
                </main>
            </div>
        `;
    }

    function groupTabHtml(group) {
        const active = Number(group.id) === Number(state.selectedGroupId) ? 'active' : '';
        const cycle = group.cycle_number ? `Turno ${group.turn_number}, ciclo ${group.cycle_number}` : statusLabel(group.status);

        return `
            <button class="group-tab ${active}" data-action="select-group" data-group-id="${group.id}" type="button">
                <strong>${escape(group.name)}</strong>
                <span>${escape(group.member_count)} membros · ${escape(cycle)}</span>
            </button>
        `;
    }

    function emptyHtml() {
        return `
            <section class="empty-state">
                <h2 class="section-title">Crie ou seleccione um grupo</h2>
            </section>
        `;
    }

    function dashboardHtml(dashboard) {
        const group = dashboard.group;
        const cycle = dashboard.current_cycle;
        const role = dashboard.role;
        const capabilities = dashboard.capabilities;
        const summary = dashboard.summary;

        return `
            <header class="topbar">
                <div>
                    <h2>${escape(group.name)}</h2>
                    <span class="status-pill">${statusLabel(group.status)}</span>
                    ${role.is_organizer ? '<span class="role-pill">Organizador</span>' : ''}
                    ${role.is_current_beneficiary ? '<span class="role-pill">Beneficiário actual</span>' : ''}
                </div>
                <div class="actions">
                    ${capabilities.can_start_turn ? `<button class="btn gold" data-action="start-turn" type="button">${group.status === 'turn_completed' ? 'Novo turno' : 'Iniciar'}</button>` : ''}
                </div>
            </header>
            <section class="stat-grid">
                <div class="stat">
                    <span>Contribuição</span>
                    <strong>${money(group.contribution_amount, group.currency)}</strong>
                </div>
                <div class="stat">
                    <span>Beneficiário</span>
                    <strong>${cycle ? escape(cycle.beneficiary_name) : '-'}</strong>
                </div>
                <div class="stat">
                    <span>Pago no ciclo</span>
                    <strong>${money(summary.paid_total, group.currency)}</strong>
                </div>
                <div class="stat">
                    <span>Progresso</span>
                    <strong>${summary.progress_percent}%</strong>
                    <div class="progress"><div style="width:${summary.progress_percent}%"></div></div>
                </div>
            </section>
            <section class="grid-two">
                <div class="panel">
                    <div class="panel-head">
                        <h3 class="panel-title">Membros</h3>
                        <span class="muted">${summary.paid_count}/${summary.member_count} pagos</span>
                    </div>
                    ${membersHtml(dashboard)}
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <h3 class="panel-title">Registar pagamento</h3>
                    </div>
                    ${capabilities.can_register_payments && cycle ? paymentFormHtml(dashboard) : '<p class="muted">Sem ciclo aberto para pagamentos.</p>'}
                </div>
            </section>
            <section class="grid-two">
                <div class="panel">
                    <div class="panel-head">
                        <h3 class="panel-title">Comprovativos</h3>
                    </div>
                    ${payoutsHtml(dashboard)}
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <h3 class="panel-title">Adicionar membro</h3>
                    </div>
                    ${capabilities.can_edit_members ? memberFormHtml() : '<p class="muted">Edição disponível antes do turno ou entre turnos.</p>'}
                </div>
            </section>
            <section class="panel">
                <div class="panel-head">
                    <h3 class="panel-title">Histórico</h3>
                </div>
                ${activitiesHtml(dashboard.activities)}
            </section>
        `;
    }

    function membersHtml(dashboard) {
        const canEdit = dashboard.capabilities.can_edit_members;
        const organizerId = Number(dashboard.group.organizer_user_id);

        return `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Posição</th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Estado</th>
                            <th>Pagamento</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${dashboard.members.map(member => `
                            <tr>
                                <td>${member.position}</td>
                                <td>${escape(member.name)}</td>
                                <td>${escape(member.phone)}</td>
                                <td>${member.status === 'active' ? 'Activo' : 'Removido'}</td>
                                <td>${member.payment_id ? `<span class="paid-pill">Pago</span>` : `<span class="waiting-pill">Pendente</span>`}</td>
                                <td class="actions">
                                    ${dashboard.capabilities.can_register_payments && !member.payment_id && member.status === 'active'
                                        ? `<button class="btn small ghost" data-action="fill-payment" data-member-id="${member.id}" type="button">Confirmar</button>`
                                        : ''}
                                    ${canEdit && Number(member.user_id) !== organizerId && member.status === 'active'
                                        ? `<button class="btn small danger" data-action="remove-member" data-member-id="${member.id}" type="button">Remover</button>`
                                        : ''}
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function paymentFormHtml(dashboard) {
        const unpaidMembers = dashboard.members.filter(member => member.status === 'active' && !member.payment_id);

        if (unpaidMembers.length === 0) {
            return '<p class="muted">Todos os membros já pagaram este ciclo.</p>';
        }

        return `
            <form class="inline-form" data-action="register-payment">
                <label class="full">Membro
                    <select name="member_id" required>
                        ${unpaidMembers.map(member => `<option value="${member.id}">${member.position}. ${escape(member.name)}</option>`).join('')}
                    </select>
                </label>
                <label>Valor
                    <input name="amount" type="number" min="1" step="0.01" value="${escape(dashboard.current_cycle.contribution_amount_snapshot)}" required>
                </label>
                <label>Método
                    <select name="method">
                        <option value="cash">Dinheiro</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="emola">e-Mola</option>
                        <option value="bank_transfer">Transferência</option>
                        <option value="other">Outro</option>
                    </select>
                </label>
                <label class="full">Referência
                    <input name="reference">
                </label>
                <button class="btn primary full" type="submit">Confirmar pagamento</button>
            </form>
        `;
    }

    function memberFormHtml() {
        return `
            <form class="inline-form" data-action="add-member">
                <label>Nome
                    <input name="name" required>
                </label>
                <label>Telefone
                    <input name="phone" required>
                </label>
                <button class="btn primary full" type="submit">Adicionar</button>
            </form>
        `;
    }

    function payoutsHtml(dashboard) {
        if (!dashboard.payouts.length) {
            return '<p class="muted">Ainda não há comprovativos.</p>';
        }

        return `
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Ciclo</th>
                            <th>Beneficiário</th>
                            <th>Valor</th>
                            <th>Código</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${dashboard.payouts.map(payout => {
                            const canConfirm = payout.status === 'available'
                                && (dashboard.role.is_organizer || Number(payout.beneficiary_user_id) === Number(state.user.id));

                            return `
                                <tr>
                                    <td>T${payout.turn_number} · C${payout.cycle_number}</td>
                                    <td>${escape(payout.beneficiary_name)}</td>
                                    <td>${money(payout.amount, dashboard.group.currency)}</td>
                                    <td>${escape(payout.receipt_code)}</td>
                                    <td>${payout.status === 'received' ? '<span class="paid-pill">Recebido</span>' : '<span class="waiting-pill">Disponível</span>'}</td>
                                    <td class="actions">
                                        ${canConfirm ? `<button class="btn small primary" data-action="confirm-payout" data-payout-id="${payout.id}" type="button">Recebido</button>` : ''}
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    function activitiesHtml(activities) {
        if (!activities.length) {
            return '<p class="muted">Sem eventos registados.</p>';
        }

        return `
            <div class="timeline">
                ${activities.map(activity => `
                    <div class="timeline-item">
                        <strong>${activityLabel(activity.action)}</strong>
                        <span>${escape(activity.actor_name || 'Sistema')} · ${escape(activity.created_at)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function statusLabel(status) {
        return {
            draft: 'Rascunho',
            active: 'Activo',
            turn_completed: 'Turno concluído',
            dissolved: 'Dissolvido',
        }[status] || status;
    }

    function activityLabel(action) {
        return {
            demo_created: 'Grupo de demonstração',
            group_created: 'Grupo criado',
            member_added: 'Membro adicionado',
            member_removed: 'Membro removido',
            turn_started: 'Turno iniciado',
            payment_confirmed: 'Pagamento confirmado',
            payment_updated: 'Pagamento actualizado',
            cycle_completed: 'Ciclo concluído',
            cycle_opened: 'Novo ciclo aberto',
            turn_completed: 'Turno concluído',
            payout_received: 'Recebimento confirmado',
        }[action] || action;
    }

    function formData(form) {
        return Object.fromEntries(new FormData(form).entries());
    }

    root.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-action]');

        if (!form) {
            return;
        }

        event.preventDefault();

        try {
            const action = form.dataset.action;
            const data = formData(form);

            if (action === 'login') {
                const payload = await api('api/auth/login', { method: 'POST', body: data });
                state.user = payload.user;
                await refreshGroups();
                if (state.groups[0]) {
                    await loadDashboard(state.groups[0].id, false);
                }
                setNotice('Sessão iniciada.');
            }

            if (action === 'register') {
                const payload = await api('api/auth/register', { method: 'POST', body: data });
                state.user = payload.user;
                state.groups = [];
                state.dashboard = null;
                setNotice('Conta criada.');
            }

            if (action === 'create-group') {
                const payload = await api('api/groups', { method: 'POST', body: data });
                state.dashboard = payload.dashboard;
                state.selectedGroupId = payload.dashboard.group.id;
                await refreshGroups();
                form.reset();
                setNotice('Grupo criado.');
            }

            if (action === 'add-member') {
                const payload = await api(`api/groups/${state.selectedGroupId}/members`, { method: 'POST', body: data });
                await loadDashboard(state.selectedGroupId, false);
                await refreshGroups();
                form.reset();
                const password = payload.temporary_password ? ` Senha inicial: ${payload.temporary_password}` : '';
                setNotice(`Membro adicionado.${password}`);
            }

            if (action === 'register-payment') {
                await api(`api/groups/${state.selectedGroupId}/payments`, { method: 'POST', body: data });
                await loadDashboard(state.selectedGroupId, false);
                await refreshGroups();
                setNotice('Pagamento confirmado.');
            }

            render();
        } catch (error) {
            setNotice(error.message, 'error');
        }
    });

    root.addEventListener('click', async (event) => {
        const target = event.target.closest('[data-action]');

        if (!target) {
            return;
        }

        const action = target.dataset.action;

        try {
            if (action === 'logout') {
                await api('api/auth/logout', { method: 'POST', body: {} });
                state.user = null;
                state.groups = [];
                state.dashboard = null;
                state.selectedGroupId = null;
                render();
                return;
            }

            if (action === 'select-group') {
                await loadDashboard(Number(target.dataset.groupId));
                return;
            }

            if (action === 'start-turn') {
                await api(`api/groups/${state.selectedGroupId}/start`, { method: 'POST', body: {} });
                await loadDashboard(state.selectedGroupId, false);
                await refreshGroups();
                setNotice('Turno iniciado.');
                render();
                return;
            }

            if (action === 'fill-payment') {
                const select = root.querySelector('form[data-action="register-payment"] select[name="member_id"]');
                if (select) {
                    select.value = target.dataset.memberId;
                    select.focus();
                }
                return;
            }

            if (action === 'remove-member') {
                if (!window.confirm('Remover este membro do grupo?')) {
                    return;
                }
                await api(`api/groups/${state.selectedGroupId}/members/${target.dataset.memberId}`, { method: 'DELETE' });
                await loadDashboard(state.selectedGroupId, false);
                await refreshGroups();
                setNotice('Membro removido.');
                render();
                return;
            }

            if (action === 'confirm-payout') {
                await api(`api/payouts/${target.dataset.payoutId}/confirm`, { method: 'POST', body: {} });
                await loadDashboard(state.selectedGroupId, false);
                setNotice('Recebimento confirmado.');
                render();
            }
        } catch (error) {
            setNotice(error.message, 'error');
        }
    });

    bootstrap();
}());

