# Xitique Digital

Plataforma web para gestão transparente de poupança rotativa comunitária. Esta versão é um MVP de produção mínima: autenticação por sessão, API JSON, SQLite, cache em ficheiro, interface React e seed de demonstração.

## Como correr

```powershell
cd C:\xampp\htdocs\XitiqueDigital
php -S 127.0.0.1:8097 -t public public/index.php
```

Abra `http://127.0.0.1:8097`.

Credenciais demo:

- Telefone: `840000001`
- Senha: `password`

Também pode abrir via XAMPP/Apache em `http://localhost/XitiqueDigital/public` se o Apache estiver activo e `mod_rewrite` disponível.

## Stack

- PHP 8.2 puro, sem framework externo.
- SQLite por padrão em `database/database.sqlite`.
- Front-end SPA em React com Vite.
- Componentes reutilizáveis em `resources/js/components`.
- `lucide-react` para ícones, `@radix-ui/react-alert-dialog` para diálogo acessível e `clsx` para composição de classes.
- Cache em ficheiro em `storage/cache`.
- Sessão HTTP com cookie `HttpOnly` e protecção CSRF para operações mutáveis.

## Frontend

Instale dependências e gere os assets de produção:

```powershell
npm install
npm run build
```

O build escreve `public/assets/app.js` e `public/assets/app.css`, usados directamente pelo PHP.

## Funcionalidades do MVP

- Criar conta e iniciar sessão.
- Criar grupo de xitique.
- Adicionar/remover membros antes de iniciar ou entre turnos.
- Iniciar turno.
- Registar pagamentos confirmados pelo organizador.
- Fechar ciclo automaticamente quando todos pagam.
- Gerar comprovativo de recebimento.
- Abrir automaticamente o próximo ciclo.
- Confirmar recebimento pelo beneficiário ou organizador.
- Ver histórico completo de eventos do grupo.

## Estrutura

```text
app/
  Controllers/     Endpoints HTTP da API
  Core/            Router, auth, CSRF, DB, cache, config
  Services/        Regras de domínio do xitique
database/
  schema.sql       Esquema relacional
docs/
  ARCHITECTURE.md  Decisões técnicas e desenho do sistema
public/
  index.php        Front controller e HTML inicial
  assets/          Bundle React compilado e imagem local
resources/
  js/              App React, componentes e cliente de API
  styles/          CSS-fonte do design system
storage/
  cache/           Cache materializada por grupo
```

## API rápida

Todas as chamadas `POST` e `DELETE` usam o header `X-CSRF-Token` gerado no HTML inicial.

- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/me`
- `GET /api/groups`
- `POST /api/groups`
- `GET /api/groups/{id}`
- `POST /api/groups/{id}/members`
- `DELETE /api/groups/{groupId}/members/{memberId}`
- `POST /api/groups/{id}/start`
- `POST /api/groups/{groupId}/payments`
- `POST /api/payouts/{id}/confirm`

Mais detalhes em [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
