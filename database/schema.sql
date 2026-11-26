-- ============================================================================================
-- Cofrelo: estrutura do banco de dados (MySQL 8)
--
-- Evolução do schema inicial do grupo (usuario, carteira, transacao, meta, categoria):
--   * carteira passou a se chamar conta e o saldo é calculado pelas transações pagas;
--   * valores em dinheiro usam DECIMAL(12,2), que não tem erro de arredondamento como o float;
--   * a meta ficou ligada ao usuário e ganhou o histórico de depósitos (meta_deposito);
--   * as assinaturas (cobranças mensais) ganharam tabela própria.
--
-- O sistema aplica este arquivo sozinho na primeira execução (src/Banco/Migrador.php).
-- No XAMPP também dá para importar pelo phpMyAdmin.
-- ============================================================================================

CREATE TABLE IF NOT EXISTS usuario (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(120)  NOT NULL,
    email            VARCHAR(160)  NOT NULL,
    senha_hash       VARCHAR(255)  NOT NULL,
    orcamento_mensal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    DATETIME      NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conta (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id    INT UNSIGNED  NOT NULL,
    nome          VARCHAR(80)   NOT NULL,
    tipo          ENUM('corrente','pagamento','investimento','dinheiro') NOT NULL DEFAULT 'corrente',
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    descricao     VARCHAR(160)  NULL,
    ativa         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_conta_usuario (usuario_id),
    CONSTRAINT fk_conta_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categoria (
    id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(60)  NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    cor  CHAR(7)      NOT NULL DEFAULT '#8C978F',
    PRIMARY KEY (id),
    UNIQUE KEY uk_categoria_nome_tipo (nome, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assinatura (
    id              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    usuario_id      INT UNSIGNED     NOT NULL,
    conta_id        INT UNSIGNED     NOT NULL,
    categoria_id    INT UNSIGNED     NOT NULL,
    descricao       VARCHAR(120)     NOT NULL,
    valor           DECIMAL(12,2)    NOT NULL,
    forma_pagamento VARCHAR(20)      NULL,
    dia_cobranca    TINYINT UNSIGNED NOT NULL,
    aviso_dias      TINYINT UNSIGNED NOT NULL DEFAULT 3,
    ativa           TINYINT(1)       NOT NULL DEFAULT 1,
    inicio          DATE             NOT NULL,
    criado_em       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_assinatura_usuario (usuario_id),
    CONSTRAINT fk_assinatura_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT fk_assinatura_conta FOREIGN KEY (conta_id) REFERENCES conta (id) ON DELETE CASCADE,
    CONSTRAINT fk_assinatura_categoria FOREIGN KEY (categoria_id) REFERENCES categoria (id),
    CONSTRAINT ck_assinatura_dia CHECK (dia_cobranca BETWEEN 1 AND 28)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transacao (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id       INT UNSIGNED  NOT NULL,
    conta_id         INT UNSIGNED  NOT NULL,
    conta_destino_id INT UNSIGNED  NULL,
    categoria_id     INT UNSIGNED  NULL,
    assinatura_id    INT UNSIGNED  NULL,
    tipo             ENUM('receita','despesa','transferencia') NOT NULL,
    descricao        VARCHAR(120)  NOT NULL,
    valor            DECIMAL(12,2) NOT NULL,
    data             DATE          NOT NULL,
    forma_pagamento  VARCHAR(20)   NULL,
    status           ENUM('pago','pendente') NOT NULL DEFAULT 'pago',
    observacao       VARCHAR(255)  NULL,
    criado_em        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em    DATETIME      NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_transacao_usuario_data (usuario_id, data),
    KEY ix_transacao_conta (conta_id),
    KEY ix_transacao_destino (conta_destino_id),
    KEY ix_transacao_categoria (categoria_id),
    KEY ix_transacao_assinatura (assinatura_id),
    CONSTRAINT fk_transacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT fk_transacao_conta FOREIGN KEY (conta_id) REFERENCES conta (id) ON DELETE CASCADE,
    CONSTRAINT fk_transacao_destino FOREIGN KEY (conta_destino_id) REFERENCES conta (id) ON DELETE CASCADE,
    CONSTRAINT fk_transacao_categoria FOREIGN KEY (categoria_id) REFERENCES categoria (id),
    CONSTRAINT fk_transacao_assinatura FOREIGN KEY (assinatura_id) REFERENCES assinatura (id) ON DELETE SET NULL,
    CONSTRAINT ck_transacao_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meta (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id     INT UNSIGNED  NOT NULL,
    nome           VARCHAR(80)   NOT NULL,
    descricao      VARCHAR(120)  NULL,
    valor_objetivo DECIMAL(12,2) NOT NULL,
    valor_atual    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    data_inicio    DATE          NOT NULL,
    data_limite    DATE          NULL,
    icone          VARCHAR(20)   NOT NULL DEFAULT 'alvo',
    criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_meta_usuario (usuario_id),
    CONSTRAINT fk_meta_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT ck_meta_objetivo CHECK (valor_objetivo > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meta_deposito (
    id        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    meta_id   INT UNSIGNED  NOT NULL,
    valor     DECIMAL(12,2) NOT NULL,
    data      DATE          NOT NULL,
    criado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_deposito_meta (meta_id),
    CONSTRAINT fk_deposito_meta FOREIGN KEY (meta_id) REFERENCES meta (id) ON DELETE CASCADE,
    CONSTRAINT ck_deposito_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias padrão, iguais para todos os usuários. As cores seguem o protótipo.
INSERT IGNORE INTO categoria (nome, tipo, cor) VALUES
    ('Moradia',         'despesa', '#2F6FDE'),
    ('Alimentação',     'despesa', '#EE6A35'),
    ('Saúde',           'despesa', '#22A06B'),
    ('Transporte',      'despesa', '#6B4FD8'),
    ('Assinaturas',     'despesa', '#E07BB0'),
    ('Lazer',           'despesa', '#D99A00'),
    ('Educação',        'despesa', '#0E9AA7'),
    ('Compras',         'despesa', '#B5654A'),
    ('Outras despesas', 'despesa', '#8C978F'),
    ('Salário',         'receita', '#1D704F'),
    ('Freelance',       'receita', '#4C9A6E'),
    ('Rendimentos',     'receita', '#8CB79C'),
    ('Outras receitas', 'receita', '#A7B3AC');
