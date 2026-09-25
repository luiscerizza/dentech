-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: dentech_teste
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

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
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) DEFAULT NULL,
  `paciente_nome` varchar(255) DEFAULT NULL,
  `procedimento` varchar(255) NOT NULL,
  `data` date NOT NULL,
  `horario` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agendamentos`
--

LOCK TABLES `agendamentos` WRITE;
/*!40000 ALTER TABLE `agendamentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `agendamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estoque`
--

DROP TABLE IF EXISTS `estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estoque` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `quantidade` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unidade` varchar(50) NOT NULL DEFAULT 'unidade',
  `estoque_minimo` decimal(10,2) NOT NULL DEFAULT 5.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estoque`
--

LOCK TABLES `estoque` WRITE;
/*!40000 ALTER TABLE `estoque` DISABLE KEYS */;
/*!40000 ALTER TABLE `estoque` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) DEFAULT 'Sistema',
  `acao` varchar(50) NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orcamentos`
--

DROP TABLE IF EXISTS `orcamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `data_criacao` date NOT NULL,
  `validade` date NOT NULL,
  `status` enum('pendente','aceito','recusado') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

DROP TABLE IF EXISTS `orcamentos_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `orcamentos_itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `valor_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `orcamento_id` (`orcamento_id`),
  CONSTRAINT `orcamentos_itens_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

DROP TABLE IF EXISTS `parcelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcelas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orcamento_id` int(11) NOT NULL,
  `numero_parcela` tinyint(4) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('pendente','paga','atrasada') DEFAULT 'pendente',
  `data_pagamento` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orcamento` (`orcamento_id`),
  CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

DROP TABLE IF EXISTS `procedimentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procedimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `medicamentos` text DEFAULT NULL,
  `data_procedimento` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  CONSTRAINT `procedimentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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

DROP TABLE IF EXISTS `prontuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prontuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente` varchar(255) NOT NULL,
  `nascimento` date NOT NULL,
  `sexo` enum('Masculino','Feminino','Outro') DEFAULT NULL,
  `estado_civil` varchar(50) DEFAULT NULL,
  `profissao` varchar(100) DEFAULT NULL,
  `rg` varchar(20) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `endereco` text DEFAULT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `tratamento_odonto` text DEFAULT NULL,
  `tratamento_medico` text DEFAULT NULL,
  `medicamento_continuo` text DEFAULT NULL,
  `alergia_medicamento` text DEFAULT NULL,
  `alergia_outras` text DEFAULT NULL,
  `problemas_saude` text DEFAULT NULL,
  `gravida_meses` varchar(10) DEFAULT NULL,
  `fuma_tempo` varchar(50) DEFAULT NULL,
  `fuma_cigarros_dia` varchar(20) DEFAULT NULL,
  `bebida_frequencia` varchar(100) DEFAULT NULL,
  `drogas_uso` text DEFAULT NULL,
  `doencas_transmissiveis` text DEFAULT NULL,
  `cancer_familiar` text DEFAULT NULL,
  `tratamento_cancer` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prontuarios`
--

LOCK TABLES `prontuarios` WRITE;
/*!40000 ALTER TABLE `prontuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `prontuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'dentech_teste'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-25 10:30:50
