#!/usr/bin/env python3
"""
Teste de ponta a ponta (ambiente local de desenvolvimento).

Fluxo: visitante -> landing -> formulário -> MySQL -> login admin -> lead ->
status -> edição da propriedade/imagens -> alterações no site.

Uso:
  BASE_URL=http://127.0.0.1:8080 SETUP_TOKEN=... IMG_DIR=/caminho/imagens \
  MYSQL="mysql -u usuario -psenha banco" python3 tests/e2e_test.py

Requer: python3 + requests. NÃO execute contra o site em produção.
"""
import os
import re
import subprocess
import sys
import time
import uuid

import requests

BASE = os.environ.get("BASE_URL", "http://127.0.0.1:8080").rstrip("/")
TOKEN = os.environ["SETUP_TOKEN"]
IMG = os.environ["IMG_DIR"]
MYSQL = os.environ["MYSQL"].split()
ADMIN_EMAIL = "admin@example.com"
ADMIN_PASS = "SenhaForte2026!"

passed = 0


def ok(cond, msg):
    global passed
    if not cond:
        print("  FALHOU:", msg)
        sys.exit(1)
    passed += 1
    print("  ok -", msg)


def sql(q):
    out = subprocess.run(MYSQL + ["--default-character-set=utf8mb4", "-N", "-B", "-e", q],
                         capture_output=True, text=True, check=True)
    return out.stdout.strip()


def csrf(html):
    m = re.search(r'name="csrf_token" value="([a-f0-9]+)"', html)
    assert m, "csrf não encontrado"
    return m.group(1)


print("1. Landing page")
visitor = requests.Session()
r = visitor.get(BASE + "/?utm_source=google&utm_medium=cpc&utm_campaign=lancamento&utm_content=anuncio1&utm_term=terreno")
ok(r.status_code == 200, "landing responde 200")
ok("260.000 m² para um novo projeto" in r.text, "headline padrão exibida")
ok("Área total aproximada" in r.text, "área exibida")
ok("Conheça a área" in r.text, "seção topográfico presente")
ok('id="galeria"' not in r.text, "galeria vazia não gera bloco")
ok('id="detalhes"' not in r.text, "detalhes vazios não geram bloco")
ok("admin/" in r.text and "admin-gear" in r.text, "engrenagem administrativa presente")
token = re.search(r'name="form_token" value="([^"]+)"', r.text).group(1)

print("2. Validação do formulário (backend)")
time.sleep(3.2)
r = visitor.post(BASE + "/api/lead.php", data={"name": "", "email": "x", "form_token": token},
                 headers={"Accept": "application/json"})
ok(r.status_code == 422 and r.json()["errors"].get("email"), "rejeita dados inválidos com erros por campo")

print("3. Envio do lead")
sid = uuid.uuid4().hex
lead = {
    "name": "Maria Teste", "whatsapp": "(84) 99876-5432", "email": "maria@empresa.com.br",
    "company": "Empresa Teste Ltda", "website_instagram": "@empresateste",
    "interest_reason": "Temos interesse em desenvolver um projeto na área.\nGostaríamos de mais dados.",
    "utm_source": "google", "utm_medium": "cpc", "utm_campaign": "lancamento",
    "utm_content": "anuncio1", "utm_term": "terreno",
    "landing_page": BASE + "/?utm_source=google", "referrer": "https://www.google.com/",
    "submission_id": sid, "form_token": token, "company_fax": "",
}
r = visitor.post(BASE + "/api/lead.php", data=lead, headers={"Accept": "application/json"})
ok(r.status_code == 200 and r.json()["ok"] is True, "lead aceito")
r2 = visitor.post(BASE + "/api/lead.php", data=lead, headers={"Accept": "application/json"})
ok(r2.json().get("duplicate") is True, "reenvio idêntico não duplica")
row = sql(f"SELECT name, whatsapp, email, company, website_instagram, status, utm_source, utm_medium, utm_campaign, utm_content, utm_term, created_at FROM leads WHERE submission_id='{sid}'")
ok(row.startswith("Maria Teste\t(84) 99876-5432\tmaria@empresa.com.br\tEmpresa Teste Ltda\t@empresateste\tnovo\tgoogle\tcpc\tlancamento\tanuncio1\tterreno"), "lead gravado no MySQL com UTMs e status 'novo'")
ok(sql(f"SELECT COUNT(*) FROM leads WHERE submission_id='{sid}'") == "1", "apenas 1 registro no banco")

