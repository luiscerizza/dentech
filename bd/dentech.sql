-- dentech.estoque definição

CREATE TABLE `estoque` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `quantidade` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unidade` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unidade',
  `estoque_minimo` decimal(10,2) NOT NULL DEFAULT '5.00',
  `valor_item` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_sugerido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.logs definição

CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Sistema',
  `acao` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tabela` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `registro_id` int DEFAULT NULL,
  `detalhes` text COLLATE utf8mb4_general_ci,
  `ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.prontuarios definição

CREATE TABLE `prontuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nascimento` date NOT NULL,
  `sexo` enum('Masculino','Feminino','Outro') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado_civil` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `profissao` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rg` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cpf` varchar(14) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `endereco` text COLLATE utf8mb4_general_ci,
  `cep` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefone` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacoes` text COLLATE utf8mb4_general_ci,
  `tratamento_odonto` text COLLATE utf8mb4_general_ci,
  `tratamento_medico` text COLLATE utf8mb4_general_ci,
  `medicamento_continuo` text COLLATE utf8mb4_general_ci,
  `alergia_medicamento` text COLLATE utf8mb4_general_ci,
  `alergia_outras` text COLLATE utf8mb4_general_ci,
  `problemas_saude` text COLLATE utf8mb4_general_ci,
  `gravida_meses` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fuma_tempo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fuma_cigarros_dia` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bebida_frequencia` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `drogas_uso` text COLLATE utf8mb4_general_ci,
  `doencas_transmissiveis` text COLLATE utf8mb4_general_ci,
  `cancer_familiar` text COLLATE utf8mb4_general_ci,
  `tratamento_cancer` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `termo_consentimento_aceito` tinyint(1) NOT NULL DEFAULT '0',
  `termo_consentimento_aceito_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prontuarios_cpf` (`cpf`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.servicos definição

CREATE TABLE `servicos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `valor_sugerido` decimal(10,2) NOT NULL DEFAULT '0.00',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `data_criacao` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_servicos_ativo` (`ativo`),
  KEY `idx_servicos_nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- dentech.agendamentos definição

CREATE TABLE `agendamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int DEFAULT NULL,
  `paciente_nome` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `procedimento` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `data` date NOT NULL,
  `horario` time NOT NULL,
  `status` enum('agendado','confirmado','cancelado') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'agendado',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.consentimentos definição

CREATE TABLE `consentimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prontuario_id` int NOT NULL,
  `aceito` tinyint(1) NOT NULL DEFAULT '0',
  `data_aceite` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_consentimento_prontuario` (`prontuario_id`),
  CONSTRAINT `fk_consentimento_prontuario` FOREIGN KEY (`prontuario_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- dentech.orcamentos definição

CREATE TABLE `orcamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `data_criacao` date NOT NULL,
  `validade` date NOT NULL,
  `status` enum('pendente','aceito','recusado') COLLATE utf8mb4_general_ci DEFAULT 'pendente',
  `observacoes` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `agendamento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `idx_orcamento_agendamento` (`agendamento_id`),
  CONSTRAINT `fk_orcamento_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.orcamentos_itens definição

CREATE TABLE `orcamentos_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orcamento_id` int NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `valor_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `orcamento_id` (`orcamento_id`),
  CONSTRAINT `orcamentos_itens_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.parcelas definição

CREATE TABLE `parcelas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orcamento_id` int NOT NULL,
  `numero_parcela` tinyint NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('pendente','paga','atrasada') COLLATE utf8mb4_general_ci DEFAULT 'pendente',
  `data_pagamento` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.planos_tratamento definição

CREATE TABLE `planos_tratamento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `status` enum('planejamento','em_andamento','concluido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planejamento',
  `data_criacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_planos_tratamento_paciente` (`paciente_id`),
  KEY `idx_planos_tratamento_status` (`status`),
  CONSTRAINT `fk_planos_tratamento_paciente` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- dentech.planos_tratamento_itens definição

CREATE TABLE `planos_tratamento_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plano_id` int NOT NULL,
  `servico_id` int DEFAULT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dente_regiao` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prioridade` enum('baixa','media','alta') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `valor_estimado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('planejado','em_andamento','concluido','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planejado',
  `ordem` int NOT NULL DEFAULT '0',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_plano_itens_plano` (`plano_id`),
  KEY `idx_plano_itens_servico` (`servico_id`),
  KEY `idx_plano_itens_status` (`status`),
  KEY `idx_plano_itens_ordem` (`plano_id`,`ordem`),
  CONSTRAINT `fk_plano_itens_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos_tratamento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_plano_itens_servico` FOREIGN KEY (`servico_id`) REFERENCES `servicos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- dentech.procedimentos definição

CREATE TABLE `procedimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_general_ci,
  `medicamentos` text COLLATE utf8mb4_general_ci,
  `valor_materiais` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_mao_obra` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_final` decimal(10,2) NOT NULL DEFAULT '0.00',
  `data_procedimento` date NOT NULL,
  `orcamento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `idx_procedimento_orcamento` (`orcamento_id`),
  CONSTRAINT `fk_procedimento_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procedimentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- dentech.lancamentos_financeiros definição

CREATE TABLE `lancamentos_financeiros` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('receita','despesa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data` date NOT NULL,
  `forma_pagamento` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `parcelas` int NOT NULL DEFAULT '1',
  `status` enum('pago','pendente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `observacoes` text COLLATE utf8mb4_unicode_ci,
  `orcamento_id` int DEFAULT NULL,
  `parcela_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `procedimento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lancamento_parcela` (`parcela_id`),
  KEY `idx_data` (`data`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`),
  KEY `idx_orcamento` (`orcamento_id`),
  KEY `idx_lancamento_procedimento` (`procedimento_id`),
  CONSTRAINT `fk_lancamento_procedimento` FOREIGN KEY (`procedimento_id`) REFERENCES `procedimentos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- dentech.procedimento_materiais definição

CREATE TABLE `procedimento_materiais` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedimento_id` int NOT NULL,
  `estoque_id` int NOT NULL,
  `quantidade` decimal(10,2) NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `valor_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedimento` (`procedimento_id`),
  KEY `idx_estoque` (`estoque_id`),
  CONSTRAINT `fk_procedimento_materiais_estoque` FOREIGN KEY (`estoque_id`) REFERENCES `estoque` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_procedimento_materiais_procedimento` FOREIGN KEY (`procedimento_id`) REFERENCES `procedimentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;