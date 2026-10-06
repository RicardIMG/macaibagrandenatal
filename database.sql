-- =====================================================================
--  Landing Page de Propriedade — Estrutura do banco de dados (MySQL)
--  Importe este arquivo pelo phpMyAdmin da Hostinger (aba "Importar").
--  Compatível com MySQL 5.7+ / MariaDB 10.3+  (charset utf8mb4)
--
--  Este arquivo NÃO cria nenhum administrador nem contém senhas.
--  O primeiro administrador é criado pelo assistente /install/
--  (veja INSTALL.md).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------
-- Administradores
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tentativas de login (proteção contra força bruta)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45)  NOT NULL,
  `email`      VARCHAR(190) NOT NULL DEFAULT '',
  `success`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_ip` (`ip_address`, `created_at`),
  KEY `idx_attempts_email` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Leads / interessados
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id`     CHAR(32)     NULL DEFAULT NULL,
  `name`              VARCHAR(150) NOT NULL,
  `whatsapp`          VARCHAR(40)  NOT NULL,
  `email`             VARCHAR(190) NOT NULL,
  `company`           VARCHAR(190) NOT NULL,
  `website_instagram` VARCHAR(255) NOT NULL,
  `interest_reason`   TEXT         NOT NULL,
  `status`            VARCHAR(30)  NOT NULL DEFAULT 'novo',
  `notes`             TEXT         NULL,
  `utm_source`        VARCHAR(190) NULL DEFAULT NULL,
  `utm_medium`        VARCHAR(190) NULL DEFAULT NULL,
  `utm_campaign`      VARCHAR(190) NULL DEFAULT NULL,
  `utm_content`       VARCHAR(190) NULL DEFAULT NULL,
  `utm_term`          VARCHAR(190) NULL DEFAULT NULL,
  `landing_page`      VARCHAR(500) NULL DEFAULT NULL,
  `referrer`          VARCHAR(500) NULL DEFAULT NULL,
  `ip_address`        VARCHAR(45)  NULL DEFAULT NULL,
  `user_agent`        VARCHAR(255) NULL DEFAULT NULL,
  `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leads_submission` (`submission_id`),
  KEY `idx_leads_created` (`created_at`),
  KEY `idx_leads_status` (`status`),
  KEY `idx_leads_email` (`email`),
  KEY `idx_leads_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Propriedade (registro único, id = 1)
-- Campos vazios NÃO são exibidos no site.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `property` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hero_eyebrow`        VARCHAR(190) NULL DEFAULT NULL,
  `headline`            VARCHAR(255) NULL DEFAULT NULL,
  `subheadline`         VARCHAR(500) NULL DEFAULT NULL,
  `area`                VARCHAR(100) NULL DEFAULT NULL,
  `area_label`          VARCHAR(190) NULL DEFAULT NULL,
  `description`         TEXT NULL,
  `location`            TEXT NULL,
  `municipality`        VARCHAR(190) NULL DEFAULT NULL,
  `state`               VARCHAR(100) NULL DEFAULT NULL,
  `access_info`         TEXT NULL,
  `infrastructure`      TEXT NULL,
  `vocation`            TEXT NULL,
  `urban_info`          TEXT NULL,
  `characteristics`     TEXT NULL,
  `differentials`       TEXT NULL,
  `documentation`       TEXT NULL,
  `additional_info`     TEXT NULL,
  `topographic_title`   VARCHAR(190) NULL DEFAULT NULL,
  `topographic_text`    TEXT NULL,
  `hero_cta_text`       VARCHAR(120) NULL DEFAULT NULL,
  `mid_cta_title`       VARCHAR(190) NULL DEFAULT NULL,
  `mid_cta_text`        TEXT NULL,
  `mid_cta_button`      VARCHAR(120) NULL DEFAULT NULL,
  `form_title`          VARCHAR(190) NULL DEFAULT NULL,
  `form_intro`          TEXT NULL,
  `form_button`         VARCHAR(120) NULL DEFAULT NULL,
  `success_title`       VARCHAR(190) NULL DEFAULT NULL,
  `success_message`     TEXT NULL,
  `hero_image`          VARCHAR(255) NULL DEFAULT NULL,
  `hero_image_thumb`    VARCHAR(255) NULL DEFAULT NULL,
  `hero_image_alt`      VARCHAR(255) NULL DEFAULT NULL,
  `topographic_image`   VARCHAR(255) NULL DEFAULT NULL,
  `topographic_full`    VARCHAR(255) NULL DEFAULT NULL,
  `topographic_alt`     VARCHAR(255) NULL DEFAULT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Galeria de imagens
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gallery` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_path`  VARCHAR(255) NOT NULL,
  `thumb_path`  VARCHAR(255) NULL DEFAULT NULL,
  `caption`     VARCHAR(255) NULL DEFAULT NULL,
  `alt_text`    VARCHAR(255) NULL DEFAULT NULL,
  `tag`         VARCHAR(60)  NULL DEFAULT NULL,
  `width`       INT UNSIGNED NULL DEFAULT NULL,
  `height`      INT UNSIGNED NULL DEFAULT NULL,
  `sort_order`  INT NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gallery_order` (`sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Configurações gerais (SEO, marca, privacidade etc.) — chave/valor
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(64) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Conteúdo inicial (apenas a informação confirmada: ~260.000 m²)
-- ---------------------------------------------------------------------
INSERT INTO `property`
  (`id`, `hero_eyebrow`, `headline`, `subheadline`, `area`, `area_label`, `description`,
   `topographic_title`, `topographic_text`,
   `hero_cta_text`, `mid_cta_title`, `mid_cta_text`, `mid_cta_button`,
   `form_title`, `form_intro`, `form_button`, `success_title`, `success_message`)
VALUES
  (1,
   'Oportunidade de aquisição',
   '260.000 m² para um novo projeto',
   'Uma propriedade singular para quem enxerga potencial onde outros enxergam apenas terra.',
   '260.000 m²',
   'Área total aproximada',
   'Uma área de aproximadamente 260.000 m², apresentada de forma reservada a interessados com real intenção de aquisição. As informações complementares são compartilhadas diretamente com nossa equipe.',
   'Conheça a área',
   'Levantamento topográfico do terreno. Clique na imagem para ampliar e analisar os detalhes.',
   'Quero receber mais informações',
   'Tem interesse nesta propriedade?',
   'Preencha seus dados para que possamos entender seu interesse e apresentar as informações complementares da propriedade.',
   'Solicitar contato',
   'Solicite informações',
   'Conte-nos um pouco sobre você e sua empresa. Retornaremos pelos contatos informados.',
   'Enviar',
   'Recebemos seu interesse.',
   'Nossa equipe analisará as informações e, caso exista aderência, entraremos em contato através dos dados informados.')
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('site_name',        'Propriedade 260.000 m²'),
  ('seo_title',        '260.000 m² para um novo projeto | Oportunidade de aquisição'),
  ('seo_description',  'Propriedade com área total aproximada de 260.000 m². Solicite informações complementares e converse com nossa equipe.'),
  ('canonical_url',    ''),
  ('og_image',         ''),
  ('favicon',          ''),
  ('privacy_url',      ''),
  ('privacy_text',     ''),
  ('lgpd_notice',      'Ao enviar seus dados, você concorda em ser contatado por nossa equipe para tratar exclusivamente sobre esta oportunidade.'),
  ('footer_text',      'Apresentação reservada. As informações desta página têm caráter informativo e não constituem oferta pública.'),
  ('noindex_site',     '0')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
