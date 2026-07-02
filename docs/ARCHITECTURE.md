# Arquitectura do Xitique Digital

## Visão Geral

O MVP foi desenhado como uma aplicação modular em PHP puro, pronta para correr em XAMPP, mas com fronteiras claras para evoluir para Laravel, Symfony ou uma API dedicada no futuro. A aplicação não movimenta dinheiro: regista compromissos, confirmações, ordem de beneficiários e comprovativos.

Arquitectura lógica:

```text
Browser SPA
   |
   | JSON + sessão + CSRF
   v
public/index.php
   |
   v
Controllers
   |
   v
XitiqueService
   |
   +--> Cache por grupo
   |
   v
SQLite / futuro PostgreSQL
```

## Componentes

`public/index.php`
: Front controller. Serve o HTML inicial, aplica headers de segurança, normaliza URLs para XAMPP e servidor embutido, e encaminha `/api/*`.

`app/Core/Router.php`
: Router mínimo com rotas parametrizadas como `/api/groups/{id}`.

`app/Core/Auth.php`
: Sessão do utilizador, login/logout e `requireUserId()`.

`app/Core/Csrf.php`
: Token por sessão. Todas as mutações exigem `X-CSRF-Token`.

`app/Core/Database.php`
: Singleton PDO SQLite com `PRAGMA foreign_keys = ON` e transacções.

`app/Core/Migrator.php`
: Auto-migração opcional a partir de `database/schema.sql`.

`app/Core/Cache.php`
: Cache JSON em ficheiro com TTL e invalidação por prefixo.

`app/Controllers/*`
: Camada HTTP fina. Valida sessão, lê request e devolve JSON.

`app/Services/XitiqueService.php`
: Camada de domínio. Contém permissões, criação de grupos, gestão de membros, pagamentos, fecho de ciclo, criação de payout e abertura automática do próximo ciclo.

## Fluxo de Dados

### Criar Grupo

1. Organizador envia `POST /api/groups`.
2. Serviço cria `groups` em estado `draft`.
3. Organizador é inserido em `group_members` na posição 1.
4. Evento `group_created` é gravado em `activity_logs`.
5. Dashboard do grupo é devolvido.

### Iniciar Turno

1. Organizador envia `POST /api/groups/{id}/start`.
2. Serviço exige pelo menos 2 membros activos.
3. Primeiro membro activo por posição vira beneficiário do ciclo 1.
4. Grupo muda para `active`.
5. `current_cycle_id` aponta para o ciclo aberto.

### Confirmar Pagamento

1. Organizador envia `POST /api/groups/{id}/payments`.
2. Serviço confirma que o grupo está activo e que o membro pertence ao grupo.
3. Pagamento é inserido ou actualizado em `payments`.
4. Cache do dashboard do grupo é invalidado.
5. Serviço conta pagamentos confirmados no ciclo.

### Fechar Ciclo e Avançar

Quando `pagamentos_confirmados == membros_activos`:

1. Ciclo muda para `completed`.
2. É criado um `payout` com `receipt_code`.
3. Se todos os membros já receberam no turno, grupo muda para `turn_completed`.
4. Caso contrário, o próximo membro por posição vira beneficiário.
5. Novo ciclo é criado e `groups.current_cycle_id` é actualizado.

### Confirmar Recebimento

1. Beneficiário ou organizador envia `POST /api/payouts/{id}/confirm`.
2. `payout.status` muda para `received`.
3. `received_at` é preenchido.
4. Evento `payout_received` entra no histórico.

## Projecto de API

Formato padrão de sucesso:

```json
{
  "ok": true
}
```

Formato padrão de erro:

```json
{
  "ok": false,
  "message": "Mensagem legível",
  "details": {}
}
```

### Autenticação

`POST /api/auth/register`

```json
{
  "name": "Dona Amélia",
  "phone": "840000001",
  "password": "password"
}
```

`POST /api/auth/login`

```json
{
  "phone": "840000001",
  "password": "password"
}
```

`GET /api/me`

Devolve utilizador autenticado e grupos acessíveis.

### Grupos

