-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 07-Ago-2026 às 16:05
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `dentech`
--
-- --------------------------------------------------------

--
-- Estrutura da tabela `agendamentos`
--

CREATE TABLE `agendamentos` (
  `id` int(11),
  `paciente_id` int(11) DEFAULT NULL,
  `paciente_nome` varchar(255) DEFAULT NULL,
  `procedimento` varchar(255) NOT NULL,
  `data` date NOT NULL,
  `horario` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `agendamentos`
--

INSERT INTO `agendamentos` (`id`, `paciente_id`, `paciente_nome`, `procedimento`, `data`, `horario`, `created_at`) VALUES
(4, NULL, 'Reunião Porto Seguro', 'reuniao', '2026-05-29', '09:00:00', '2026-05-26 17:34:53'),
(7, NULL, 'Teresinha', 'Consulta Laluce', '2026-05-30', '09:45:00', '2026-05-26 17:36:44'),
(8, NULL, 'Gabi', 'restauração 46/36 e limpeza', '2026-05-30', '10:15:00', '2026-05-26 17:37:06'),
(9, NULL, 'Clicéria', 'moldagem para clareamento e bruxismo', '2026-05-30', '11:15:00', '2026-05-26 17:37:27'),
(12, 73, NULL, 'Raspagem e polimento', '2026-05-28', '09:00:00', '2026-05-27 16:43:06'),
(13, 74, NULL, 'Raspagem e polimento', '2026-05-28', '09:00:00', '2026-05-27 16:50:18'),
(14, 75, NULL, 'Raspagem e polimento', '2026-05-28', '09:00:00', '2026-05-27 16:55:58'),
(15, 73, NULL, 'PREPARO E MOLDAGEM', '2026-05-29', '16:30:00', '2026-05-27 16:58:05'),
(16, 75, NULL, 'RASPAGEM E POLIMENTO', '2026-06-01', '16:30:00', '2026-05-27 16:59:00'),
(17, 64, NULL, 'Restaurações (7)', '2026-05-28', '09:00:00', '2026-05-27 17:04:21'),
(18, 94, NULL, 'Raspagem e polimento', '2026-06-13', '09:00:00', '2026-06-12 18:10:19'),
(19, 95, NULL, 'Raspagem e polimento', '2026-06-13', '09:00:00', '2026-06-12 18:12:46'),
(20, 94, NULL, 'Restaurações (2)', '2026-06-13', '09:00:00', '2026-06-12 19:01:22'),
(22, 96, NULL, 'Raspagem e polimento', '2026-06-13', '09:00:00', '2026-06-12 19:50:22'),
(24, NULL, 'Ana Farelli', 'provar coping', '2026-06-16', '11:00:00', '2026-06-15 18:33:03'),
(25, NULL, 'Lara Palermo', 'consulta', '2026-06-17', '17:15:00', '2026-06-15 18:33:35'),
(29, NULL, 'Rafael Pandini', 'consulta', '2026-06-16', '10:00:00', '2026-06-15 18:39:22'),
(30, 75, NULL, 'pino de fibra de vidro + preparo+ provisório', '2026-06-22', '16:20:00', '2026-06-15 18:41:18'),
(32, 70, NULL, 'Implante com Dra Lara', '2026-07-16', '16:30:00', '2026-06-15 18:43:47'),
(33, 64, NULL, 'extração', '2026-06-23', '10:30:00', '2026-06-17 14:49:09'),
(34, 64, NULL, 'Endo 47 Dr Bruno', '2026-06-25', '10:30:00', '2026-06-17 14:49:47'),
(35, 97, NULL, 'RESTAURAÇÃO', '2026-06-18', '10:00:00', '2026-06-17 14:50:30'),
(36, NULL, 'Rafael Pandini', 'Raspagem e moldagem para clareamento', '2026-06-19', '10:00:00', '2026-06-17 14:51:35'),
(37, 97, NULL, 'Raspagem e polimento', '2026-06-18', '09:00:00', '2026-06-17 18:35:25'),
(38, 63, NULL, 'clareamento', '2026-06-18', '09:00:00', '2026-06-17 18:39:46'),
(39, 123, NULL, 'Restaurações', '2026-06-19', '09:00:00', '2026-06-18 12:52:30'),
(40, NULL, NULL, 'Raspagem e polimento', '2026-06-20', '09:00:00', '2026-06-19 18:01:05'),
(42, NULL, 'Taina Marques Abreu', 'consulta e raspagem - PORTO SEGURO', '2026-06-24', '17:10:00', '2026-06-19 18:03:18'),
(43, NULL, 'Taina Marques Abreu', 'Tratamento Porto Seguro', '2026-06-27', '13:30:00', '2026-06-19 18:04:09'),
(44, NULL, 'Raquel', 'Consulta  particular', '2026-06-23', '09:30:00', '2026-06-22 14:06:42'),
(45, NULL, 'Jéssica Cristina Lemos Motta', 'Consulta  particular', '2026-08-08', '10:30:00', '2026-06-22 14:15:36'),
(52, NULL, 'Flávio de Alessandra', 'moldar', '2026-06-26', '15:30:00', '2026-06-25 19:46:12'),
(53, 223, NULL, 'clareamento', '2026-06-28', '09:00:00', '2026-06-27 14:30:34'),
(56, NULL, 'Renatinha', 'limpeza', '2026-07-01', '15:00:00', '2026-06-27 14:32:48'),
(57, NULL, 'Jaqueline Mendes', 'Consulta  particular', '2026-06-30', '15:00:00', '2026-06-27 14:33:08'),
(60, 255, NULL, 'raspagem , restauração', '2026-07-04', '14:00:00', '2026-06-29 14:11:09'),
(61, 223, NULL, 'Clareamento', '2026-04-03', '10:00:00', '2026-06-29 14:12:03'),
(62, 256, NULL, 'consulta', '2026-06-29', '11:30:00', '2026-06-29 14:31:06'),
(66, 62, NULL, 'Inicio', '2026-07-01', '10:30:00', '2026-06-30 16:13:03'),
(67, 73, NULL, 'provar coping', '2026-07-02', '15:30:00', '2026-06-30 16:15:06'),
(68, 144, NULL, 'caiu dente', '2026-07-02', '16:30:00', '2026-06-30 16:15:19'),
(69, 105, NULL, 'prova e registro', '2026-07-02', '16:30:00', '2026-06-30 16:15:34'),
(71, 223, NULL, 'clareamento', '2026-06-03', '11:00:00', '2026-06-30 16:16:59'),
(75, NULL, 'JAQUELINE MENDES', 'endo com DR Bruno', '2026-07-02', '17:30:00', '2026-07-02 13:57:10'),
(80, NULL, 'PRESCILA VELA', 'AVAL E RAIO X', '2026-07-06', '10:00:00', '2026-07-02 17:07:15'),
(82, NULL, 'Renatinha', 'Clareamento', '2026-07-03', '16:30:00', '2026-07-03 11:30:53'),
(84, NULL, 'Jaqueline Mendes', 'dr Bruno', '2026-07-16', '08:00:00', '2026-07-03 11:39:29'),
(88, 101, NULL, 'limpeza + verniz', '2026-07-05', '09:00:00', '2026-07-04 14:20:27'),
(94, 259, NULL, 'pino + reconstrução + aumento de coroa', '2026-07-07', '16:00:00', '2026-07-06 13:20:38'),
(97, 255, NULL, 'limpeza+restauração+ exo+ modagem', '2026-07-09', '10:30:00', '2026-07-07 18:38:34'),
(98, 54, NULL, 'extração deciduo', '2026-07-09', '09:00:00', '2026-07-08 20:24:19'),
(102, NULL, 'ANNE', 'RESTAURAÇÃO', '2026-07-15', '10:00:00', '2026-07-08 20:27:06'),
(103, 255, NULL, 'LIMPEZA + RESTAURAÇÃO + MOLDAGEM + EXO', '2026-07-18', '14:00:00', '2026-07-08 20:28:00'),
(108, 141, NULL, 'consulta', '2026-07-15', '15:00:00', '2026-07-14 21:15:25'),
(109, NULL, 'Renata', 'consulta', '2026-07-17', '18:00:00', '2026-07-14 21:15:52'),
(111, 64, NULL, 'onlay', '2026-07-16', '09:00:00', '2026-07-15 14:37:32'),
(112, 141, NULL, 'Pino de fibra de vidro + coroa Emax', '2026-07-16', '09:00:00', '2026-07-15 20:29:40'),
(117, 259, NULL, 'coroa + pino + aumento de coroa', '2026-07-19', '09:00:00', '2026-07-18 15:19:15'),
(120, 62, NULL, 'RESTAURAÇÃO', '2026-07-20', '14:15:00', '2026-07-18 15:25:29'),
(127, 265, NULL, 'cimentar onlay', '2026-07-23', '15:00:00', '2026-07-21 20:00:45'),
(128, NULL, 'val', 'moldar', '2026-07-22', '16:00:00', '2026-07-21 20:01:36'),
(129, 260, NULL, 'clareamento', '2026-07-22', '09:00:00', '2026-07-21 20:06:52'),
(130, 255, NULL, 'PPR flex unilatera', '2026-07-22', '09:00:00', '2026-07-21 20:07:23'),
(132, 268, NULL, 'RESTAURAÇÃO', '2026-07-25', '10:00:00', '2026-07-25 12:55:10'),
(133, 264, NULL, 'clareamento', '2026-07-29', '09:00:00', '2026-07-28 18:25:41'),
(139, NULL, 'Renata', 'Clareamento', '2026-07-30', '16:30:00', '2026-07-30 14:51:20'),
(140, NULL, 'Vilma stivanelli', 'consulta', '2026-07-30', '17:00:00', '2026-07-30 14:51:34'),
(141, 70, NULL, 'Pós Operatório', '2026-07-31', '09:30:00', '2026-07-30 14:52:12'),
(142, 75, NULL, 'pino + preparo para coroa', '2026-07-31', '16:30:00', '2026-07-30 14:52:31'),
(143, NULL, 'Elizabeth', 'Conserto Protocolo', '2026-07-31', '18:00:00', '2026-07-31 12:28:19'),
(144, 273, NULL, 'Prótese total provisória', '2026-08-06', '09:00:00', '2026-08-05 18:01:25');

-- --------------------------------------------------------

--
-- Estrutura da tabela `estoque`
--

CREATE TABLE `estoque` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `quantidade` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unidade` varchar(50) NOT NULL DEFAULT 'unidade',
  `estoque_minimo` decimal(10,2) NOT NULL DEFAULT 5.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `estoque`
--

INSERT INTO `estoque` (`id`, `nome`, `quantidade`, `unidade`, `estoque_minimo`, `updated_at`) VALUES
(1, 'luvas', 0.00, 'unidade', 5.00, '2026-06-23 19:18:36'),
(2, 'Anestésico Mepivacaína', 50.00, 'unidade', 20.00, '2026-05-26 18:32:19'),
(3, 'Anestésico Articaína', 50.00, 'unidade', 20.00, '2026-05-26 18:32:52'),
(4, 'Anestésico Lidocaína', 100.00, 'unidade', 40.00, '2026-05-26 18:33:12'),
(5, 'Agulha Curta', 100.00, 'unidade', 30.00, '2026-05-26 18:33:52'),
(6, 'Lâmina 15', 30.00, 'unidade', 10.00, '2026-05-26 18:34:23'),
(7, 'Babador Descartavel', 0.00, 'pacote', 1.00, '2026-06-23 19:18:26'),
(8, 'Sugador Descartável', 0.00, 'pacote', 1.00, '2026-06-23 19:23:00'),
(9, 'Rolete de Algodão', 0.00, 'pacote', 1.00, '2026-06-23 19:22:54'),
(10, 'Gaze', 1.00, 'pacote', 1.00, '2026-06-23 19:23:07'),
(11, 'Papel carbobo simples', 0.00, 'unidade', 1.00, '2026-06-23 19:24:00'),
(12, 'Mascara', 0.00, 'pacote', 1.00, '2026-06-23 19:28:36'),
(13, 'Aplicador Microbrush Regular', 0.00, 'frasco', 1.00, '2026-06-23 19:28:25'),
(14, 'Aplicador Microbrush Fino', 3.00, 'pacote', 1.00, '2026-06-25 18:35:10'),
(15, 'Luva Estéril 6,5', 10.00, 'unidade', 5.00, '2026-06-23 19:30:38'),
(16, 'Gaze estéril', 1.00, 'pacote', 5.00, '2026-06-23 19:40:08'),
(17, 'Kit Cirurgico Completo', 1.00, 'unidade', 2.00, '2026-06-23 19:41:00'),
(18, 'Gorro Descartável', 1.00, 'pacote', 1.00, '2026-06-23 19:41:53'),
(19, 'Coletor Descarpak', 1.00, 'unidade', 1.00, '2026-06-23 19:43:35'),
(20, 'Sugador Cirurgico', 20.00, 'unidade', 5.00, '2026-06-23 19:44:11'),
(21, 'Seringa Descartável', 0.00, 'unidade', 2.00, '2026-06-23 19:44:50'),
(22, 'Campo descartável mesa auxiliar', 0.00, 'pacote', 1.00, '2026-06-23 19:46:38'),
(23, 'Propé', 0.00, 'pacote', 1.00, '2026-06-23 19:47:39'),
(24, 'Cotonete', 2.00, 'unidade', 1.00, '2026-06-23 19:55:50'),
(25, 'Algodao', 0.00, 'pacote', 5.00, '2026-06-27 14:20:06'),
(26, 'Lubrificante', 2.00, 'frasco', 1.00, '2026-06-23 19:56:22'),
(27, 'Água Destilada', 2.00, 'frasco', 1.00, '2026-06-23 19:57:25'),
(28, 'Alcool 70', 2.00, 'frasco', 1.00, '2026-06-23 19:57:41'),
(29, 'Àgua Oxigenada 10vol', 2.00, 'frasco', 1.00, '2026-06-23 19:59:52'),
(30, 'Periogard', 1.00, 'frasco', 1.00, '2026-06-23 20:00:09'),
(31, 'Detergtente enzimático', 1.00, 'frasco', 1.00, '2026-06-23 20:02:25');

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) DEFAULT 'Sistema',
  `acao` varchar(50) NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `logs`
--

INSERT INTO `logs` (`id`, `usuario`, `acao`, `tabela`, `registro_id`, `detalhes`, `ip`, `created_at`) VALUES
(1, 'Admin', 'Acesso', 'Area_Restrita', NULL, 'Usuário acessou o painel administrativo', '::1', '2026-05-23 18:11:10');

-- --------------------------------------------------------

--
-- Estrutura da tabela `orcamentos`
--

CREATE TABLE `orcamentos` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `data_criacao` date NOT NULL,
  `validade` date NOT NULL,
  `status` enum('pendente','aceito','recusado') DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `orcamentos`
--

INSERT INTO `orcamentos` (`id`, `paciente_id`, `data_criacao`, `validade`, `status`, `observacoes`, `created_at`) VALUES
(2, 73, '2026-05-27', '2026-06-26', 'aceito', '', '2026-05-27 16:41:56'),
(3, 74, '2026-05-27', '2026-06-26', 'aceito', '', '2026-05-27 16:50:02'),
(4, 75, '2026-05-27', '2026-06-26', 'aceito', 'Valores com desconto pelo convênio Laluce. Valor cheio $ 1200,00. Valor com Desconto $955,00', '2026-05-27 16:55:39'),
(5, 64, '2026-05-27', '2026-06-26', 'aceito', 'RESTANTE EM ABERTO ATE 27/05/2026 R$741,00', '2026-05-27 17:03:55'),
(6, 94, '2026-06-12', '2026-07-12', 'aceito', 'PAGAMENTO CARTÃO DE CRÉDITO', '2026-06-12 18:07:51'),
(7, 95, '2026-06-12', '2026-07-12', 'aceito', '', '2026-06-12 18:12:36'),
(8, 94, '2026-06-12', '2026-07-12', 'aceito', 'EM ABERTO PARA PAGAMENTO PARCELADO VIA PIX', '2026-06-12 19:01:07'),
(9, 95, '2026-06-12', '2026-07-12', 'aceito', 'EM ABERTO PARA PAGAMENTO EM PIX/ DÉBITO\r\n15/06 PAGOU 250,00 DÉBITO', '2026-06-12 19:12:32'),
(10, 96, '2026-06-12', '2026-07-12', 'aceito', 'Rasapagem Ro e Bruno\r\nPagamento via pix', '2026-06-12 19:50:00'),
(11, 96, '2026-06-12', '2026-07-20', 'pendente', '', '2026-06-12 20:30:01'),
(13, 97, '2026-06-17', '2026-06-17', 'aceito', '', '2026-06-17 18:34:40'),
(14, 63, '2026-06-17', '2026-06-16', 'aceito', '', '2026-06-17 18:37:21'),
(15, 123, '2026-06-18', '2026-07-18', 'aceito', '', '2026-06-18 12:52:13'),
(16, 64, '2026-06-23', '2026-07-23', 'aceito', '', '2026-06-23 18:16:03'),
(17, 143, '2026-06-23', '2026-07-23', 'aceito', '', '2026-06-23 18:50:25'),
(19, 223, '2026-06-27', '2026-07-27', 'aceito', '', '2026-06-27 14:30:17'),
(20, 185, '2026-07-01', '2026-07-31', 'aceito', '', '2026-07-01 13:06:09'),
(21, 258, '2026-07-03', '2026-08-02', 'aceito', 'DÉBITO\r\nVALOR LÍQUIDO $631,19', '2026-07-03 13:32:18'),
(22, 111, '2026-07-03', '2026-08-02', 'aceito', '', '2026-07-03 20:19:22'),
(23, 101, '2026-07-04', '2026-08-03', 'aceito', '', '2026-07-04 14:20:15'),
(24, 259, '2026-07-06', '2026-08-05', 'aceito', '', '2026-07-06 13:21:57'),
(25, 54, '2026-07-08', '2026-08-07', 'aceito', '', '2026-07-08 20:24:12'),
(26, 260, '2026-07-14', '2026-08-13', 'aceito', 'Total R$1770,00 - 15% = 1500,00', '2026-07-14 20:46:38'),
(27, 260, '2026-07-14', '2026-08-13', 'pendente', 'O VALOR ESTÁ SEM DESCONTO. CONFORME FOR A FORMA DE PAGAMENTO / PARCELAMENTO, CONSIGO TRABALHAR MELHOR O DESCONTO. ESTAMOS CONSIDERANDO TRABALHAR ESTÉTICA EM 20 DENTES (10 SUPERIORES E 10 INFERORES).\r\n\r\nCOLOQUEI DESCONTO NAS RESTAURAÇÕES (TOTAL 1100,00 - 15% = 935,00)\r\nO TRATAMENTO DE CANAL TAMBÉM ESTÁ COM DESCONTO (750,00 PREÇO CHEIO, COM DESCONTO, 650,00)', '2026-07-14 21:00:16'),
(28, 64, '2026-07-15', '2026-08-14', 'aceito', '', '2026-07-15 14:37:16'),
(29, 141, '2026-07-15', '2026-08-14', 'aceito', 'Não inclui restauração, vamos fazer o raio x primeiro. \r\nVocê vai fazendo pix e eu vou dando baixa aqui! Fica tranquilo!', '2026-07-15 19:45:37'),
(30, 255, '2026-07-15', '2026-08-14', 'aceito', '', '2026-07-15 20:39:01'),
(31, 70, '2026-07-15', '2026-08-14', 'recusado', 'com Dra Lara\r\nabate operacional 50/50', '2026-07-15 20:54:57'),
(32, 70, '2026-07-17', '2026-08-16', 'aceito', '1850,00 - 10% a vista = 1635\r\n\r\nliquido 637,50', '2026-07-17 17:38:37'),
(33, 70, '2026-07-17', '2026-08-16', 'aceito', 'COM DRA NATALIA 4500/4500', '2026-07-17 17:40:01'),
(34, 262, '2026-07-17', '2026-08-16', 'aceito', '17/07/26 Pagou 140,00 em dinheiro', '2026-07-17 20:02:34'),
(35, 261, '2026-07-17', '2026-08-16', 'aceito', '', '2026-07-17 21:04:00'),
(36, 61, '2026-07-17', '2026-08-16', 'aceito', '', '2026-07-17 21:05:22'),
(37, 263, '2026-07-21', '2026-08-20', 'aceito', '', '2026-07-21 15:15:13'),
(38, 264, '2026-07-21', '2026-08-20', 'aceito', '', '2026-07-21 19:48:19'),
(39, 266, '2026-07-23', '2026-08-22', 'aceito', '', '2026-07-23 21:03:58'),
(40, 269, '2026-07-28', '2026-08-27', 'pendente', 'VALOR COM DESCONTO\r\n10% NA PROTESE (2100 X2 = 4200)\r\n15% NO CLINICO (1230-15%)', '2026-07-28 20:05:24'),
(41, 162, '2026-07-29', '2026-08-28', 'aceito', '', '2026-07-29 19:18:36'),
(42, 270, '2026-07-29', '2026-08-28', 'aceito', '', '2026-07-29 20:56:33'),
(43, 271, '2026-07-31', '2026-08-30', 'pendente', '', '2026-07-31 17:47:19'),
(44, 193, '2026-07-31', '2026-08-30', 'pendente', '', '2026-07-31 17:49:47'),
(45, 273, '2026-08-05', '2026-09-04', 'aceito', '', '2026-08-05 17:46:24');

-- --------------------------------------------------------

--
-- Estrutura da tabela `orcamentos_itens`
--

CREATE TABLE `orcamentos_itens` (
  `id` int(11) NOT NULL,
  `orcamento_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `valor_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `orcamentos_itens`
--

INSERT INTO `orcamentos_itens` (`id`, `orcamento_id`, `descricao`, `quantidade`, `valor_unitario`) VALUES
(13, 3, 'Raspagem e polimento', 1, 250.00),
(14, 4, 'Raspagem e polimento', 1, 240.00),
(15, 4, 'Pino de fibra de vidro e coroa provisória 14', 1, 640.00),
(16, 4, 'Colagem provisório 23', 1, 75.00),
(18, 5, 'Restaurações (7)', 1, 1410.00),
(22, 6, 'Raspagem e polimento', 1, 200.00),
(23, 6, 'Restaurações (9)', 1, 1160.00),
(24, 7, 'Raspagem e polimento', 1, 250.00),
(25, 7, 'Restaurações (4)', 1, 620.00),
(26, 8, 'Restaurações (2)', 1, 300.00),
(29, 10, 'Raspagem e polimento', 2, 250.00),
(31, 2, 'Raspagem e polimento', 1, 200.00),
(32, 2, 'Restauração 45 1 face', 1, 180.00),
(33, 2, 'Restauração 16 2 faces', 1, 280.00),
(34, 2, 'Restauração 21 1 face', 1, 280.00),
(35, 2, 'Restauração 23 1 face', 1, 180.00),
(36, 2, 'onlay 26', 1, 1500.00),
(37, 2, 'Onlay 25', 1, 1500.00),
(38, 2, 'Coroa psi 46', 1, 1700.00),
(39, 2, 'Coroa psi 47', 1, 1700.00),
(40, 2, 'Placa para Bruxismo', 1, 1300.00),
(45, 9, 'Restaurações', 2, 250.00),
(46, 9, 'Restaurações (2)', 3, 220.00),
(50, 13, 'Raspagem e polimento', 1, 280.00),
(51, 13, 'Restaurações', 4, 180.00),
(52, 14, 'clareamento', 1, 1000.00),
(53, 14, 'Placa Estabilizadora', 1, 950.00),
(54, 15, 'Restaurações', 4, 200.00),
(55, 15, 'limpeza', 1, 200.00),
(59, 16, 'Extração 28', 1, 450.00),
(60, 16, 'Extração Raiz 16', 1, 180.00),
(61, 17, 'Restauração 4 faces', 1, 320.00),
(62, 17, 'Restauração 1 face', 1, 180.00),
(63, 17, 'Raspagem e polimento', 1, 200.00),
(65, 19, 'clareamento', 1, 975.00),
(66, 19, 'Raspagem e polimento', 1, 270.00),
(67, 19, 'Restaurações (2)', 2, 200.00),
(69, 20, 'Restaurações', 1, 180.00),
(70, 21, 'Restaurações', 2, 200.00),
(71, 21, 'limpeza', 1, 237.50),
(72, 22, 'Raspagem e polimento', 1, 250.00),
(73, 23, 'limpeza + verniz', 1, 350.00),
(75, 25, 'extração deciduo', 1, 150.00),
(82, 28, 'onlay', 1, 1350.00),
(85, 29, 'Pino de fibra de vidro + coroa Emax', 1, 1850.00),
(86, 29, 'Raspagem e polimento', 1, 250.00),
(89, 31, 'exo + implante + rog', 1, 1850.00),
(90, 27, 'Implante + provisório 14', 1, 1850.00),
(91, 27, '18 Lentes emax', 18, 1900.00),
(92, 27, '2 Coroas sobre implante emax', 2, 2100.00),
(93, 27, 'Extração 18 e 28', 2, 450.00),
(94, 27, 'Restauraçóes 47; 46; 38 ; 27; 35', 5, 187.00),
(95, 27, 'Endo (canal) 32 e 13', 2, 650.00),
(96, 27, 'Endo (canal) 12', 1, 650.00),
(97, 32, 'implante', 1, 1635.00),
(98, 33, 'ENXERTO GENGIVAL 3 REGIOES', 3, 2200.00),
(99, 33, 'MUCODERM', 2, 1200.00),
(106, 35, 'limpeza', 1, 150.00),
(107, 36, 'limpeza', 1, 150.00),
(109, 24, 'coroa + pino + aumento de coroa', 1, 2150.00),
(115, 34, 'limpeza', 1, 250.00),
(116, 34, 'restauração 32', 1, 150.00),
(117, 34, 'restauração cervical 25', 1, 130.00),
(118, 34, 'restauração cervical 24', 1, 130.00),
(119, 34, 'restauração cervical 14,15,34,35,44,45', 6, 130.00),
(120, 11, 'Extração 18,28,48', 1, 1300.00),
(121, 37, 'limpeza e moldagem', 1, 370.00),
(128, 26, 'clareamento', 1, 1030.00),
(129, 26, 'Restauração', 1, 220.00),
(130, 26, 'Restauração', 1, 250.00),
(131, 30, 'PPR flex unilatera', 1, 675.00),
(132, 39, 'limpeza protocolo', 2, 350.00),
(133, 39, 'conserto protocolo', 1, 150.00),
(137, 38, 'clareamento', 1, 700.00),
(138, 38, 'restaurações cervical', 6, 150.00),
(139, 38, 'limpeza', 1, 250.00),
(140, 40, 'PPR METÁLICA SUPERIOR', 1, 1890.00),
(141, 40, 'PPR METALICA INFERIOR', 1, 1890.00),
(142, 40, 'RASPAGEM', 1, 200.00),
(143, 40, 'EXTRAÇÃO 24', 1, 225.00),
(144, 40, 'EXTRAÇÃO 34', 1, 200.00),
(145, 40, 'EXTRAÇÃO 31', 1, 225.00),
(146, 40, 'RESTAURAÇÃO', 1, 195.00),
(147, 41, 'Raspagem e polimento', 1, 250.00),
(148, 41, 'Restaurações', 1, 150.00),
(149, 42, 'Restauração 2 Faces', 1, 250.00),
(152, 44, 'Extração + implante 37', 1, 1300.00),
(153, 44, 'Restauração 2 Faces 34', 1, 220.00),
(154, 44, 'Raspagem e polimento', 1, 249.80),
(155, 44, 'Coroa Psi', 1, 1700.00),
(156, 44, 'coroa sobre dente', 1, 1575.00),
(157, 44, 'Coroa Sobre dente', 1, 1620.00),
(158, 43, 'Implante', 2, 1300.00),
(159, 43, 'coroa sobre implante', 4, 1650.00),
(160, 45, 'Prótese total provisória', 1, 880.00);

-- --------------------------------------------------------

--
-- Estrutura da tabela `parcelas`
--

CREATE TABLE `parcelas` (
  `id` int(11) NOT NULL,
  `orcamento_id` int(11) NOT NULL,
  `numero_parcela` tinyint(4) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `vencimento` date NOT NULL,
  `status` enum('pendente','paga','atrasada') DEFAULT 'pendente',
  `data_pagamento` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `parcelas`
--

INSERT INTO `parcelas` (`id`, `orcamento_id`, `numero_parcela`, `valor`, `vencimento`, `status`, `data_pagamento`) VALUES
(13, 3, 1, 250.00, '2026-06-27', 'pendente', NULL),
(14, 4, 1, 477.50, '2026-06-27', 'pendente', NULL),
(15, 4, 2, 477.50, '2026-07-27', 'pendente', NULL),
(21, 5, 1, 282.00, '2026-06-27', 'paga', '2026-06-23'),
(22, 5, 2, 282.00, '2026-07-27', 'paga', '2026-06-23'),
(23, 5, 3, 282.00, '2026-08-27', 'paga', '2026-06-23'),
(24, 5, 4, 282.00, '2026-09-27', 'paga', '2026-06-23'),
(25, 5, 5, 282.00, '2026-10-27', 'paga', '2026-06-23'),
(28, 6, 1, 136.00, '2026-07-12', 'paga', '2026-06-12'),
(29, 6, 2, 136.00, '2026-08-12', 'paga', '2026-06-12'),
(30, 6, 3, 136.00, '2026-09-12', 'paga', '2026-06-12'),
(31, 6, 4, 136.00, '2026-10-12', 'paga', '2026-06-12'),
(32, 6, 5, 136.00, '2026-11-12', 'paga', '2026-06-12'),
(33, 6, 6, 136.00, '2026-12-12', 'paga', '2026-06-12'),
(34, 6, 7, 136.00, '2027-01-12', 'paga', '2026-06-12'),
(35, 6, 8, 136.00, '2027-02-12', 'paga', '2026-06-12'),
(36, 6, 9, 136.00, '2027-03-12', 'paga', '2026-06-12'),
(37, 6, 10, 136.00, '2027-04-12', 'paga', '2026-06-12'),
(38, 7, 1, 870.00, '2026-07-12', 'paga', '2026-06-12'),
(39, 8, 1, 100.00, '2026-07-12', 'pendente', NULL),
(40, 8, 2, 100.00, '2026-08-12', 'pendente', NULL),
(41, 8, 3, 100.00, '2026-09-12', 'pendente', NULL),
(46, 10, 1, 500.00, '2026-07-12', 'paga', '2026-07-08'),
(47, 11, 1, 1300.00, '2026-07-12', 'atrasada', NULL),
(48, 2, 1, 735.00, '2026-07-12', 'pendente', NULL),
(49, 2, 2, 735.00, '2026-08-12', 'pendente', NULL),
(50, 2, 3, 735.00, '2026-09-12', 'pendente', NULL),
(51, 2, 4, 735.00, '2026-10-12', 'pendente', NULL),
(52, 2, 5, 735.00, '2026-11-12', 'pendente', NULL),
(53, 2, 6, 735.00, '2026-12-12', 'pendente', NULL),
(54, 2, 7, 735.00, '2027-01-12', 'pendente', NULL),
(55, 2, 8, 735.00, '2027-02-12', 'pendente', NULL),
(56, 2, 9, 735.00, '2027-03-12', 'pendente', NULL),
(57, 2, 10, 735.00, '2027-04-12', 'pendente', NULL),
(58, 2, 11, 735.00, '2027-05-12', 'pendente', NULL),
(59, 2, 12, 735.00, '2027-06-12', 'pendente', NULL),
(64, 9, 1, 232.00, '2026-07-15', 'paga', '2026-06-15'),
(65, 9, 2, 232.00, '2026-08-15', 'pendente', NULL),
(66, 9, 3, 232.00, '2026-09-15', 'pendente', NULL),
(67, 9, 4, 232.00, '2026-10-15', 'pendente', NULL),
(68, 9, 5, 232.00, '2026-11-15', 'pendente', NULL),
(73, 13, 1, 500.00, '2026-07-17', 'pendente', NULL),
(74, 13, 2, 500.00, '2026-08-17', 'pendente', NULL),
(75, 14, 1, 325.00, '2026-07-17', 'paga', '2026-06-17'),
(76, 14, 2, 325.00, '2026-08-17', 'paga', '2026-06-17'),
(77, 14, 3, 325.00, '2026-09-17', 'pendente', NULL),
(78, 14, 4, 325.00, '2026-10-17', 'pendente', NULL),
(79, 14, 5, 325.00, '2026-11-17', 'pendente', NULL),
(80, 14, 6, 325.00, '2026-12-17', 'pendente', NULL),
(81, 15, 1, 250.00, '2026-07-18', 'paga', '2026-06-18'),
(82, 15, 2, 250.00, '2026-08-18', 'pendente', NULL),
(83, 15, 3, 250.00, '2026-09-18', 'pendente', NULL),
(84, 15, 4, 250.00, '2026-10-18', 'pendente', NULL),
(91, 16, 1, 630.00, '2026-07-23', 'paga', '2026-06-23'),
(92, 17, 1, 175.00, '2026-07-23', 'paga', '2026-06-23'),
(93, 17, 2, 175.00, '2026-08-23', 'pendente', NULL),
(94, 17, 3, 175.00, '2026-09-23', 'pendente', NULL),
(95, 17, 4, 175.00, '2026-10-23', 'pendente', NULL),
(120, 19, 1, 274.17, '2026-07-27', 'paga', '2026-06-27'),
(121, 19, 2, 274.17, '2026-08-27', 'paga', '2026-06-27'),
(122, 19, 3, 274.17, '2026-09-27', 'pendente', NULL),
(123, 19, 4, 274.17, '2026-10-27', 'pendente', NULL),
(124, 19, 5, 274.17, '2026-11-27', 'pendente', NULL),
(125, 19, 6, 274.15, '2026-12-27', 'pendente', NULL),
(127, 20, 1, 180.00, '2026-08-01', 'paga', '2026-07-01'),
(128, 21, 1, 637.50, '2026-08-03', 'paga', '2026-07-03'),
(129, 22, 1, 250.00, '2026-08-03', 'paga', '2026-07-03'),
(130, 23, 1, 350.00, '2026-08-04', 'paga', '2026-07-04'),
(132, 25, 1, 150.00, '2026-08-08', 'paga', '2026-07-08'),
(135, 28, 1, 337.50, '2026-08-15', 'paga', '2026-07-15'),
(136, 28, 2, 337.50, '2026-09-15', 'paga', '2026-07-15'),
(137, 28, 3, 337.50, '2026-10-15', 'pendente', NULL),
(138, 28, 4, 337.50, '2026-11-15', 'pendente', NULL),
(140, 29, 1, 525.00, '2026-08-15', 'paga', '2026-07-15'),
(141, 29, 2, 525.00, '2026-09-15', 'pendente', NULL),
(142, 29, 3, 525.00, '2026-10-15', 'pendente', NULL),
(143, 29, 4, 525.00, '2026-11-15', 'pendente', NULL),
(150, 31, 1, 1850.00, '2026-08-15', 'pendente', NULL),
(151, 27, 1, 44035.00, '2026-08-17', 'pendente', NULL),
(152, 32, 1, 1635.00, '2026-08-17', 'paga', '2026-07-17'),
(153, 33, 1, 9000.00, '2026-08-17', 'pendente', NULL),
(156, 35, 1, 150.00, '2026-08-17', 'paga', '2026-07-17'),
(157, 36, 1, 150.00, '2026-08-17', 'paga', '2026-07-17'),
(159, 24, 1, 537.50, '2026-08-18', 'paga', '2026-07-29'),
(160, 24, 2, 537.50, '2026-09-18', 'paga', '2026-07-29'),
(161, 24, 3, 537.50, '2026-10-18', 'pendente', NULL),
(162, 24, 4, 537.50, '2026-11-18', 'pendente', NULL),
(164, 34, 1, 1440.00, '2026-08-20', 'paga', '2026-07-20'),
(165, 11, 1, 1300.00, '2026-08-20', 'pendente', NULL),
(166, 37, 1, 370.00, '2026-08-21', 'paga', '2026-07-21'),
(169, 26, 1, 1500.00, '2026-08-21', 'paga', '2026-07-21'),
(170, 30, 1, 168.75, '2026-08-21', 'pendente', NULL),
(171, 30, 2, 168.75, '2026-09-21', 'pendente', NULL),
(172, 30, 3, 168.75, '2026-10-21', 'pendente', NULL),
(173, 30, 4, 168.75, '2026-11-21', 'pendente', NULL),
(174, 39, 1, 850.00, '2026-08-23', 'paga', '2026-07-24'),
(180, 38, 1, 308.33, '2026-08-28', 'paga', '2026-07-28'),
(181, 38, 2, 308.33, '2026-09-28', 'pendente', NULL),
(182, 38, 3, 308.33, '2026-10-28', 'pendente', NULL),
(183, 38, 4, 308.33, '2026-11-28', 'pendente', NULL),
(184, 38, 5, 308.33, '2026-12-28', 'pendente', NULL),
(185, 38, 6, 308.35, '2027-01-28', 'pendente', NULL),
(186, 40, 1, 4825.00, '2026-08-28', 'pendente', NULL),
(187, 41, 1, 400.00, '2026-08-29', 'pendente', NULL),
(188, 42, 1, 250.00, '2026-08-29', 'paga', '2026-07-29'),
(190, 44, 1, 6664.80, '2026-08-31', 'pendente', NULL),
(191, 43, 1, 9200.00, '2026-09-05', 'pendente', NULL),
(192, 45, 1, 97.78, '2026-09-05', 'pendente', NULL),
(193, 45, 2, 97.78, '2026-10-05', 'pendente', NULL),
(194, 45, 3, 97.78, '2026-11-05', 'pendente', NULL),
(195, 45, 4, 97.78, '2026-12-05', 'pendente', NULL),
(196, 45, 5, 97.78, '2027-01-05', 'pendente', NULL),
(197, 45, 6, 97.78, '2027-02-05', 'pendente', NULL),
(198, 45, 7, 97.78, '2027-03-05', 'pendente', NULL),
(199, 45, 8, 97.78, '2027-04-05', 'pendente', NULL),
(200, 45, 9, 97.76, '2027-05-05', 'pendente', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `procedimentos`
--

CREATE TABLE `procedimentos` (
  `id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `medicamentos` text DEFAULT NULL,
  `data_procedimento` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `procedimentos`
--

INSERT INTO `procedimentos` (`id`, `paciente_id`, `titulo`, `descricao`, `medicamentos`, `data_procedimento`) VALUES
(10, 95, 'RESTAURAÇÃO', 'RESTAURAÇÕES : 36(O); 44(D); 45(OM);46 (O); 47(OMV). RESINA EA3,5.', NULL, '2026-06-15'),
(12, 95, 'consulta', NULL, NULL, '2026-06-26'),
(13, 63, 'ajuste de placa', '29/06/2026 ajuste de Placa', NULL, '2026-06-29'),
(14, 56, 'RESTAURAÇÃO', 'Restauração mesial e distal do 23; restauração palatina 12,13; restauração distal 14. Orientações sobre melhorar a higiene .', NULL, '2026-06-29'),
(15, 110, 'avaliação', 'Avaliação 23: necessidade de exo + implante +  rog + provisório imediato. Coroa definitiva após 90 dias no mínimo.', NULL, '2026-06-30'),
(16, 64, 'remover sutura', 'remoção de sutura', NULL, '2026-06-30'),
(17, 185, 'RESTAURAÇÃO', 'restauração palatina 25', NULL, '2026-07-01'),
(18, 258, 'Consulta Laluce', 'RASPAGEM + RESTAURAÇÃO 35OM E 45D', NULL, '2026-07-03'),
(19, 223, 'Clareamento', 'FOTO INICAL COR A3, 2 PERÓXIDO DE HIROGÊNIO 7,5% E 1 PERÓXIDO DE CARBAMIDA 22%, PLACA SUPERIOR E INFERIOR E ORIENTAÇÕES.', NULL, '2026-07-03'),
(20, 111, 'consulta', 'RASPAGEM SUBGENGIVAL COM ORIENTAÇÕES', NULL, '2026-07-03'),
(21, 101, 'CONSULTA', 'Raspagem + profilaxia + aplicação de verniz em molares e incisivos\r\nacompanhamento e controle HIMI', NULL, '2026-07-04'),
(22, 62, 'aval', 'remoção de tecido cariado e restauração 11 e 21. aplicação de verniz 12,13,22,23', NULL, '2026-07-06'),
(23, 70, 'curativo', 'curativo', NULL, '2026-07-06'),
(24, 64, 'PREPARO E MOLDAGEM PARA ONLAY', 'Aumento de coroa clinica pra posterior reabilitação', NULL, '2026-07-07'),
(25, 119, 'PROVAR COPING', 'Prova do coping + registro+ cor A3', NULL, '2026-07-07'),
(26, 257, 'CONTROLE', 'controle', NULL, '2026-07-08'),
(27, 62, 'RESTAURAÇÃO', 'faltou', NULL, '2026-07-08'),
(28, 54, 'extração deciduo', 'Extração dente 71 sem intercorrências', NULL, '2026-07-08'),
(29, 64, 'PREPARO E MOLDAGEM', 'preparo e moldagem', NULL, '2026-07-14'),
(30, 259, 'PREPARO E ESCANEAMENTO', 'preparo + escaneamento', NULL, '2026-07-14'),
(31, 70, 'Cirurgia com Dra Natália', 'CIRURGIA DE ENXERTO COM DRA NATALIA JANUARIO', NULL, '2026-07-17'),
(32, 259, 'cimentação de coroa + ajuste', NULL, NULL, '2026-07-17'),
(33, 61, 'limpeza', 'Remoção de contenção + limpeza', NULL, '2026-07-17'),
(34, 262, 'RASPAGEM E POLIMENTO', 'Raspagem e polimento, restaurações cervical 14/15/24/25/34/35/44/45 EA3,5', NULL, '2026-07-20'),
(35, 223, 'Clareamento', 'Clareamento: entrega de 3 seringas de peroxido de hidrogenio 10%', NULL, '2026-07-21'),
(36, 168, 'RASPAGEM E POLIMENTO', 'raspagem + moldagem para plaquinha de clareamento', NULL, '2026-07-21'),
(37, 260, 'Clareamento', 'entrega do clareamento com peroxido de hidrogenio 10%', NULL, '2026-07-21'),
(38, 38, '+pino de fibra de vidro + reconstrução + preparo + moldagem para coroa cor A2', NULL, NULL, '2026-07-25'),
(39, 264, 'Raspagem + moldagem para clareamento', 'Raspagem + polimento + moldagem para clareamento', NULL, '2026-07-28'),
(40, 265, 'ONLAY', 'REMOÇÃO DE PEÇA CIMENTADA + ESCANEAMENTO + COR A3\r\nLABORATÓRIO TECNOFLEX', NULL, '2026-07-29'),
(41, 270, 'Restauração 36', 'Restauração ocluso - mesial com remoção de amálgama.  Resina EA2', NULL, '2026-07-29'),
(42, 73, 'instalar coroas', 'CIMENTAÇÃO ONLAY DO 25 E AJUSTE. INTALAÇÃO 46 E 47  E AJUSTE. NOVA MOLDAGEM PARA ONLAY 26, DENTE FRATUROU.', NULL, '2026-07-30'),
(43, 193, 'consulta', 'AVALIAÇÃO: \r\nEXO E IMPLANTE 37\r\nRASPAGEM\r\nRESTAURAÇÃO 35\r\nENDO 46  E 47\r\nCOROA SOBRE IMPLANTE 37\r\nCOROA/ ONLAY 46 E 47', NULL, '2026-07-30'),
(44, 265, 'consulta', 'AJUSTE NA RESINA PROVISÓRIA', NULL, '2026-07-30');

-- --------------------------------------------------------

--
-- Estrutura da tabela `prontuarios`
--

CREATE TABLE `prontuarios` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `prontuarios`
--

INSERT INTO `prontuarios` (`id`, `paciente`, `nascimento`, `sexo`, `estado_civil`, `profissao`, `rg`, `cpf`, `endereco`, `cep`, `telefone`, `email`, `observacoes`, `tratamento_odonto`, `tratamento_medico`, `medicamento_continuo`, `alergia_medicamento`, `alergia_outras`, `problemas_saude`, `gravida_meses`, `fuma_tempo`, `fuma_cigarros_dia`, `bebida_frequencia`, `drogas_uso`, `doencas_transmissiveis`, `cancer_familiar`, `tratamento_cancer`, `created_at`) VALUES
(3, '1 Jéssica Monteiro', '1994-10-20', 'Feminino', '', '', '413244994', '42439933864', '', '16022310', '18 996920085', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:01:46'),
(4, '2 Lúcia Leite da Silva', '1968-06-08', 'Feminino', '', '', '364594119', '32871405824', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:03:41'),
(5, '3 Luiz Ricardo Anselmo Alexandrino', '2011-04-07', 'Masculino', '', '', '648740845', '49721340898', '', '', '', '', 'ano 2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:06:22'),
(6, '4 Fernanda Marcela Candido Alexandrino', '1979-12-15', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:10:22'),
(8, '5 Roberta Pereira dos Santos', '1973-07-30', 'Feminino', '', 'cozinheira', '251052102', '28229981833', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:12:58'),
(9, '6 Fernanda Santana Souza', '2013-03-28', 'Feminino', '', '', '', '0000000000', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:14:20'),
(10, '7 Ariel Bonin Breves', '0001-01-01', 'Masculino', '', '', '631370316', '06577320101', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:15:30'),
(11, '8 Miguel Geraldo Pereira', '2016-05-15', 'Masculino', '', '', '', '00000000000', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:16:47'),
(12, '9 Karilyn Maiara Escobar', '1986-08-29', 'Feminino', '', '', '347652578', '22843772893', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:19:09'),
(13, '10 Yan Capelari Scatena', '2012-08-02', 'Masculino', '', '', '569021571', '459103368160', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:24:31'),
(14, '11 Wagner Groto Rodrigues', '1976-09-22', 'Masculino', '', '', '3027437054', '21586367854', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:28:06'),
(15, '12 Valdira Miguel', '1961-08-08', 'Feminino', '', '', '19400773', '08980239807', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:29:30'),
(16, '13 Vanderlei Lopes Ferreira', '1977-07-17', 'Feminino', '', '', '68666511', '02916972919', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:30:51'),
(17, '14 Lilian Batista de Lima', '1982-04-02', 'Feminino', '', '', '', '22333826843', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:32:03'),
(18, '15 Vânia Cristina do Amaral Lemes', '1972-10-16', 'Feminino', '', '', '242648782', '13684544884', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:40:56'),
(19, '16 Thamires Soares Fernandes', '1993-09-29', 'Feminino', '', '', '495681428', '37918387848', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:43:01'),
(20, '17 Thaynara Barbosa Freitas', '1998-02-17', 'Feminino', '', '', '79011903', '03691652174', '', '', '', '', '20020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:44:26'),
(21, '18 Thiago Mantovani Prado', '1986-11-07', 'Masculino', '', '', '2965877965', '21532900821', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:45:34'),
(22, '19 Sara Macedo de Oliveira', '2011-05-11', 'Feminino', '', '', '', '000000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:46:43'),
(23, '20 Sumaia Carvalho Tavares Gagliardi', '1988-06-30', 'Feminino', '', '', '440205931', '36811208899', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:47:53'),
(24, '21 Rogério de Jesus Pichuti', '1983-08-18', 'Masculino', '', '', '', '33193349801', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:51:12'),
(25, '22 Pedro Octávio da Silva Marin', '2012-10-16', 'Masculino', '', '', '667681139', '58276741843', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:52:19'),
(26, '23 Pamela Cristina Queiroz de Lima', '1986-07-19', 'Feminino', '', '', '', '33710194890', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:54:33'),
(27, '24 Pedro Cordeiro Vieira', '1961-06-29', 'Masculino', '', '', '', '02380008850', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:55:33'),
(28, '25 Pedro Rodrigues Lemes JR', '1966-04-30', 'Masculino', '', '', '', '08846623819', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:56:30'),
(29, '26 Rafaela da Silva Vieira', '1990-05-30', 'Feminino', '', '', '46358904', '39789833890', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:57:41'),
(30, '27 Rosangela Tadeu da SIlva', '1974-02-18', 'Feminino', '', '', '25095430', '13693523878', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 17:58:52'),
(31, '28 Rosa Maria Gon', '1961-10-22', 'Feminino', '', '', '', '19400845', '', '2526507189', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:01:52'),
(32, '29 Roseli Goulart de Siqueira', '1981-09-24', 'Feminino', '', '', '', '00000000000', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:10:27'),
(33, '30 Mercedes da Silva Vieira', '1958-08-10', 'Feminino', '', '', '119654556', '24893251830', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:11:48'),
(34, '31 Fátima de Oliveira Lima', '1963-05-02', 'Feminino', '', '', '166764759', '06161676877', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:12:58'),
(35, '32 Luiz Fernando Bido', '1976-05-14', '', '', '', '', '00000000000', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:14:00'),
(36, '33 Elaine Mello Garcia', '1982-01-04', 'Feminino', '', '', '325197295', '215491000840', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:15:02'),
(37, '34 Heitor de Souza Moita', '1994-11-03', 'Masculino', '', '', '456492367', '43622743832', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:16:32'),
(38, '35 Marcela Carraro Manilia', '1993-05-17', 'Feminino', '', '', '9903936', '08223837994', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:17:47'),
(39, '36 Leticia Marchiolli de Grandi', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:18:52'),
(40, '37 Alice Groto Rodrigues', '1956-04-09', 'Feminino', '', '', '36125924', '119.956.858.95', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:20:24'),
(41, '38 Dora Sidney Gabriel da Silva Bernardo', '1968-12-01', 'Feminino', '', '', '359834450', '21658937805', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:21:43'),
(42, '39 Renata Cristina Guimarães', '1978-03-16', 'Feminino', '', '', '251999658', '119.998.828.65', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:23:01'),
(43, '40 Priscila Aparecida dos Santos Silva', '1988-04-29', 'Feminino', '', '', '402621529', '36904747870', '', '', '', '', '2020- sem procedimento nesse ano', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-26 18:24:55'),
(44, '332 Sônia Nunes dos Santos', '1976-08-05', 'Feminino', '', '', '', '00000000000', '', '', '18996595656', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:40:25'),
(45, '333 Viviane de Jesus dos Santos', '1992-07-19', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:42:17'),
(46, '334 Sérgio Vinicius da Silva Santucci', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:43:03'),
(47, '335 Fernanda Negri', '1986-05-09', 'Feminino', 'casada', '', '', '00000000000', '', '', '', '', '', '', '', '', '', 'abelha', '', '', '', '', '', '', '', '', '', '2026-05-27 15:45:02'),
(48, '336  Claudemir da Silva', '1971-04-06', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:53:17'),
(49, '337 Ragda Cristina Ferreira', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:53:58'),
(50, '338 José Luiz da Costa', '0001-01-01', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 15:55:31'),
(51, '339 Assunta Ermínia de Paula Pravato', '1952-12-28', 'Feminino', '', '', '110784327', '25270604809', 'Rua Fundador Vicente Franco 145', '16050370', '', '', '', '', '', 'Alprazolam, Quetiapina 50mg, Sertralina', '', '', 'Problemas Respiratórios, Outros: Problemas Respiratórios,  Asma', '', '', '', '', '', '', '', '', '2026-05-27 15:58:28'),
(52, '340 Bruno Ferrite', '0001-01-01', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:00:33'),
(53, '341 Patricia Machado', '1975-01-27', 'Feminino', '', '', '', '00000000000', '', '', '18981853333', '', '', '', 'toc, tag', 'flouxetina, apraz', '', 'iodo, frutos do mar', '', '', '', '', '', '', '', '', '', '2026-05-27 16:02:46'),
(54, '342 maria Luiza Vilela Zani', '2020-02-11', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:05:18'),
(55, '343 José Maria Schidmit', '0001-01-01', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:06:08'),
(56, '344 Gilberto Cândido', '1979-03-18', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:07:33'),
(57, '345 Maria José Santana', '1962-10-14', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:08:31'),
(58, '346 Fernanda Furlan Araujo Franca', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:09:08'),
(59, '347 Kévin Roberto Fernandes Lima', '1998-12-22', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:10:06'),
(60, '363 Amanda Barreira Cervelheira', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:10:45'),
(61, '348 Gabriela Zancon Inácio', '1997-09-17', 'Feminino', '', '', '', '00000000000', '', '', '11914893094', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:11:37'),
(62, '349 Igor Agostine', '0001-01-01', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:12:22'),
(63, '371 Clicéria Berteli', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:12:53'),
(64, '350 Thainá Lopes', '1995-09-04', 'Feminino', '', '', '', '46390904800', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:24:47'),
(65, '351 Caroline Pitarelli', '1996-04-04', 'Feminino', '', '', '', '00000000000', '', '', '44 98842 4121', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:26:25'),
(66, '352 Gilson', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:27:55'),
(67, '353 Lenita Aparecida Guerra', '1980-06-12', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:29:03'),
(68, '354 Tatiane', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:30:11'),
(69, '355 Evelin', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:30:51'),
(70, '356 Nilva Correia Gardenal', '1964-11-26', 'Feminino', 'casada', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:33:16'),
(71, '357 Amanda Correia Gardenal', '1998-03-16', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:34:50'),
(72, '358 Heitor Matos da Silveira', '1993-03-15', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:35:47'),
(73, '373 Tereza Marques do Amaral', '0001-01-01', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:37:25'),
(74, '359 Isadora Guerra Teixeira', '2005-11-17', 'Feminino', '', '', '', '49487076867', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:49:23'),
(75, '374 Jenifer Tatiele Rodrigues Bassetti', '1998-10-02', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 16:51:44'),
(76, '41 Ana Carolina do Nascimento', '0090-08-13', 'Feminino', '', '', '', '39731663827', '', '', '', '', '2020', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:24:12'),
(77, '42 Amanda Cavalari de Godoi dos Anjos', '1997-05-13', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:26:02'),
(78, '43 Bruna Gonçalves Guimarães', '1990-04-07', 'Feminino', '', '', '', '39471425875', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:27:01'),
(79, '44 Beatriz Anderline Ferreira', '2011-11-16', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:28:02'),
(80, '45 Bento Gagliardo Garcia', '2019-08-31', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:28:53'),
(81, '46 Elisa Calegari Bortolot', '1998-07-24', 'Feminino', '', '', '', '47612692864', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:30:12'),
(82, '47 Emanuela Aparecida Doná de Paula', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:31:13'),
(83, '48 Gabriela Firmino Massaro', '1982-10-29', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:31:55'),
(84, '49 Henrique C. Godoi dos Anjos', '2017-06-19', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:32:44'),
(85, '50 José Renato Furlaneto Pinto', '0001-01-01', '', '', '', '', '44181392813', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:33:44'),
(86, '51 Karina Gonçalves de Jesus', '1986-04-16', 'Feminino', '', '', '351656844', '22859402896', '', '16021485', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:34:37'),
(87, '52 Joaquim queiroz de Sousa', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:35:26'),
(88, '53 Lucas Gonçalves Pires de Jesus Souza', '2011-02-04', 'Masculino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:36:25'),
(89, '54 Luciana', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:37:34'),
(90, '55 Maria Aparecida Andre de SOuza', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 17:38:11'),
(91, '308 Sandra Regina Antônio Mendes Galvão', '1966-08-13', '', '', '', '', '08577260801', '', '', '', '', '', '', 'Paciente transplantada', 'NA FICHA FISICA', '', '', 'Hipertensão', '', '', '', '', '', '', '', '', '2026-05-27 18:30:12'),
(92, '306 Nathalia Drielli Passarela Cavalheiro', '1991-10-03', 'Feminino', '', '', '', '41503656870', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 18:32:24'),
(93, '307 Jéssica Barbosa Arantes', '1993-11-06', '', '', '', '', '400933362854', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-05-27 18:33:21'),
(94, '360 Terezinha de Fátima Ribeiro Barbosa', '1959-02-07', 'Feminino', 'casada', '', '152931466', '06731986852', 'Dr Cesar Bombarda, 190, Claudionor Cinti', '', '', '', '', '', '', 'omeprazol e lozartana', '', '', 'Hipertensão', '', '', '', '', '', '', '', '', '2026-06-12 18:04:31'),
(95, '361 Eduardo Fornageiro Uemura', '1981-11-17', 'Masculino', 'solteiro', '', '', '22153040817', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-12 18:11:18'),
(96, '362 Rodrigo de Oliveira', '1992-07-10', 'Masculino', 'solteiro', '', '', '40289114861', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-12 19:48:07'),
(97, '383 Luis Fernando Pires', '1988-01-18', 'Masculino', 'CASADO', '', '', '37808359831', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-12 19:49:02'),
(99, '309 Mariana Pimentel Bernardo Oliveira', '1997-02-14', 'Feminino', '', '', '458915841', '45713303837', 'Joao Angelo Poncho 666', '15050400', '18988145747', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 17:59:23'),
(100, '310 Danilo Pelegrino', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:00:04'),
(101, '311 Miguel Menezes Namba', '0001-01-01', '', '', '', '', '0000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:01:02'),
(102, '312 Solange de Almeida Antônio', '1962-05-06', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:01:54'),
(103, '313 Ana Livia Souza SIlvino Alexandre', '2014-06-16', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:02:53'),
(104, '314 Nicole Pyetra Silvino Oliveira', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:04:20'),
(105, '317 Sanie Miriam Rossane de Araujo', '1962-06-06', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:06:23'),
(106, '318 Rebeca Emanuelle dos Santos', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:07:20'),
(107, '319 Vilma Aparecida Meireles Borges', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:08:10'),
(108, '320 Vitor Matheus Costa Santos', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:09:06'),
(109, '321 Antonio Honório Filho', '1956-02-05', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:09:57'),
(110, '322 Rita de Cássia Bedran Benez Bixofis', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:10:53'),
(111, '323 Gustavo Marques Pereira Paulon', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:11:47'),
(112, '324 Luis Gustavo Hanisch Cerizza', '2004-03-04', 'Feminino', '', '', '582886909', '40048760870', 'Rua Torres Homem 1298 -  Vila Santa Maria', '', '', '', '', '', '', '', 'Buscopan', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:13:27'),
(113, '325 Isabel Dangelo Souza', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:14:46'),
(114, '326 Simone Cristina de Oliveira Rafael', '1975-06-02', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:15:50'),
(115, '327 Claudete de Oliveira Borges', '1978-07-19', 'Feminino', '', '', '', '22704483833', 'Rua 17 n 188 Vida Nova', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:17:26'),
(116, '328 José Roberto Ferreira Lima', '1969-03-12', '', '', '', '', '0000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:18:18'),
(117, '329 Mariana Bansi', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:19:36'),
(118, '364 Natalia Mendes', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:20:25'),
(119, '330 Jacira Correia dos Santos', '1968-04-12', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:21:19'),
(120, '331 Ismair Gonçalves Amaral', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:25:09'),
(121, '316 Sara', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:30:23'),
(122, '315 Antonio Ivo Aureliano', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-17 18:33:11'),
(123, '200 Lara Palermo Schneiderit', '2018-10-09', 'Feminino', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-18 12:51:06'),
(124, '280 Ana Beatriz Famiziera', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:17:56'),
(125, '281 Rodrigo Cristovam', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:19:01'),
(126, '282 Rafael Cancian', '0001-01-01', '', '', '', '', '0000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:20:32'),
(127, '283 Amanda Cancian dos Santos', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:21:46'),
(128, '284 Juliana Fleury', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:22:21'),
(129, '285 larissa Michele Vieira Silva', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:23:42'),
(130, '286 Andrea Borges Correia', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:24:45'),
(131, '287', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:25:45'),
(132, '288 Ivelize Aleixo', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:26:38'),
(133, '289 Dirce Martins Fernandes', '0001-01-01', '', '', '', '', '0000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:27:51'),
(134, '290 Daniela Palermo Schneiderit', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:36:10'),
(135, '291 Maria de Lourdes Trevisam Chapenote', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:37:12'),
(136, '292 Paula Martinelli dos Santos', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:38:41'),
(137, '293 Rafaela Melo Ribeiro', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:39:46'),
(138, '295 vinicius Gonçalves Porto Nascimento', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:40:39'),
(139, '296 Jaqueline Cristiane leite Pereira', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:41:37'),
(140, '297 Sueli Prima', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:42:44'),
(141, '298 Matheus Palermo', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:44:51'),
(142, '299 Vanessa Camila Campana', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:45:38'),
(143, '300 Fatima Raquel Ribeiro da Silva', '1973-07-27', 'Feminino', '', '', '', '09563069854', 'Rua Rodolfo Miranda, 1348', '16021507', '18998013301', '', '', '', '', '', '', '', 'Outros: Arritimia cardíaca', '', '', '', '', '', '', '', '', '2026-06-23 18:48:55'),
(144, '301 Glauco de Arruda Campos', '1957-06-04', 'Masculino', 'casado', '', '1057966', '02355947848', 'Rua Argentina 962, Jd Brasilia', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:53:48'),
(145, '302 Sofia Amorim', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:57:10'),
(146, '303 Rafael Vinicius Garcia', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 18:57:43'),
(147, '304 Diego Roldão', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 19:00:40'),
(148, '305 Gustavo Marques', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-23 19:01:08'),
(149, '234 Lucimar Vitor Ruiz', '1975-11-18', '', '', '', '', '24916259890', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'REMOÇÃO DE NÓDULO EM 2015', '2026-06-26 17:59:45'),
(150, '235 Agenor Amorim', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:00:38'),
(151, '236 Lauriane Aparecida Garcia Silva', '1977-10-18', '', '', '', '', '2692739602', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:01:46'),
(152, '237 Rosimeire da Silva ALves', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:02:45'),
(153, '238 Luciana Pereira de Oliveira Silva', '1978-02-21', '', '', '', '', '21249216818', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:03:39'),
(154, '239 Jonatas Morais Souza', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:04:13'),
(155, '240 Bruna Murari', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:05:01'),
(156, '241 Bruna Menezes', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:05:36'),
(157, '242 Cintia Palmade Souza', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:06:25'),
(158, '243 Yasmin de Oliveira Rodrigues', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:07:01'),
(159, '244 Paloma Perez Gonçalves', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:07:27'),
(160, '245 João Arthur Mazarini', '1962-05-28', '', '', '', '', '04367796841', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:08:22'),
(161, '246 Laura Spironelli', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:08:47'),
(162, '247 Ana Carolina Arruda', '1982-10-29', 'Feminino', 'solteira', '', '300058093', '21975123824', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:11:44'),
(163, '248 Ana Beatriz Regino Soterio', '2002-03-10', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:12:30'),
(164, '249 Lorenzo Fiumari', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:12:59'),
(165, '250 Matheus Araujo', '1992-03-28', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:15:37'),
(166, '251 Luiz Chinaglia', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:16:07'),
(167, '252 Gabriela Ariene Marques', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:16:32'),
(168, '253 Rafael Martins Barbosa', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:17:06'),
(169, '254 Alessandra', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:17:35'),
(170, '255 Sebastião Carlos Ventura', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:18:09'),
(171, '256 Thais Diniz', '1986-10-25', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:18:44'),
(172, '257 Claiton Barbosa Moura', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:19:29'),
(173, '258 Sara Cardoso Seabra', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 18:20:01'),
(174, '259 Luciano', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:33:45'),
(175, '260 Fátima de Oliveira Firmino', '1956-03-22', '', '', '', '95359485', '95877002872', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:34:40'),
(176, '261 Fernanda Cansoni', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:35:07'),
(177, '262 william Daniel Alves Rodrigues', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:35:32'),
(178, '263 Elcio José O. de Castro', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:36:34'),
(179, '264 Leticia Cristina Oliveira Tomé dos Santos', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:38:24'),
(180, '265 Joao Vitor Neris Ribeiro', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:39:22'),
(181, '266 Zilda Pereira dos Santos Casoni', '1966-05-12', '', '', '', '227660213', '06720639803', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:40:23'),
(182, '267 Ruy Padula', '1957-07-20', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:40:56'),
(183, '268 Fernanda Bertolino', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:41:15'),
(184, '269 Maria Ines Simoes Diniz', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:41:38'),
(185, '270 Ana Maria Cansoni Padula', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:42:05'),
(186, '271 Alicia Rafaeli Martins', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:42:28'),
(187, '272 Maria Luiza Gonçalves Lima', '1951-12-02', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:43:14'),
(188, '273 Cristina Harumi Fugi', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:43:41'),
(189, '274 Luis Carlos Alves', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:44:10'),
(190, '275 Cristiane Trindade Gonçalves', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:44:43'),
(191, '276 Joao Pedro MartinS', '2007-11-24', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:58:14'),
(192, '277 Gleidson Caravanti', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 19:59:52'),
(193, '278 Rinaldo Barbosa', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:00:22'),
(194, '279 Acir Pereira da Silva', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:00:55'),
(195, '195 Neusa Benanti Fioroto', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:15:15'),
(196, '196 Valquiria de Fátima Souza da Costa', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:15:43'),
(197, '197 Angela Santos Silva', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:16:04'),
(198, '198 Gislaine da SIlva Santos', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:16:29'),
(199, '199 Julia Cagnin Ribeiro Costa', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:17:01'),
(200, '201 Andrea Sonoda Ywahara Bittes', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:17:42'),
(201, '202 Bruno Martins Bittes', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:18:05'),
(202, '203 Milena Ercole Fiorusse', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:18:30'),
(203, '204 Janaina de Fatima dos reis Gaiarim', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:19:23'),
(204, '205 Maria do Carmo Franco', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:20:10'),
(205, '206 Sonia Regina', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:22:17'),
(206, '207 José Yoshimara', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:22:37'),
(207, '208 Jaqueline de Fatima Fuzete', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:23:09'),
(208, '209 Maria Luiza Gouvea Primiano', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:23:32'),
(209, '210 Ivone Gomes Bastos', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:23:57'),
(210, '211 Felipe Burgo Batata', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:24:19'),
(211, '212 Heloisa Roberto de Poli', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:24:42'),
(212, '213 Samuel', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:25:01'),
(213, '214 Janaina da Silva Alves Vilalva', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:25:28'),
(214, '215 Pedro Mota', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:25:45'),
(215, '216 Manuella Mota', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:26:13'),
(216, '217 Marcos Vinicius Siqueira da Silva', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:26:48'),
(217, '218 Marcela dos Santos Matias', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:27:15'),
(218, '219 Eliane Soares Evangelista Severino', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:27:41'),
(219, '220 Luiz Despo Sindor Pereira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:29:10'),
(220, '221 Everaldo Jose Farias', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:29:32'),
(221, '222 Maria Aparecida Bragadini', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:30:13'),
(222, '223 Heitor Palermo Schneiderit', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:30:52'),
(223, '224 Raphael Pandini A. Cardia', '2007-01-12', '', '', '', '', '43271443807', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:34:10'),
(224, '225 Adriana', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:35:53'),
(225, '226 Leandro Cavalaro', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:41:19'),
(226, '226 Leandro Cavalaro', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:41:24'),
(227, '227 Daniela Candido', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:41:45'),
(228, '228 Terezinha Cansoni', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:42:05'),
(229, '229 Angélica C. Roin Ferreira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:42:45'),
(230, '230 Miguel - janaina', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:43:26'),
(231, '231 Ana Livia Panini de Moraes', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:43:48'),
(232, '232 Wagner', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:44:06'),
(233, '233 Adriana Augusta Herreira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:44:26'),
(234, '176 Silmara de Oliveira Ferreira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:53:48'),
(235, '177 Gabriel de Souza Tessaroto', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:54:48'),
(236, '178 karolina Sarti Orsi', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:55:13'),
(237, '179 Milton walsiner de Lima', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:55:44'),
(238, '180 Rodrigo Colombo', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:56:34'),
(239, '181 Raquel Francischini Colombo', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:57:08'),
(240, '182 Micalea da Costa Pereira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:57:38'),
(241, '183 Ana Flávia Colombo Edeumanm', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:58:28'),
(242, '184 Adriana Nelis Vieira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:58:52'),
(243, '185 Wesley Queiroz', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:59:16'),
(244, '186 Mariele Silva Barbosa', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 20:59:42'),
(245, '187 Anna Elena Colombo Spironelli', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:00:08'),
(246, '188 William Dias Gomes', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:00:27'),
(247, '189 Camila Sebastiano Soares', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:00:50'),
(248, '190 Matheus Araujo Tonon', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:03:24'),
(249, '191 Guilherme Amaral', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:03:45'),
(250, '191 Guilherme Amaral', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:03:45'),
(251, '191 Guilherme Amaral', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:03:45'),
(252, '192 Priscila Bennati Santana', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:05:34'),
(253, '193 Gabriela Assalim Pereira', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-26 21:06:06'),
(254, '194 Henrique Vieira Alcantara', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-27 12:53:14'),
(255, '365 Tainá Marques de Abreu', '1998-02-03', 'Feminino', 'solteira', 'atendente', '563157240', '48474384850', 'Victor Bombonatti ', '16050-240', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-29 14:10:36'),
(256, '81 Angelo Souza Tavares Junior', '1991-01-10', '', '', '', '354989765', '36811218851', '', '', '', '', '', '', '', '', 'omeprazol, paracetamol', '', 'Hipertensão, Problemas Cardíacos, Outros: disfunção mitral', '', '', '', '', '', '', '', '', '2026-06-29 14:29:34'),
(257, '67 Jaqueline dos Santos Casoni Borges', '1988-06-04', '', '', '', '401794052', '36853991877', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-06-29 14:30:39'),
(258, '366 Milena Medeiros Martins', '1978-09-02', 'Feminino', 'divorciada', 'professora', '234056836', '27444782881', 'Paulo Palpites, 420 Araçatuba G\r\nAraçatuba / SP', '16012817', '', '', 'Bariátrica há 12 anos', '', '', 'Puran', '', '', '', '', '', '', '', '', '', '', '', '2026-07-03 13:27:32'),
(259, '368 Priscila Vela Correia Souza', '1988-11-02', 'Feminino', '', '', '408202282', '35548254847', 'José Cazerta 1328', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-06 13:20:00'),
(260, '376 Danilo Faquiano', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-14 20:45:16'),
(261, '370 Vitor Scardovelli de Almeida', '1998-11-29', '', '', '', '', '46464302831', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-17 19:02:25'),
(262, '369 Marcos Boer', '1967-03-23', '', '', '', '', '06165644813', '', '', '', '', 'problema cardiaco', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-17 20:01:56'),
(263, 'Sem Cadastro', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-21 15:14:49'),
(264, '375 Claudia Martins Barbosa', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-21 19:47:30'),
(265, '372 Jaqueline Mendes', '0001-01-01', '', '', '', '', '00000000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-21 20:00:08'),
(266, 'Marisa Boer', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-23 21:03:21'),
(267, 'Marisa Boer', '0001-01-01', '', '', '', '', '00000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-23 21:03:21'),
(268, '367 Amanda de Almeida Zanardeli Prado', '0001-01-01', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-25 12:54:46'),
(269, '377 Lourdes Aguiar', '0001-01-01', '', '', '', '', '0000000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-28 20:00:24'),
(270, '378 Elena Santana Machado', '1966-09-08', '', '', '', '', '02302406826', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-29 20:25:14'),
(271, 'Vera Stivanelli', '0001-01-01', '', '', '', '', '00000000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-31 13:00:06'),
(272, 'Vera Stivanelli', '0001-01-01', '', '', '', '', '00000000000000', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-07-31 13:00:06'),
(273, '379 Elisabete Aparecida Moreno', '1961-04-06', 'Feminino', '', '', '', '11988820855', 'Rua Julio Monteagudo Pinheiro n 195 , Jd das Palmeiras', '', '18 99167-9595', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-08-05 17:45:16');
INSERT INTO `prontuarios` (`id`, `paciente`, `nascimento`, `sexo`, `estado_civil`, `profissao`, `rg`, `cpf`, `endereco`, `cep`, `telefone`, `email`, `observacoes`, `tratamento_odonto`, `tratamento_medico`, `medicamento_continuo`, `alergia_medicamento`, `alergia_outras`, `problemas_saude`, `gravida_meses`, `fuma_tempo`, `fuma_cigarros_dia`, `bebida_frequencia`, `drogas_uso`, `doencas_transmissiveis`, `cancer_familiar`, `tratamento_cancer`, `created_at`) VALUES
(274, '379 Elisabete Aparecida Moreno', '1961-04-06', 'Feminino', '', '', '', '11988820855', 'Rua Julio Monteagudo Pinheiro n 195 , Jd das Palmeiras', '', '18 99167-9595', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '2026-08-05 17:45:17');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paciente_id` (`paciente_id`);

--
-- Índices para tabela `estoque`
--
ALTER TABLE `estoque`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `orcamentos`
--
ALTER TABLE `orcamentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paciente_id` (`paciente_id`);

--
-- Índices para tabela `orcamentos_itens`
--
ALTER TABLE `orcamentos_itens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orcamento_id` (`orcamento_id`);

--
-- Índices para tabela `parcelas`
--
ALTER TABLE `parcelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orcamento` (`orcamento_id`);

--
-- Índices para tabela `procedimentos`
--
ALTER TABLE `procedimentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `paciente_id` (`paciente_id`);

--
-- Índices para tabela `prontuarios`
--
ALTER TABLE `prontuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT de tabela `estoque`
--
ALTER TABLE `estoque`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `orcamentos`
--
ALTER TABLE `orcamentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de tabela `orcamentos_itens`
--
ALTER TABLE `orcamentos_itens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT de tabela `parcelas`
--
ALTER TABLE `parcelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT de tabela `procedimentos`
--
ALTER TABLE `procedimentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT de tabela `prontuarios`
--
ALTER TABLE `prontuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=275;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `agendamentos`
--
ALTER TABLE `agendamentos`
  ADD CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `orcamentos`
--
ALTER TABLE `orcamentos`
  ADD CONSTRAINT `orcamentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `orcamentos_itens`
--
ALTER TABLE `orcamentos_itens`
  ADD CONSTRAINT `orcamentos_itens_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `parcelas`
--
ALTER TABLE `parcelas`
  ADD CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`orcamento_id`) REFERENCES `orcamentos` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `procedimentos`
--
ALTER TABLE `procedimentos`
  ADD CONSTRAINT `procedimentos_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `prontuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
