-- --------------------------------------------------------
-- Banco de dados: dentech
-- Versão: 2.1 (com anamnese completa, estoque, orçamentos e LGPD)
-- --------------------------------------------------------

CREATE DATABASE IF NOT EXISTS dentech;
USE dentech;

-- --------------------------------------------------------
-- Tabela: prontuarios (pacientes + anamnese completa)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS prontuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente VARCHAR(255) NOT NULL,
    nascimento DATE NOT NULL,
    sexo ENUM('Masculino', 'Feminino', 'Outro') NULL,
    estado_civil VARCHAR(50) NULL,
    profissao VARCHAR(100) NULL,
    rg VARCHAR(20) NULL,
    cpf VARCHAR(14) NULL,
    endereco TEXT NULL,
    cep VARCHAR(10) NULL,
    telefone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) NULL,
    observacoes TEXT,
    
    -- Campos de anamnese
    tratamento_odonto TEXT NULL,
    tratamento_medico TEXT NULL,
    medicamento_continuo TEXT NULL,
    alergia_medicamento TEXT NULL,
    alergia_outras TEXT NULL,
    problemas_saude TEXT NULL,
    gravida_meses VARCHAR(10) NULL,
    fuma_tempo VARCHAR(50) NULL,
    fuma_cigarros_dia VARCHAR(20) NULL,
    bebida_frequencia VARCHAR(100) NULL,
    drogas_uso TEXT NULL,
    doencas_transmissiveis TEXT NULL,
    cancer_familiar TEXT NULL,
    tratamento_cancer TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: agendamentos
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NULL,
    paciente_nome VARCHAR(255) NULL,
    procedimento VARCHAR(255) NOT NULL,
    data DATE NOT NULL,
    horario TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES prontuarios(id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: procedimentos (histórico clínico + medicamentos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS procedimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    medicamentos TEXT, -- ← campo para receitas
    data_procedimento DATE NOT NULL,
    FOREIGN KEY (paciente_id) REFERENCES prontuarios(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: estoque (materiais clínicos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 0,
    unidade VARCHAR(50) NOT NULL DEFAULT 'unidade',
    estoque_minimo DECIMAL(10,2) NOT NULL DEFAULT 5,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: orcamentos (orçamentos comerciais)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    data_criacao DATE NOT NULL,
    validade DATE NOT NULL,
    status ENUM('pendente', 'aceito', 'recusado') DEFAULT 'pendente',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES prontuarios(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: orcamentos_itens (itens do orçamento)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS orcamentos_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    valor_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- --------------------------------------------------------
-- Tabela: parcelas (parcelas do orcamento)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orcamento_id INT NOT NULL,
    numero_parcela TINYINT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    status ENUM('pendente','paga','atrasada') DEFAULT 'pendente',
    data_pagamento DATE NULL,
    INDEX idx_orcamento (orcamento_id),
    FOREIGN KEY (orcamento_id) REFERENCES orcamentos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Tabela: logs (tabela de logs)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) DEFAULT 'Sistema', -- Quem fez a ação
    acao VARCHAR(50) NOT NULL,              -- Tipo: Login, Criar, Editar, Excluir
    tabela VARCHAR(50),                     -- Onde: prontuarios, orcamentos
    registro_id INT,                        -- ID do item afetado
    detalhes TEXT,                          -- Texto extra (ex: "Deletou paciente João")
    ip VARCHAR(45),                         -- IP do usuário (::1 no localhost)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;