# honeypot
r = visitor.post(BASE + "/api/lead.php", data={**lead, "submission_id": uuid.uuid4().hex, "company_fax": "spam"},
                 headers={"Accept": "application/json"})
ok(r.json()["ok"] and sql("SELECT COUNT(*) FROM leads") == "1", "robô (honeypot) não gera lead")

# envio sem JS (form tradicional)
r = visitor.post(BASE + "/api/lead.php", data={**lead, "submission_id": uuid.uuid4().hex, "email": "joao@x.com", "name": "João Sem JS"},
                 allow_redirects=False)
ok(r.status_code == 303 and "enviado=1" in r.headers["Location"], "envio sem JavaScript redireciona para confirmação")

print("4. Proteção do /admin")
anon = requests.Session()
r = anon.get(BASE + "/admin/", allow_redirects=False)
ok(r.status_code in (302, 303) and "login.php" in r.headers["Location"], "/admin sem login redireciona ao login")
for page in ["leads.php", "lead.php?id=1", "export.php", "property.php", "images.php", "settings.php"]:
    r = anon.get(BASE + "/admin/" + page, allow_redirects=False)
    ok(r.status_code in (302, 303), f"/admin/{page} protegido")
r = anon.get(BASE + "/admin/login.php")
ok("noindex" in r.headers.get("X-Robots-Tag", "") and "noindex" in r.text, "admin com noindex")
ok("password" not in r.text.lower() or "type=\"password\"" in r.text, "login sem credenciais no HTML")

print("5. Instalação do primeiro administrador")
inst = requests.Session()
r = inst.get(BASE + "/install/")
c = csrf(r.text)
r = inst.post(BASE + "/install/", data={"csrf_token": c, "setup_token": "errado", "email": ADMIN_EMAIL, "password": ADMIN_PASS, "password_confirm": ADMIN_PASS})
ok("Token de instalação incorreto" in r.text, "token de instalação errado é recusado")
r = inst.post(BASE + "/install/", data={"csrf_token": c, "setup_token": TOKEN, "email": ADMIN_EMAIL, "password": ADMIN_PASS, "password_confirm": ADMIN_PASS})
ok("Administrador criado" in r.text, "administrador criado")
h = sql(f"SELECT password_hash FROM admins WHERE email='{ADMIN_EMAIL}'")
ok(h.startswith("$2y$") or h.startswith("$argon"), "senha armazenada como hash")
r = inst.get(BASE + "/install/")
ok("Este assistente está desativado" in r.text, "instalador desativado após o 1º admin")

print("6. Login")
adm = requests.Session()
r = adm.get(BASE + "/admin/login.php")
c = csrf(r.text)
r = adm.post(BASE + "/admin/login.php", data={"csrf_token": c, "email": ADMIN_EMAIL, "password": "errada123"})
ok("E-mail ou senha inválidos" in r.text, "senha errada recusada")
r = adm.post(BASE + "/admin/login.php", data={"email": ADMIN_EMAIL, "password": ADMIN_PASS}, allow_redirects=False)
ok(r.status_code == 403, "login sem CSRF recusado")
r = adm.get(BASE + "/admin/login.php")
c = csrf(r.text)
r = adm.post(BASE + "/admin/login.php", data={"csrf_token": c, "email": ADMIN_EMAIL, "password": ADMIN_PASS})
ok("Dashboard" in r.text and "Total de interessados" in r.text, "login ok -> dashboard")
ok("Maria Teste" in r.text, "dashboard mostra o último lead")

