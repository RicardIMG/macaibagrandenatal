#!/usr/bin/env python3
"""Testa o assistente de primeira instalação (config.php ausente + banco vazio).
Uso: BASE_URL=... DB_NAME=... DB_USER=... DB_PASS=... MYSQL="mysql ..." python3 tests/wizard_test.py"""
import os, re, subprocess, sys
import requests

BASE = os.environ.get("BASE_URL", "http://127.0.0.1:8080").rstrip("/")
MYSQL = os.environ["MYSQL"].split()

def sql(q):
    return subprocess.run(MYSQL + ["-N", "-B", "-e", q], capture_output=True, text=True, check=True).stdout.strip()

def ok(c, m):
    if not c:
        print("  FALHOU:", m); sys.exit(1)
    print("  ok -", m)

s = requests.Session()
r = s.get(BASE + "/")
ok("Configurar o site" in r.text, "assistente aparece quando não há config.php")
ok(s.get(BASE + "/admin/login.php").text.count("Configurar o site") == 1, "assistente também protege o /admin")
csrf = re.search(r'name="csrf" value="([a-f0-9]+)"', r.text).group(1)
base = {"setup_wizard": "1", "csrf": csrf, "db_host": "localhost", "db_name": os.environ["DB_NAME"],
        "db_user": os.environ["DB_USER"], "email": "dono@example.com",
        "password": "SenhaForte2026!", "password_confirm": "SenhaForte2026!", "site_url": ""}
r = s.post(BASE + "/", data={**base, "db_pass": "errada"})
ok("Usuário ou senha do banco incorretos" in r.text, "senha do banco errada gera mensagem clara")
r = s.post(BASE + "/", data={**base, "db_pass": os.environ["DB_PASS"], "password_confirm": "outra"})
ok("não confere" in r.text, "confirmação de senha validada")
r = s.post(BASE + "/", data={**base, "db_pass": os.environ["DB_PASS"]})
ok("Tudo pronto" in r.text, "instalação concluída")
ok(sql("SELECT COUNT(*) FROM property") == "1", "tabelas criadas e conteúdo inicial importado")
ok(sql("SELECT password_hash FROM admins WHERE email='dono@example.com'").startswith("$2y$"), "administrador criado com hash")
r = requests.get(BASE + "/")
ok("260.000 m² para um novo projeto" in r.text, "site carrega após a instalação")
r = s.post(BASE + "/", data={**base, "db_pass": os.environ["DB_PASS"]})
ok("Configurar o site" not in r.text, "assistente desaparece depois de configurado")
a = requests.Session()
c = re.search(r'name="csrf_token" value="([a-f0-9]+)"', a.get(BASE + "/admin/login.php").text).group(1)
r = a.post(BASE + "/admin/login.php", data={"csrf_token": c, "email": "dono@example.com", "password": "SenhaForte2026!"})
ok("Total de interessados" in r.text, "login com o administrador criado no assistente")
print("ASSISTENTE OK")
