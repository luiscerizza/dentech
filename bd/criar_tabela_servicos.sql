CREATE TABLE servicos (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    valor_sugerido DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_criacao DATE NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_servicos_ativo (ativo),
    INDEX idx_servicos_nome (nome)
);