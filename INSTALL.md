# INSTALL.md — Como publicar o site na Hostinger

Este guia leva você do zero até o site funcionando, com formulário gravando
no MySQL e painel administrativo protegido. Siga na ordem. Tempo estimado:
30 a 45 minutos.

> **Requisitos:** qualquer plano de hospedagem compartilhada da Hostinger
> (Premium, Business ou Cloud) com **PHP 8.0 ou superior** (recomendado 8.2/8.3)
> e MySQL. Não é necessário VPS, Node.js nem nada rodando permanentemente.

---

## Visão geral dos arquivos

```
macaibagrandenatal/
├── INSTALL.md              ← este guia (NÃO enviar ao servidor)
├── README.md               ← visão geral do projeto (NÃO enviar)
├── database.sql            ← estrutura do banco (importar no phpMyAdmin, NÃO enviar)
├── tests/                  ← testes automáticos de desenvolvimento (NÃO enviar)
└── public_html/            ← ★ TUDO o que está DENTRO desta pasta vai para o servidor
    ├── index.php           ← landing page
    ├── privacidade.php     ← Política de Privacidade
    ├── robots.txt
    ├── .htaccess           ← segurança, cache e (opcional) HTTPS
    ├── api/lead.php        ← recebe o formulário
    ├── admin/              ← painel administrativo (/admin)
    ├── install/            ← assistente do 1º administrador (apagar depois)
    ├── app/                ← código interno (bloqueado para o navegador)
    ├── config/             ← configuração (bloqueado para o navegador)
    ├── assets/             ← CSS, JavaScript e imagens do layout
    └── uploads/            ← imagens enviadas pelo painel (sem execução de scripts)
```

---

## 1. Quais arquivos enviar

Envie **o conteúdo** da pasta `public_html/` deste projeto — e não a pasta em si.

Não envie: `INSTALL.md`, `README.md`, `database.sql`, `tests/`, `.git/`, `.gitignore`.

⚠️ Os arquivos **`.htaccess`** começam com ponto e são “ocultos”. Eles são
essenciais para a segurança. Ao compactar/enviar, confira se foram junto:
`public_html/.htaccess`, `app/.htaccess`, `config/.htaccess`,
`uploads/.htaccess`, `admin/.htaccess`, `install/.htaccess`, `app/cli/.htaccess`.

## 2. Qual pasta usar dentro do `public_html`

Na Hostinger, cada domínio tem sua própria pasta:

```
/home/uXXXXXXXX/domains/SEUDOMINIO.com.br/public_html/
```

**Recomendado:** coloque os arquivos **diretamente na raiz** desse
`public_html` (o site abrirá em `https://seudominio.com.br/`).

Passo a passo (hPanel → **Sites** → seu site → **Gerenciador de Arquivos**):

1. Abra a pasta `public_html` do domínio.
2. Se existir um `default.php` ou `index.html` de boas-vindas da Hostinger, **exclua**.
3. No seu computador, entre na pasta `public_html/` do projeto, selecione
   **todo o conteúdo** e compacte em `site.zip`
   (no Mac/Linux, em um terminal: `cd public_html && zip -r ../site.zip .` — inclui os `.htaccess`).
4. No Gerenciador de Arquivos, clique em **Upload** e envie o `site.zip` para dentro do `public_html`.
5. Clique com o botão direito no `site.zip` → **Extract** (Extrair) → destino: o próprio `public_html` (deixe o campo de pasta vazio ou `.`).
6. Confira que `index.php`, `.htaccess`, `admin/`, `app/` etc. ficaram **diretamente** dentro de `public_html` (e não em `public_html/public_html/`).
7. Exclua o `site.zip`.

> Instalar em subpasta (ex.: `public_html/terreno/` → `seudominio.com.br/terreno/`)
> também funciona sem alterações. Nesse caso, adapte os caminhos deste guia.

> **Enviou o repositório inteiro?** (deploy via **Git** do hPanel, ou extraiu o
> ZIP baixado do GitHub na raiz do `public_html`, ficando `public_html/public_html/index.php`)
> Também funciona: o `.htaccess` da raiz do projeto encaminha os acessos para a pasta
> `public_html/` interna e bloqueia `database.sql`, `INSTALL.md`, `README.md` e `tests/`.
> Nesse formato, o `config.php` fica em `public_html/public_html/config/config.php`
> e a pasta de uploads em `public_html/public_html/uploads/`.

