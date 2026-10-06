# Landing page — Propriedade 260.000 m²

Landing page institucional para apresentação de uma propriedade/terreno
(~260.000 m²) e captação de interessados, com painel administrativo.

**Stack:** HTML5 + CSS + JavaScript puro · PHP 8 · MySQL — sem frameworks,
sem build, sem Node em produção. Feita para hospedagem compartilhada (Hostinger).

➡️ **Instalação passo a passo: [INSTALL.md](INSTALL.md)**

## Funcionalidades

**Site público** (`/`)
- Hero com imagem grande, headline, subheadline, área e CTA com rolagem suave
- Seção “A propriedade” e bloco “Detalhes” (localização, município, estado, acesso,
  infraestrutura, vocação, informações urbanísticas, documentação, características,
  diferenciais, informações complementares) — **campos vazios não aparecem**
- “Conheça a área”: topográfico em destaque com lightbox em tela cheia,
  zoom (roda do mouse, botões, pinça e duplo toque) e link para a versão em alta resolução
- Galeria com lightbox (teclado, setas e gesto de deslizar)
- CTA intermediário e formulário de interesse com validação (front e back),
  máscara de WhatsApp (Brasil e internacional), anti-duplicidade, anti-robô
  (honeypot + token assinado + limite por IP), registro de UTMs, página de entrada
  e referência; funciona mesmo sem JavaScript
- SEO: title, description, canonical, Open Graph, favicon configurável, HTML semântico
- Política de Privacidade (`/privacidade.php`) editável e aviso LGPD no formulário
- Engrenagem discreta ⚙ (canto inferior direito) que leva ao `/admin`

**Painel** (`/admin`) — login server-side, sessões seguras, CSRF, limite de tentativas
- Dashboard: total, hoje, 7 e 30 dias, por status, últimos leads
- Leads: tabela completa, busca (nome, e-mail, WhatsApp, empresa), filtro por status,
  paginação, detalhes, status, observações internas, botão **Chamar no WhatsApp**,
  exclusão (LGPD) e exportação CSV
- Propriedade: todos os textos do site
- Imagens: principal, topográfico e galeria (upload múltiplo, substituição,
  exclusão, ordenação por arrastar ou setas, legendas, categorias, texto alternativo)
- Configurações: SEO, marca, privacidade, rodapé, imagem OG, favicon, e-mail e senha do admin

## Estrutura

```
database.sql                 Estrutura MySQL + conteúdo inicial (só a área confirmada)
public_html/                 → conteúdo a enviar para o public_html da Hostinger
  index.php                  Landing page (renderizada a partir do banco)
  privacidade.php            Política de Privacidade
  api/lead.php               Endpoint do formulário
  admin/                     Painel (dashboard, leads, lead, export, property, images, settings, login, logout)
  install/                   Assistente do 1º administrador (excluir após uso)
  app/bootstrap.php          Inicialização
  app/admin.php              Inicialização do painel + layout
  app/lib/                   db, auth, csrf, session, settings, property, leads, images, helpers
  app/views/admin/           Cabeçalho/rodapé do painel
  app/cli/admin-user.php     Criar admin / redefinir senha via SSH
  config/config.sample.php   Modelo de configuração (config.php fica fora do Git)
  assets/css, assets/js      Estilos e scripts (site e painel)
  assets/img                 Placeholders neutros e favicon padrão
  uploads/                   Imagens enviadas (.htaccess impede execução de scripts)
tests/e2e_test.py            Teste de ponta a ponta (ambiente local)
```

## Segurança (resumo)

- Prepared statements (PDO, sem emulação) em todas as consultas
- Escape de toda saída HTML (`e()`), sanitização e validação no backend
- Senha com `password_hash()`; nenhuma credencial em código, HTML, JS ou Git
- Sessão com cookie `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS, regenerada no login,
  expiração por inatividade e vínculo ao navegador
- CSRF em todos os formulários do painel; logout via POST
- Bloqueio temporário após tentativas de login excessivas (por e-mail e por IP)
- Uploads: extensão + MIME real + `getimagesize` + limite de dimensões + nome aleatório
  + recodificação GD; `uploads/.htaccess` libera só imagens e desativa scripts
- `app/` e `config/` bloqueados por `.htaccess`; `/admin` com `noindex` (meta + cabeçalho) e fora do `robots.txt`
- Cabeçalhos: CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`
- CSV com proteção contra injeção de fórmulas

## Desenvolvimento local

```bash
mysql -e "CREATE DATABASE prop CHARACTER SET utf8mb4"
mysql prop < database.sql
cp public_html/config/config.sample.php public_html/config/config.php   # edite os dados
php -S 127.0.0.1:8080 -t public_html
# testes de ponta a ponta (requer python3 + requests):
php tests/make_test_images.php tests/output/imgs
BASE_URL=http://127.0.0.1:8080 SETUP_TOKEN=... IMG_DIR=tests/output/imgs \
MYSQL="mysql -u usuario -psenha prop" python3 tests/e2e_test.py
```
