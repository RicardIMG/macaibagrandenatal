<?php
/**
 * ARQUIVO DE CONFIGURAÇÃO — MODELO
 *
 * 1. Copie este arquivo para "config.php" (na mesma pasta).
 * 2. Preencha os dados do banco MySQL criado na Hostinger.
 * 3. Defina um "setup_token" longo e aleatório (usado UMA vez em /install/).
 *
 * O arquivo config.php NUNCA deve ser enviado ao Git (já está no .gitignore).
 * A senha do administrador NÃO fica aqui: ela é criada em /install/ e
 * armazenada no banco apenas como hash (password_hash).
 */
return [
    'db' => [
        'host'     => 'localhost',          // Na Hostinger normalmente é "localhost"
        'port'     => 3306,
        'name'     => 'u000000000_propriedade', // Nome do banco (com o prefixo da Hostinger)
        'user'     => 'u000000000_admin',       // Usuário do banco (com o prefixo)
        'password' => 'COLOQUE_A_SENHA_DO_BANCO_AQUI',
        'charset'  => 'utf8mb4',
    ],

    // URL pública do site, sem barra no final. Ex.: https://www.seudominio.com.br
    // Pode ficar vazio: o sistema detecta automaticamente.
    'base_url' => '',

    // Token de instalação: usado apenas para criar o primeiro administrador em /install/.
    // Use uma sequência longa e aleatória (mínimo 24 caracteres). Ex.: gere em
    // https://www.random.org/strings/ ou use um gerenciador de senhas.
    'setup_token' => '',

    // Fuso horário exibido no painel.
    'timezone' => 'America/Sao_Paulo',

    // Uploads: tamanho máximo por arquivo, em megabytes.
    // (O limite do PHP na Hostinger — upload_max_filesize — também precisa comportar esse valor.)
    'upload_max_mb' => 15,

    // Login: tentativas permitidas antes do bloqueio temporário.
    'login_max_attempts'   => 5,   // por e-mail
    'login_max_ip_attempts'=> 15,  // por IP
    'login_lock_minutes'   => 15,

    // Sessão administrativa expira após X minutos sem atividade.
    'session_idle_minutes' => 120,

    // Mantenha FALSE em produção (não exibe erros técnicos aos visitantes).
    'debug' => false,
];
