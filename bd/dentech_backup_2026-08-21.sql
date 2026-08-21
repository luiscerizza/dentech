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

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agendamentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int DEFAULT NULL,
  `paciente_nome` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `procedimento` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `data` date NOT NULL,
  `horario` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agendamentos`
--

LOCK TABLES `agendamentos` WRITE;
/*!40000 ALTER TABLE `agendamentos` DISABLE KEYS */;
INSERT INTO `agendamentos` VALUES (3,NULL,NULL,'teste','2026-08-19','09:00:00','2026-08-18 13:34:39'),(4,NULL,'teste','teste','2026-08-19','20:23:00','2026-08-19 13:23:15');
/*!40000 ALTER TABLE `agendamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consentimentos`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estoque`
--

LOCK TABLES `estoque` WRITE;
/*!40000 ALTER TABLE `estoque` DISABLE KEYS */;
INSERT INTO `estoque` VALUES (1,'Luva descartável',10.00,'pacote',2.00,15.00,20.00,'2026-08-21 13:21:40');
/*!40000 ALTER TABLE `estoque` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lancamentos_financeiros`
--

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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lancamento_parcela` (`parcela_id`),
  KEY `idx_data` (`data`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`),
  KEY `idx_orcamento` (`orcamento_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lancamentos_financeiros`
--

LOCK TABLES `lancamentos_financeiros` WRITE;
/*!40000 ALTER TABLE `lancamentos_financeiros` DISABLE KEYS */;
INSERT INTO `lancamentos_financeiros` VALUES (1,'receita','Orçamento','Orçamento #6 - Parcela 1/4','2026-08-19','A definir',128.00,4,'pago','Receita gerada pelo orçamento #6. Parcela 1/4.',6,10,'2026-08-19 04:12:04'),(2,'receita','Orçamento','Orçamento #6 - Parcela 2/4','2026-10-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 2/4.',6,11,'2026-08-19 04:12:04'),(3,'receita','Orçamento','Orçamento #6 - Parcela 3/4','2026-11-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 3/4.',6,12,'2026-08-19 04:12:04'),(4,'receita','Orçamento','Orçamento #6 - Parcela 4/4','2026-12-19','A definir',128.00,4,'pendente','Receita gerada pelo orçamento #6. Parcela 4/4.',6,13,'2026-08-19 04:12:04'),(5,'receita','Orçamento','Orçamento #7 - Parcela 1/3','2026-09-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 1/3.',7,14,'2026-08-19 04:24:42'),(6,'receita','Orçamento','Orçamento #7 - Parcela 2/3','2026-10-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 2/3.',7,15,'2026-08-19 04:24:42'),(7,'receita','Orçamento','Orçamento #7 - Parcela 3/3','2026-11-19','A definir',50.00,3,'pendente','Receita gerada pelo orçamento #7. Parcela 3/3.',7,16,'2026-08-19 04:24:42');
/*!40000 ALTER TABLE `lancamentos_financeiros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
INSERT INTO `logs` VALUES (1,'Usuario','Criou prontuário','prontuarios',1,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 13:14:03'),(2,'Usuario','Criou prontuário','prontuarios',2,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 14:01:47'),(3,'Usuario','Criou prontuário','prontuarios',3,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-17 14:37:58'),(4,'Usuario','Criou agendamento','agendamentos',1,'Novo agendamento criado','127.0.0.1','2026-08-18 02:12:08'),(5,'Usuario','Excluiu agendamento','agendamentos',1,'Agendamento removido','127.0.0.1','2026-08-18 02:25:55'),(6,'Usuario','Criou agendamento','agendamentos',2,'Novo agendamento criado','127.0.0.1','2026-08-18 02:26:46'),(7,'Usuario','Recusou orçamento','orcamentos',1,'Orçamento recusado','127.0.0.1','2026-08-18 11:52:39'),(8,'Usuario','Aceitou orçamento','orcamentos',2,'Orçamento aprovado e processo de agendamento iniciado','127.0.0.1','2026-08-18 13:34:39'),(9,'Usuario','Recusou orçamento','orcamentos',3,'Orçamento recusado','127.0.0.1','2026-08-18 15:46:05'),(10,'Usuario','Recusou orçamento','orcamentos',4,'Orçamento recusado','127.0.0.1','2026-08-19 04:11:02'),(11,'Usuario','Criou agendamento','agendamentos',4,'Novo agendamento criado','127.0.0.1','2026-08-19 13:23:15'),(12,'Usuario','Criou prontuário','prontuarios',4,'Novo paciente cadastrado no prontuário','127.0.0.1','2026-08-21 11:36:17');
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orcamentos`
--

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
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orcamentos`
--

LOCK TABLES `orcamentos` WRITE;
/*!40000 ALTER TABLE `orcamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `orcamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orcamentos_itens`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orcamentos_itens`
--

LOCK TABLES `orcamentos_itens` WRITE;
/*!40000 ALTER TABLE `orcamentos_itens` DISABLE KEYS */;
/*!40000 ALTER TABLE `orcamentos_itens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcelas`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcelas`
--

LOCK TABLES `parcelas` WRITE;
/*!40000 ALTER TABLE `parcelas` DISABLE KEYS */;
/*!40000 ALTER TABLE `parcelas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `procedimentos`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procedimentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_general_ci,
  `medicamentos` text COLLATE utf8mb4_general_ci,
  `data_procedimento` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `procedimentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `procedimentos`
--

LOCK TABLES `procedimentos` WRITE;
/*!40000 ALTER TABLE `procedimentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `procedimentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prontuarios`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prontuarios`
--

LOCK TABLES `prontuarios` WRITE;
/*!40000 ALTER TABLE `prontuarios` DISABLE KEYS */;
INSERT INTO `prontuarios` VALUES (4,'Teste','2001-12-05','Masculino','','','',NULL,'','','','','','','','','','','','','','','','','','','','2026-08-21 11:36:17',1,'2026-08-21 11:36:22');
/*!40000 ALTER TABLE `prontuarios` ENABLE KEYS */;
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

-- Dump completed on 2026-08-21 10:44:32