## 3. Criar o banco MySQL na Hostinger

hPanel → **Bancos de dados** → **Gerenciamento de banco de dados MySQL**:

1. Em “Criar um novo banco de dados MySQL e usuário”:
   - **Nome do banco:** ex. `propriedade` → a Hostinger adiciona um prefixo, ficando `u123456789_propriedade`.
   - **Usuário:** ex. `admin` → fica `u123456789_admin`.
   - **Senha:** clique em gerar uma senha forte e **guarde-a** (você vai usá-la no passo 5).
2. Clique em **Criar**.
3. Na lista abaixo, anote os nomes **completos** (com o prefixo) do banco e do usuário.

## 4. Importar as tabelas

1. Na mesma tela, na linha do banco criado, clique em **Entrar no phpMyAdmin**.
2. Na coluna da esquerda, clique no nome do banco (`u123456789_propriedade`).
3. Aba **Importar** → **Escolher arquivo** → selecione o `database.sql` deste projeto.
4. Mantenha o formato **SQL** e o conjunto de caracteres **utf-8** → **Importar** (botão no fim da página).
5. Deve aparecer “Importação finalizada com sucesso” e as tabelas:
   `admins`, `gallery`, `leads`, `login_attempts`, `property`, `settings`.

## 5. Onde informar host, nome, usuário e senha do banco

1. No Gerenciador de Arquivos, abra `public_html/config/`.
2. Clique com o botão direito em `config.sample.php` → **Copy** (Copiar) → nome: `config.php`
   (ou baixe, renomeie e reenvie).
3. Clique com o botão direito em `config.php` → **Edit** (Editar) e preencha:

```php
'db' => [
    'host'     => 'localhost',                  // database host (na Hostinger: localhost)
    'port'     => 3306,
    'name'     => 'u123456789_propriedade',     // database name (com prefixo)
    'user'     => 'u123456789_admin',           // database user (com prefixo)
    'password' => 'A_SENHA_DO_BANCO',           // database password
    'charset'  => 'utf8mb4',
],
'base_url' => 'https://seudominio.com.br',      // pode deixar '' (detecção automática)
'setup_token' => 'UMA-SEQUENCIA-LONGA-E-ALEATORIA-COM-24+-CARACTERES',
```

4. **Salve**.

> O `host` na Hostinger é `localhost` na grande maioria dos planos. Se a
> conexão falhar, confira o host exibido em **Bancos de dados → Gerenciamento**
> (alguns planos mostram algo como `mysql.hostinger.com`).

O `config.php` fica protegido: a pasta `config/` é bloqueada pelo `.htaccess`
(o navegador recebe “403 Proibido”) e o arquivo não gera saída. Ele **não**
está no Git (`.gitignore`).

## 6. Configurar o domínio

- **Domínio registrado na Hostinger:** ele já aponta para a hospedagem. Em
  hPanel → **Sites**, confirme que o site usa esse domínio.
- **Domínio registrado em outro lugar (Registro.br, GoDaddy...):**
  1. hPanel → **Sites** → **Adicionar site / domínio** e informe o domínio.
  2. No painel onde o domínio foi registrado, altere os **servidores DNS** para:
     `ns1.dns-parking.com` e `ns2.dns-parking.com`
     (ou, mantendo seu DNS, crie um registro **A** para `@` e `www` com o IP
     mostrado em hPanel → **Sites → Painel → Detalhes do plano**).
  3. A propagação leva de alguns minutos até 24 h (Registro.br costuma ser rápido).

## 7. Ativar HTTPS / SSL