`GET /api/groups`

Lista grupos onde o utilizador é organizador ou membro activo.

`POST /api/groups`

```json
{
  "name": "Xitique da Família",
  "description": "Grupo mensal",
  "contribution_amount": 1000,
  "frequency": "monthly",
  "start_date": "2026-07-02"
}
```

`GET /api/groups/{id}`

Devolve dashboard completo: grupo, papel do utilizador, ciclo actual, membros, pagamentos, comprovativos, histórico e permissões.

`POST /api/groups/{id}/members`

```json
{
  "name": "Mateus Nhantumbo",
  "phone": "840000002"
}
```

`DELETE /api/groups/{groupId}/members/{memberId}`

Remove membro, permitido apenas em `draft` ou `turn_completed`.

`POST /api/groups/{id}/start`

Inicia o primeiro turno ou um novo turno após todos terem recebido.

### Pagamentos

`POST /api/groups/{groupId}/payments`

```json
{
  "member_id": 2,
  "amount": 1000,
  "method": "mpesa",
  "reference": "MPESA-123"
}
```

Métodos aceites: `cash`, `mpesa`, `emola`, `bank_transfer`, `other`.

### Recebimentos

`POST /api/payouts/{id}/confirm`

Marca o comprovativo como recebido. Permitido para o beneficiário do payout ou organizador.

## Esquema de Banco de Dados

Tabelas principais:

`users`
: Utilizadores autenticáveis. Telefone é único.

`groups`
: Grupo de xitique. Guarda valor de contribuição, frequência, organizador, estado e ciclo actual.

`group_members`
: Associação utilizador-grupo, posição na fila e estado.

`cycles`
: Ciclos de um turno. Cada ciclo tem beneficiário, prazo, valor esperado e estado.

`payments`
: Pagamentos confirmados pelo organizador. Tem unicidade por `cycle_id + member_id`.

`payouts`
: Comprovativos de recebimento gerados ao fechar um ciclo.

`activity_logs`
: Auditoria de eventos relevantes para transparência.

Índices principais:

- `groups.organizer_user_id`
- `group_members(group_id, status, position)`
- `cycles(group_id, status, turn_number, cycle_number)`
- `payments(cycle_id, status)`
- `payouts(beneficiary_member_id, status)`
- `activity_logs(group_id, created_at DESC)`

## Estratégia de Cache

MVP:

- Cache em ficheiro para dashboards: `group_{id}_dashboard_user_{userId}`.
- TTL configurável por `CACHE_TTL_SECONDS`.
- Invalidação por prefixo `group_{id}_` em mutações de membros, pagamentos, ciclos e payouts.
- Cache é user-aware porque permissões e papel do utilizador variam por sessão.

Escala:

- Trocar `app/Core/Cache.php` por Redis mantendo a mesma interface.
- Usar tags Redis por grupo: `group:{id}`.
- Cachear dashboards por 30-90 segundos.
- Invalidar em eventos de domínio: `PaymentConfirmed`, `CycleCompleted`, `PayoutReceived`, `MemberChanged`.

## Escalabilidade

Curto prazo:

- SQLite suporta bem demonstração e pequenos grupos.
- Mover para MySQL/PostgreSQL quando houver múltiplos organizadores reais.
- Criar migrations versionadas em vez de `schema.sql` monolítico.

Médio prazo:

- Filas para notificações WhatsApp/SMS.
- Redis para cache e rate limiting.
- Jobs para lembretes de pagamento antes de `due_date`.
- Exportação PDF de relatórios como funcionalidade premium.

Longo prazo:

- Multi-tenant por organização/comunidade.
- Auditoria imutável com append-only ledger para disputas.
- Webhooks de integração M-Pesa apenas para confirmação, mantendo o produto fora da custódia de fundos.

## Segurança

- Senhas com `password_hash`.
- Sessão com cookie `HttpOnly` e `SameSite=Lax`.
- CSRF obrigatório em mutações.
- Permissões por grupo: organizador gere; membro consulta; beneficiário confirma o seu recebimento.
- Não há carteira digital nem custódia de dinheiro.

