import { LogIn, UserPlus } from 'lucide-react';
import { assetUrl } from '../lib/config.js';
import { Button } from './ui/Button.jsx';
import { Field, TextInput } from './ui/Field.jsx';

export function AuthScreen({ authMode, onLogin, onRegister, setAuthMode, submitting }) {
  const isLogin = authMode === 'login';

  return (
    <main className="auth-screen" id="conteudo">
      <section className="auth-panel" aria-labelledby="auth-title">
        <div
          className="auth-brand"
          style={{
            backgroundImage: `linear-gradient(rgba(23, 107, 77, 0.88), rgba(28, 36, 48, 0.82)), url("${assetUrl('assets/community-savings.png')}")`,
          }}
        >
          <div>
            <p className="eyebrow">Poupança rotativa comunitária</p>
            <h1 id="auth-title">Xitique Digital</h1>
            <p>
              Transparência para grupos de poupança, com histórico completo de
              pagamentos, recebimentos e ordem de beneficiários.
            </p>
          </div>
          <p className="demo-line">Demo: 840000001 / password</p>
        </div>

        <div className="auth-forms">
          <div className="segmented" role="tablist" aria-label="Autenticação">
            <button
              aria-selected={isLogin}
              className={isLogin ? 'active' : ''}
              onClick={() => setAuthMode('login')}
              role="tab"
              type="button"
            >
              Entrar
            </button>
            <button
              aria-selected={!isLogin}
              className={!isLogin ? 'active' : ''}
              onClick={() => setAuthMode('register')}
              role="tab"
              type="button"
            >
              Criar conta
            </button>
          </div>

          {isLogin ? (
            <form className="form-block" onSubmit={onLogin}>
              <h2>Entrar</h2>
              <Field id="login-phone" label="Telefone" required>
                {({ describedBy }) => (
                  <TextInput
                    autoComplete="username"
                    defaultValue="840000001"
                    describedBy={describedBy}
                    id="login-phone"
                    name="phone"
                    required
                  />
                )}
              </Field>
              <Field id="login-password" label="Senha" required>
                {({ describedBy }) => (
                  <TextInput
                    autoComplete="current-password"
                    defaultValue="password"
                    describedBy={describedBy}
                    id="login-password"
                    name="password"
                    required
                    type="password"
                  />
                )}
              </Field>
              <Button icon={LogIn} isLoading={submitting === 'login'} type="submit" variant="primary">
                Entrar
              </Button>
            </form>
          ) : (
            <form className="form-block" onSubmit={onRegister}>
              <h2>Criar conta</h2>
              <Field id="register-name" label="Nome" required>
                {({ describedBy }) => (
                  <TextInput
                    autoComplete="name"
                    describedBy={describedBy}
                    id="register-name"
                    name="name"
                    required
                  />
                )}
              </Field>
              <Field id="register-phone" label="Telefone" required>
                {({ describedBy }) => (
                  <TextInput
                    autoComplete="tel"
                    describedBy={describedBy}
                    id="register-phone"
                    name="phone"
                    required
                  />
                )}
              </Field>
              <Field id="register-password" hint="Use pelo menos 6 caracteres." label="Senha" required>
                {({ describedBy }) => (
                  <TextInput
                    autoComplete="new-password"
                    describedBy={describedBy}
                    id="register-password"
                    minLength={6}
                    name="password"
                    required
                    type="password"
                  />
                )}
              </Field>
              <Button icon={UserPlus} isLoading={submitting === 'register'} type="submit" variant="primary">
                Criar conta
              </Button>
            </form>
          )}
        </div>
      </section>
    </main>
  );
}