print("7. Leads")
r = adm.get(BASE + "/admin/leads.php?q=maria")
ok("Maria Teste" in r.text and "google / cpc" in r.text and "lancamento" in r.text, "busca por nome + origem/campanha")
r = adm.get(BASE + "/admin/leads.php?q=99876")
ok("Maria Teste" in r.text, "busca por WhatsApp")
r = adm.get(BASE + "/admin/leads.php?q=Empresa+Teste")
ok("Maria Teste" in r.text, "busca por empresa")
lead_id = sql(f"SELECT id FROM leads WHERE submission_id='{sid}'")
r = adm.get(BASE + f"/admin/lead.php?id={lead_id}")
ok("https://wa.me/5584998765432" in r.text, "botão WhatsApp com número internacional, sem mensagem automática")
ok("Temos interesse em desenvolver" in r.text, "detalhe exibe motivo do interesse")
c = csrf(r.text)
r = adm.post(BASE + f"/admin/lead.php?id={lead_id}", data={"csrf_token": c, "id": lead_id, "action": "update", "status": "qualificado", "notes": "Ligar na segunda."})
ok("Lead atualizado" in r.text, "status alterado")
ok(sql(f"SELECT CONCAT(status,'|',notes) FROM leads WHERE id={lead_id}") == "qualificado|Ligar na segunda.", "status e observações gravados no banco")
r = adm.get(BASE + "/admin/leads.php?status=qualificado")
ok("Maria Teste" in r.text and "João Sem JS" not in r.text, "filtro por status")
r = adm.get(BASE + "/admin/export.php")
ok(r.headers["Content-Type"].startswith("text/csv") and "Maria Teste" in r.content.decode("utf-8-sig"), "exportação CSV")

print("8. Propriedade")
r = adm.get(BASE + "/admin/property.php")
c = csrf(r.text)
form = dict(re.findall(r'<input type="text" name="([a-z_]+)" value="([^"]*)"', r.text))
form.update({k: v for k, v in re.findall(r'<textarea name="([a-z_]+)"[^>]*>([^<]*)</textarea>', r.text)})
import html as _h
form = {k: _h.unescape(v) for k, v in form.items()}
form.update({
    "csrf_token": c,
    "headline": "Um território para grandes projetos",
    "municipality": "Município Teste",
    "characteristics": "Característica A\nCaracterística B",
    "differentials": "",
})
r = adm.post(BASE + "/admin/property.php", data=form)
ok("Informações da propriedade salvas" in r.text, "propriedade salva")
r = visitor.get(BASE + "/")
ok("Um território para grandes projetos" in r.text, "nova headline aparece no site")
ok("Município Teste" in r.text and "Característica B" in r.text, "novos campos aparecem no site")
ok("<h3>Diferenciais</h3>" not in r.text, "campo vazio continua oculto")

print("9. Imagens")
r = adm.get(BASE + "/admin/images.php")
c = csrf(r.text)
with open(os.path.join(IMG, "hero.jpg"), "rb") as f:
    r = adm.post(BASE + "/admin/images.php", data={"csrf_token": c, "action": "hero_upload"}, files={"image": ("foto.jpg", f, "image/jpeg")})
ok("Imagem principal atualizada" in r.text, "upload da imagem principal")
with open(os.path.join(IMG, "topo.png"), "rb") as f:
    r = adm.post(BASE + "/admin/images.php", data={"csrf_token": c, "action": "topo_upload"}, files={"image": ("topo.png", f, "image/png")})
ok("Planta da área atualizada" in r.text, "upload da planta da área")
files = [("images[]", (n, open(os.path.join(IMG, n), "rb"), "image/" + ("webp" if n.endswith("webp") else "jpeg"))) for n in ["g1.jpg", "g2.jpg", "g3.webp"]]
r = adm.post(BASE + "/admin/images.php", data={"csrf_token": c, "action": "gallery_upload", "tag": "Drone"}, files=files)
ok("3 imagem(ns) adicionada(s)" in r.text, "upload múltiplo na galeria")
for bad, mime in [("evil.php", "image/jpeg"), ("evil.jpg", "image/jpeg"), ("fake.jpg", "image/jpeg")]:
    with open(os.path.join(IMG, bad), "rb") as f:
        r = adm.post(BASE + "/admin/images.php", data={"csrf_token": c, "action": "hero_upload"}, files={"image": (bad, f, mime)})
    ok("flash-error" in r.text, f"upload malicioso '{bad}' recusado")