1. hPanel → **Segurança → SSL** (ou **Sites → Painel → SSL**).
2. Selecione o domínio → **Instalar SSL** (Let's Encrypt gratuito). Aguarde ficar **Ativo**.
3. Forçar HTTPS — escolha **uma** das opções:
   - hPanel → SSL → ative **“Forçar HTTPS”**; **ou**
   - edite `public_html/.htaccess` e remova o `#` das duas linhas:
     ```
     # RewriteCond %{HTTPS} !=on
     # RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```
4. Abra `http://seudominio.com.br` e confirme que vai para `https://`.

> Faça o passo 8 (criar administrador) **com HTTPS já ativo**, para que a
> senha nunca trafegue sem criptografia.

## 8. Criar o primeiro administrador

1. Acesse **`https://seudominio.com.br/install/`**.
2. A página verifica: versão do PHP, extensões (PDO MySQL, GD, Fileinfo),
   permissão da pasta `uploads/`, conexão com o banco, tabelas e o
   `setup_token`. Todos os itens devem estar com ✓.
3. Preencha:
   - **Token de instalação:** o mesmo `setup_token` que você colocou no `config.php`;
   - **E-mail do administrador** (passo 9);
   - **Senha** e **Confirmar senha** (passo 10).
4. Clique em **Criar administrador**.
5. **Exclua a pasta `public_html/install/`** no Gerenciador de Arquivos.
   (Mesmo que ela fique, o assistente se desativa sozinho quando já existe
   um administrador — mas excluir é a prática correta.)
6. Opcional: no `config.php`, apague o valor de `setup_token` (deixe `''`).

### 9. Como definir o e-mail administrativo

- Na criação: campo **E-mail do administrador** no `/install/`.
- Depois: **Painel → Configurações → E-mail de acesso** (exige a senha atual).

### 10. Como definir a senha administrativa de forma segura

- Use no mínimo **10 caracteres**, com letras e números (recomendado: 14+ com
  símbolos, gerada em um gerenciador de senhas).
- A senha é digitada apenas no formulário (sobre HTTPS) e gravada no banco
  **somente como hash** (`password_hash` do PHP — bcrypt). Ela **não** fica em
  nenhum arquivo, no HTML, no JavaScript ou no Git.
- Para trocar: **Painel → Configurações → Alterar senha**.
- **Esqueceu a senha?**
  - Com acesso SSH (hPanel → Avançado → Acesso SSH), na pasta do site:
    ```
    cd domains/seudominio.com.br/public_html
    php app/cli/admin-user.php seu@email.com.br
    ```
    O comando pede a nova senha (sem exibi-la) e a redefine.
  - Sem SSH: no phpMyAdmin, apague o registro em `admins`
    (`DELETE FROM admins;`), envie novamente a pasta `install/`, defina um
    `setup_token` no `config.php` e repita o passo 8.

## 11. Permissões de pasta

Padrão da Hostinger (normalmente já vem assim):

| Item | Permissão |
|---|---|
| Pastas (todas, incluindo `uploads/`) | **755** |
| Arquivos `.php`, `.css`, `.js`, `.htaccess` | **644** |
| `config/config.php` | **640** (ou 644, se 640 der erro) |

Para alterar: botão direito no item → **Permissions** (Permissões).
**Nunca use 777.**

## 12. Configurar a pasta de uploads

- A pasta `public_html/uploads/` precisa existir com permissão **755**
  (o PHP da Hostinger roda com o seu usuário, então 755 permite gravar).
- O arquivo `uploads/.htaccess` **precisa estar lá**: ele impede a execução de
  qualquer script e só libera arquivos `.jpg`, `.jpeg`, `.png` e `.webp`.
- O sistema valida extensão, tipo MIME real, conteúdo e dimensões, gera nomes
  aleatórios e **recodifica** cada imagem (remove metadados e otimiza o peso).
- Tamanho máximo por imagem: `upload_max_mb` no `config.php` (padrão 15 MB).
  O PHP também precisa permitir: hPanel → **Avançado → Configuração do PHP →
  Opções do PHP** → `upload_max_filesize` = **16M** (ou mais) e
  `post_max_size` = **64M** (para enviar várias fotos de uma vez).
  Ali também confirme que as extensões **gd**, **fileinfo** e **pdo_mysql** estão marcadas.

## 13. Testar o formulário

1. Abra o site com parâmetros de campanha, por exemplo:
   `https://seudominio.com.br/?utm_source=teste&utm_medium=manual&utm_campaign=instalacao`
2. Clique em **QUERO RECEBER MAIS INFORMAÇÕES** — a página rola até o formulário.
3. Clique em **Enviar** vazio: os campos devem ser destacados com mensagens.
4. Preencha todos os campos (WhatsApp ganha máscara; para número estrangeiro
   comece com `+`) e envie.
5. Deve aparecer: **“Recebemos seu interesse.”** com a mensagem de confirmação.
6. Teste também pelo celular (4G e Wi-Fi).

## 14. Testar o painel administrativo

1. Clique na pequena engrenagem **⚙** no canto inferior direito do site
   (ou acesse `https://seudominio.com.br/admin/`).
2. Faça login com o e-mail e a senha criados no passo 8.
3. **Dashboard:** o total de interessados deve mostrar 1 e o lead de teste aparece em “Últimos leads”.
4. **Leads:** clique no lead → confira os dados, a origem (`teste / manual`) e a campanha.
   Clique em **Chamar no WhatsApp** (abre a conversa, sem mensagem automática).
   Altere o **Status** para “Contato realizado”, escreva uma **observação** e salve.
5. **Propriedade:** altere a headline, salve e recarregue o site — a nova headline deve aparecer.
6. **Imagens:** envie a imagem principal, o topográfico e algumas fotos na galeria.
   No site, clique no topográfico para ampliar (zoom com roda do mouse / pinça no celular).
7. **Configurações:** ajuste título SEO, descrição, URL canônica
   (`https://seudominio.com.br/`), imagem de compartilhamento e favicon.
8. Teste a segurança: em uma janela anônima, acesse
   `https://seudominio.com.br/admin/leads.php` → deve redirecionar para o login.
   Acesse `https://seudominio.com.br/config/config.php` → deve dar **403**.
9. Clique em **Sair**.

## 15. Verificar se os leads estão entrando no banco

1. hPanel → **Bancos de dados → phpMyAdmin** → selecione o banco.
2. Clique na tabela **`leads`** → aba **Visualizar**: cada envio do formulário é uma linha,
   com `created_at` (data/hora), `status`, `utm_source` … `utm_term`,
   `landing_page` e `referrer`.
3. Ou use a aba **SQL**:
   ```sql
   SELECT id, created_at, name, email, whatsapp, status, utm_source, utm_campaign
   FROM leads ORDER BY id DESC LIMIT 20;
   ```
4. No painel, **Leads → Exportar CSV** baixa a planilha (abre no Excel com acentos corretos).

Depois dos testes, você pode excluir os leads de teste no próprio painel
(botão **Excluir lead** na página do lead).

---

## Antes de divulgar (checklist)

- [ ] HTTPS ativo e forçado
- [ ] Pasta `install/` excluída
- [ ] `debug` = `false` no `config.php`
- [ ] Imagem principal, topográfico e galeria enviados
- [ ] Textos da propriedade revisados (campos vazios não aparecem no site)
- [ ] Política de Privacidade revisada (Configurações → substituir os campos **[A DEFINIR]**)
- [ ] URL canônica, título e descrição SEO configurados
- [ ] “Ocultar o site dos mecanismos de busca” **desmarcado** quando for ao ar
- [ ] Lead de teste enviado, conferido no painel e no phpMyAdmin
- [ ] Backup: hPanel → **Arquivos → Backups** (inclui banco e arquivos)

## Problemas comuns

| Sintoma | Solução |
|---|---|
| “Configuração pendente” | O `config/config.php` não existe — passo 5. |
| “Ocorreu um erro inesperado” | Geralmente credenciais do banco erradas. Para ver o detalhe, coloque temporariamente `'debug' => true` no `config.php`, recarregue, corrija e volte para `false`. |
| **403 Forbidden** na página inicial | Não há `index.php` diretamente dentro do `public_html` do domínio (os arquivos ficaram em uma subpasta, ex.: `public_html/public_html/` ou `public_html/macaibagrandenatal-.../`). Abra o Gerenciador de Arquivos e mova o conteúdo para a raiz do `public_html` — ou envie o `site.zip` e extraia direto na raiz (passo 2). |
| Erro 500 em todo o site | Um `.htaccess` corrompido no envio. Reenvie os `.htaccess` do projeto. |
| Upload falha / “excede o limite” | Aumente `upload_max_filesize` e `post_max_size` (passo 12). |
| “Muitas tentativas” no login | Aguarde 15 minutos (proteção contra força bruta). |
| Acentos estranhos no banco | Reimporte o `database.sql` com conjunto de caracteres utf-8. |
| Sessão expirada ao salvar | A página ficou aberta muito tempo (2 h sem atividade). Faça login de novo. |
