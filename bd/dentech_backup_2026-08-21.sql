-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: 163.176.195.96    Database: dentech
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agendamentos`
--

DROP TABLE IF EXISTS `agendamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agendamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int DEFAULT NULL,
  `paciente_nome` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `procedimento` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `data` date NOT NULL,
  `horario` time NOT NULL,
  `status` enum('agendado','confirmado','cancelado','concluido') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'agendado',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `plano_item_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `idx_agendamento_plano_item` (`plano_item_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_agendamento_plano_item` FOREIGN KEY (`plano_item_id`) REFERENCES `planos_tratamento_itens` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agendamentos`
--

LOCK TABLES `agendamentos` WRITE;
/*!40000 ALTER TABLE `agendamentos` DISABLE KEYS */;
INSERT INTO `agendamentos` VALUES (3,NULL,NULL,'teste','2026-08-19','09:00:00','agendado','2026-08-18 13:34:39',NULL),(4,NULL,'teste','teste','2026-08-19','20:23:00','agendado','2026-08-19 13:23:15',NULL),(5,4,NULL,'teste','2026-08-21','12:00:00','confirmado','2026-08-21 14:21:21',NULL),(6,4,'Paula','Consulta','2026-08-27','15:00:00','agendado','2026-08-26 16:37:09',NULL),(13,4,'Paula','Restauração','2026-08-26','15:30:00','concluido','2026-08-26 20:20:21',7),(14,4,'Paula','Limpeza dental','2026-08-26','19:00:00','concluido','2026-08-26 21:10:32',8),(15,5,'Gustavo','Consulta','2026-08-26','20:00:00','concluido','2026-08-26 21:51:22',NULL);
/*!40000 ALTER TABLE `agendamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consentimentos`
--

DROP TABLE IF EXISTS `consentimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consentimentos`
--

LOCK TABLES `consentimentos` WRITE;
/*!40000 ALTER TABLE `consentimentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `consentimentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estoque`
--

DROP TABLE IF EXISTS `estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estoque`
--

LOCK TABLES `estoque` WRITE;
/*!40000 ALTER TABLE `estoque` DISABLE KEYS */;
INSERT INTO `estoque` VALUES (1,'Luva descartável',9.00,'pacote',2.00,15.00,20.00,'2026-08-24 12:09:28'),(2,'Algodão',50.00,'unidade',15.00,1.50,3.00,'2026-08-26 21:21:03');
/*!40000 ALTER TABLE `estoque` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lancamentos_financeiros`
--

DROP TABLE IF EXISTS `lancamentos_financeiros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lancamentos_financeiros`
--

LOCK TABLES `lancamentos_financeiros` WRITE;
/*!40000 ALTER TABLE `lancamentos_financeiros` DISABLE KEYS */;
INSERT INTO `lancamentos_financeiros` VALUES (1,'receita','Orçamento','Orçamento #6 - Parcela 1/4','2026-08-19','A definir',128.00,4,'pago','Receita gerada pelo orçamento #6. Parcela 1/4.',6,10,'2026-08-19 04:12:04',NULL),(2,'receita','Orçamento','Orçamento #6 - Parcela 2/4','2026-10-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 2/4.',6,11,'2026-08-19 04:12:04',NULL),(3,'receita','Orçamento','Orçamento #6 - Parcela 3/4','2026-11-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 3/4.',6,12,'2026-08-19 04:12:04',NULL),(4,'receita','Orçamento','Orçamento #6 - Parcela 4/4','2026-12-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 4/4.',6,13,'2026-08-19 04:12:04',NULL),(5,'receita','Orçamento','Orçamento #7 - Parcela 1/3','2026-09-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 1/3.',7,14,'2026-08-19 04:24:42',NULL),(6,'receita','Orçamento','Orçamento #7 - Parcela 2/3','2026-10-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 2/3.',7,15,'2026-08-19 04:24:42',NULL),(7,'receita','Orçamento','Orçamento #7 - Parcela 3/3','2026-11-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 3/3.',7,16,'2026-08-19 04:24:42',NULL),(8,'receita','Orçamento','Orçamento #9 - Parcela 1/3','2026-09-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #9. Parcela 1/3.',9,20,'2026-08-22 01:43:09',NULL),(9,'receita','Orçamento','Orçamento #9 - Parcela 2/3','2026-10-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #9. Parcela 2/3.',9,21,'2026-08-22 01:43:09',NULL),(10,'receita','Orçamento','Orçamento #9 - Parcela 3/3','2026-11-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #9. Parcela 3/3.',9,22,'2026-08-22 01:43:10',NULL),(11,'receita','Orçamento','Orçamento #10 - Parcela 1/3','2026-09-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #10. Parcela 1/3.',10,23,'2026-08-22 02:08:51',NULL),(12,'receita','Orçamento','Orçamento #10 - Parcela 2/3','2026-10-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #10. Parcela 2/3.',10,24,'2026-08-22 02:08:51',NULL),(13,'receita','Orçamento','Orçamento #10 - Parcela 3/3','2026-11-22','A definir',100.00,3,'pendente','Receita gerada pelo orçamento #10. Parcela 3/3.',10,25,'2026-08-22 02:08:51',NULL),(15,'receita','Ajuste de procedimento','Desconto do procedimento #3 - Restauração','2026-08-24','A definir',-50.00,1,'pago','Desconto gerado pela diferença entre o orçamento #10 (R$ 300,00) e o valor realizado do procedimento (R$ 250,00).',10,NULL,'2026-08-24 12:13:21',3);
/*!40000 ALTER TABLE `lancamentos_financeiros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,'Usuario','Criou prontuário','prontuarios',1,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 13:14:03'),(2,'Usuario','Criou prontuário','prontuarios',2,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 14:01:47'),(3,'Usuario','Criou prontuário','prontuarios',3,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 14:37:58'),(4,'Usuario','Criou agendamento','agendamentos',1,'Novo agendamento criado','127.0.0.1','2026-08-18 02:12:08'),(5,'Usuario','Excluiu agendamento','agendamentos',1,'Agendamento removido','127.0.0.1','2026-08-18 02:25:55'),(6,'Usuario','Criou agendamento','agendamentos',2,'Novo agendamento criado','127.0.0.1','2026-08-18 02:26:46'),(7,'Usuario','Recusou orçamento','orcamentos',1,'Orçamento recusado','127.0.0.1','2026-08-18 11:52:39'),(8,'Usuario','Aceitou orçamento','orcamentos',2,'Orçamento aprovado e processo de agendamento iniciado','127.0.0.1','2026-08-18 13:34:39'),(9,'Usuario','Recusou orçamento','orcamentos',3,'Orçamento recusado','127.0.0.1','2026-08-18 15:46:05'),(10,'Usuario','Recusou orçamento','orcamentos',4,'Orçamento recusado','127.0.0.1','2026-08-19 04:11:02'),(11,'Usuario','Criou agendamento','agendamentos',4,'Novo agendamento criado','127.0.0.1','2026-08-19 13:23:15'),(12,'Usuario','Criou prontuário','prontuarios',4,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-21 11:36:17'),(13,'Usuario','Criou agendamento','agendamentos',5,'Novo agendamento criado','127.0.0.1','2026-08-21 14:21:22'),(14,'Usuario','Recusou orçamento','orcamentos',11,'Orçamento recusado','127.0.0.1','2026-08-25 13:10:17'),(15,'Usuario','Recusou orçamento','orcamentos',12,'Orçamento recusado','127.0.0.1','2026-08-25 13:10:28'),(16,'Usuario','Criou prontuário','prontuarios',5,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-26 16:41:05'),(17,'Usuario','Excluiu agendamento','agendamentos',8,'Agendamento removido','127.0.0.1','2026-08-26 16:46:31'),(18,'Usuario','Excluiu agendamento','agendamentos',11,'Agendamento removido','127.0.0.1','2026-08-26 19:25:26'),(19,'Usuario','Excluiu agendamento','agendamentos',9,'Agendamento removido','127.0.0.1','2026-08-26 20:12:42'),(20,'Usuario','Excluiu agendamento','agendamentos',12,'Agendamento removido','127.0.0.1','2026-08-26 20:12:47'),(21,'Usuario','Excluiu agendamento','agendamentos',7,'Agendamento removido','127.0.0.1','2026-08-26 20:12:54'),(22,'Usuario','Excluiu agendamento','agendamentos',10,'Agendamento removido','127.0.0.1','2026-08-26 20:12:59');
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orcamentos`
--

DROP TABLE IF EXISTS `orcamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orcamentos`
--

LOCK TABLES `orcamentos` WRITE;
/*!40000 ALTER TABLE `orcamentos` DISABLE KEYS */;
INSERT INTO `orcamentos` VALUES (9,4,'2026-08-22','2026-09-21','aceito','','2026-08-22 01:41:48',NULL),(10,4,'2026-08-22','2026-09-21','aceito','','2026-08-22 02:08:41',5),(11,4,'2026-08-24','2026-09-23','recusado','','2026-08-24 12:50:39',NULL),(12,4,'2026-08-24','2026-09-23','recusado','','2026-08-24 13:02:47',NULL),(13,4,'2026-08-24','2026-09-23','pendente','','2026-08-24 14:53:09',NULL),(14,4,'2026-08-25','2026-09-24','pendente','','2026-08-25 13:10:42',NULL),(15,4,'2026-08-26','2026-09-25','pendente','','2026-08-26 12:59:17',NULL);
/*!40000 ALTER TABLE `orcamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orcamentos_itens`
--

DROP TABLE IF EXISTS `orcamentos_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamentos_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orcamento_id` int NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `quantidade` int NOT NULL DEFAULT '1',
  `valor_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `orcamento_id` (`orcamento_id`),
  CONSTRAINT `orcamentos_itens_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orcamentos_itens`
--

LOCK TABLES `orcamentos_itens` WRITE;
/*!40000 ALTER TABLE `orcamentos_itens` DISABLE KEYS */;
INSERT INTO `orcamentos_itens` VALUES (9,9,'teste',1,300.00),(10,10,'teste',1,300.00),(11,11,'Clareamento',1,300.00),(12,12,'Restauração 02',1,300.00),(13,13,'Clareamento dental',1,800.00),(14,13,'Limpeza dental',1,150.00),(15,14,'Limpeza dental',1,150.00),(16,15,'Restauração',1,450.00),(17,15,'Limpeza dental',1,150.00);
/*!40000 ALTER TABLE `orcamentos_itens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcelas`
--

DROP TABLE IF EXISTS `parcelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcelas`
--

LOCK TABLES `parcelas` WRITE;
/*!40000 ALTER TABLE `parcelas` DISABLE KEYS */;
INSERT INTO `parcelas` VALUES (20,9,1,100.00,'2026-09-22','pendente',NULL),(21,9,2,100.00,'2026-10-22','pendente',NULL),(22,9,3,100.00,'2026-11-22','pendente',NULL),(23,10,1,100.00,'2026-09-22','pendente',NULL),(24,10,2,100.00,'2026-10-22','pendente',NULL),(25,10,3,100.00,'2026-11-22','pendente',NULL),(26,11,1,300.00,'2026-09-24','pendente',NULL),(27,12,1,300.00,'2026-09-24','pendente',NULL),(28,13,1,950.00,'2026-09-24','pendente',NULL),(29,14,1,150.00,'2026-09-25','pendente',NULL),(30,15,1,600.00,'2026-09-26','pendente',NULL);
/*!40000 ALTER TABLE `parcelas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_tratamento`
--

DROP TABLE IF EXISTS `planos_tratamento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_tratamento`
--

LOCK TABLES `planos_tratamento` WRITE;
/*!40000 ALTER TABLE `planos_tratamento` DISABLE KEYS */;
INSERT INTO `planos_tratamento` VALUES (1,4,'Plano de Reabilitação Oral','Planejamento inicial do tratamento do paciente','concluido','2026-08-25 14:20:23','2026-08-26 21:32:19');
/*!40000 ALTER TABLE `planos_tratamento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_tratamento_itens`
--

DROP TABLE IF EXISTS `planos_tratamento_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_tratamento_itens`
--

LOCK TABLES `planos_tratamento_itens` WRITE;
/*!40000 ALTER TABLE `planos_tratamento_itens` DISABLE KEYS */;
INSERT INTO `planos_tratamento_itens` VALUES (7,1,1,'Restauração','16','alta',400.00,'concluido',1,'Avaliar necessidade de restauração adicional'),(8,1,2,'Profilaxia','Arcada completa','media',150.00,'concluido',2,NULL);
/*!40000 ALTER TABLE `planos_tratamento_itens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planos_tratamento_itens_orcamentos`
--

DROP TABLE IF EXISTS `planos_tratamento_itens_orcamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `planos_tratamento_itens_orcamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plano_item_id` int NOT NULL,
  `orcamento_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plano_item_orcamento` (`plano_item_id`,`orcamento_id`),
  KEY `idx_ptio_plano_item` (`plano_item_id`),
  KEY `idx_ptio_orcamento` (`orcamento_id`),
  CONSTRAINT `fk_ptio_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ptio_plano_item` FOREIGN KEY (`plano_item_id`) REFERENCES `planos_tratamento_itens` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planos_tratamento_itens_orcamentos`
--

LOCK TABLES `planos_tratamento_itens_orcamentos` WRITE;
/*!40000 ALTER TABLE `planos_tratamento_itens_orcamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `planos_tratamento_itens_orcamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `procedimento_materiais`
--

DROP TABLE IF EXISTS `procedimento_materiais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `procedimento_materiais`
--

LOCK TABLES `procedimento_materiais` WRITE;
/*!40000 ALTER TABLE `procedimento_materiais` DISABLE KEYS */;
INSERT INTO `procedimento_materiais` VALUES (4,3,1,1.00,15.00,15.00,'2026-08-24 12:09:28');
/*!40000 ALTER TABLE `procedimento_materiais` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `procedimentos`
--

DROP TABLE IF EXISTS `procedimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  `plano_item_id` int DEFAULT NULL,
  `agendamento_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `idx_procedimento_orcamento` (`orcamento_id`),
  KEY `idx_procedimento_plano_item` (`plano_item_id`),
  KEY `idx_procedimento_agendamento` (`agendamento_id`),
  CONSTRAINT `fk_procedimento_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_procedimento_orcamento` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_procedimento_plano_item` FOREIGN KEY (`plano_item_id`) REFERENCES `planos_tratamento_itens` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `procedimentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `procedimentos`
--

LOCK TABLES `procedimentos` WRITE;
/*!40000 ALTER TABLE `procedimentos` DISABLE KEYS */;
INSERT INTO `procedimentos` VALUES (3,4,'Restauração','Teste de procedimento',NULL,15.00,130.00,250.00,'2026-08-21',10,NULL,NULL),(5,4,'Restauração',NULL,NULL,0.00,0.00,0.00,'2026-08-26',NULL,7,13),(6,4,'Restauração',NULL,NULL,0.00,0.00,0.00,'2026-08-26',NULL,7,13),(7,4,'Restauração',NULL,NULL,0.00,0.00,0.00,'2026-08-26',NULL,7,13),(8,4,'Restauração',NULL,NULL,0.00,0.00,0.00,'2026-08-26',NULL,7,13),(9,4,'Limpeza dental','Avaliação clínica e procedimento de teste realizado com sucesso.','Nenhum medicamento utilizado.',0.00,0.00,0.00,'2026-08-26',NULL,8,14),(10,5,'Consulta','Teste de atendimento realizado às 20 horas.','Nenhum medicamento utilizado.',0.00,0.00,0.00,'2026-08-26',NULL,NULL,15);
/*!40000 ALTER TABLE `procedimentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prontuarios`
--

DROP TABLE IF EXISTS `prontuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prontuarios`
--

LOCK TABLES `prontuarios` WRITE;
/*!40000 ALTER TABLE `prontuarios` DISABLE KEYS */;
INSERT INTO `prontuarios` VALUES (4,'Paula','2001-12-05','Feminino','','','',NULL,'','','(18) 98800-1498','','','','','','','','','','','','','','','','','2026-08-21 11:36:17',1,'2026-08-21 11:36:22'),(5,'Gustavo','1999-02-15','Masculino','','','',NULL,'','','','','','','','','','','','','','','','','','','','2026-08-26 16:41:05',1,'2026-08-26 16:41:10');
/*!40000 ALTER TABLE `prontuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicos`
--

DROP TABLE IF EXISTS `servicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicos`
--

LOCK TABLES `servicos` WRITE;
/*!40000 ALTER TABLE `servicos` DISABLE KEYS */;
INSERT INTO `servicos` VALUES (1,'Restauração','Restauração dentária',500.00,1,'2026-08-24'),(2,'Limpeza dental','Profilaxia',200.00,1,'2026-08-24'),(3,'Clareamento dental','Clareamento odontológico',800.00,1,'2026-08-24'),(4,'Restauracao 02',NULL,350.00,1,'2026-08-24');
/*!40000 ALTER TABLE `servicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'dentech'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-28 12:22:25