ok(not any(n.endswith(".php") for n in os.listdir(os.path.join(os.path.dirname(__file__), "..", "public_html", "uploads"))) if os.path.isdir(os.path.join(os.path.dirname(__file__), "..", "public_html", "uploads")) else True, "nenhum .php na pasta uploads")

ids = sql("SELECT GROUP_CONCAT(id ORDER BY sort_order) FROM gallery").split(",")
r = adm.get(BASE + "/admin/images.php")
c = csrf(r.text)
new_order = list(reversed(ids))
data = [("csrf_token", c), ("action", "gallery_save")] + [("order[]", i) for i in new_order]
data += [(f"caption[{i}]", f"Legenda {i}") for i in ids]
r = adm.post(BASE + "/admin/images.php", data=data)
ok(sql("SELECT GROUP_CONCAT(id ORDER BY sort_order) FROM gallery").split(",") == new_order, "reordenação da galeria")
r = adm.post(BASE + "/admin/images.php", data={"csrf_token": c, "action": "gallery_delete", "id": new_order[-1]})
ok(sql("SELECT COUNT(*) FROM gallery") == "2", "exclusão de imagem da galeria")

r = visitor.get(BASE + "/")
hero = sql("SELECT hero_image FROM property WHERE id=1")
topo = sql("SELECT topographic_full FROM property WHERE id=1")
ok(hero and hero in r.text, "imagem principal nova aparece no site")
ok(topo and topo in r.text, "topográfico em alta resolução disponível no site")
ok('id="galeria"' in r.text and f"Legenda {new_order[0]}" in r.text, "galeria aparece no site, com legendas")
img = visitor.get(BASE + "/" + hero)
ok(img.status_code == 200 and img.headers["Content-Type"].startswith("image/"), "imagem servida corretamente")

print("10. Configurações e logout")
r = adm.get(BASE + "/admin/settings.php")
c = csrf(r.text)
r = adm.post(BASE + "/admin/settings.php", data={"csrf_token": c, "action": "general", "site_name": "Nome Teste", "seo_title": "Título SEO Teste",
                                                  "seo_description": "Descrição", "canonical_url": "https://www.exemplo.com.br/", "lgpd_notice": "Aviso LGPD teste.",
                                                  "privacy_url": "", "privacy_text": "", "footer_text": "Rodapé"})
r = visitor.get(BASE + "/")
ok("<title>Título SEO Teste</title>" in r.text and 'rel="canonical" href="https://www.exemplo.com.br/"' in r.text, "SEO/canonical configuráveis")
ok("Aviso LGPD teste." in r.text, "aviso LGPD configurável")
r = adm.post(BASE + "/admin/logout.php", data={"csrf_token": c})
r = adm.get(BASE + "/admin/", allow_redirects=False)
ok(r.status_code in (302, 303), "logout encerra a sessão")

print("11. Limite de tentativas de login")
brute = requests.Session()
for i in range(6):
    c = csrf(brute.get(BASE + "/admin/login.php").text)
    r = brute.post(BASE + "/admin/login.php", data={"csrf_token": c, "email": ADMIN_EMAIL, "password": f"errada{i}xx"})
ok("Muitas tentativas" in r.text, "bloqueio após tentativas excessivas")
c = csrf(brute.get(BASE + "/admin/login.php").text)
r = brute.post(BASE + "/admin/login.php", data={"csrf_token": c, "email": ADMIN_EMAIL, "password": ADMIN_PASS})
ok("Muitas tentativas" in r.text, "mesmo a senha correta é bloqueada durante o bloqueio")

print(f"\nTODOS OS {passed} TESTES PASSARAM")
