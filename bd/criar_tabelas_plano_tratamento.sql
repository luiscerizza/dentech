-- ============================================================
-- DENTECH
-- PLANO DE TRATAMENTO
-- ============================================================

CREATE TABLE IF NOT EXISTS planos_tratamento (
    id INT NOT NULL AUTO_INCREMENT,
    paciente_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    status ENUM(
        'planejamento',
        'em_andamento',
        'concluido',
        'cancelado'
    ) NOT NULL DEFAULT 'planejamento',
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_planos_tratamento_paciente (paciente_id),
    INDEX idx_planos_tratamento_status (status),

    CONSTRAINT fk_planos_tratamento_paciente
        FOREIGN KEY (paciente_id)
        REFERENCES prontuarios (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS planos_tratamento_itens (
    id INT NOT NULL AUTO_INCREMENT,
    plano_id INT NOT NULL,
    servico_id INT NULL,
    descricao VARCHAR(255) NOT NULL,
    dente_regiao VARCHAR(100) NULL,
    prioridade ENUM(
        'baixa',
        'media',
        'alta'
    ) NOT NULL DEFAULT 'media',
    valor_estimado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM(
        'planejado',
        'em_andamento',
        'concluido',
        'cancelado'
    ) NOT NULL DEFAULT 'planejado',
    ordem INT NOT NULL DEFAULT 0,
    observacoes TEXT NULL,

    PRIMARY KEY (id),

    INDEX idx_plano_itens_plano (plano_id),
    INDEX idx_plano_itens_servico (servico_id),
    INDEX idx_plano_itens_status (status),
    INDEX idx_plano_itens_ordem (plano_id, ordem),

    CONSTRAINT fk_plano_itens_plano
        FOREIGN KEY (plano_id)
        REFERENCES planos_tratamento (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_plano_itens_servico
        FOREIGN KEY (servico_id)
        REFERENCES servicos (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
