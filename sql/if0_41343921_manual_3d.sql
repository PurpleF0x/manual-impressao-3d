-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql303.infinityfree.com
-- Tempo de geração: 08-Abr-2026 às 07:10
-- Versão do servidor: 11.4.10-MariaDB
-- versão do PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `if0_41343921_manual_3d`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'Login efetuado: admin', '::1', '2026-03-05 15:47:55'),
(2, 1, 'logout', 'Logout efetuado', '::1', '2026-03-05 15:49:34'),
(3, 2, 'register', 'Novo registo: Martim', '::1', '2026-03-05 15:49:49'),
(4, 2, 'login', 'Login efetuado: Martim', '::1', '2026-03-05 15:50:00'),
(5, 2, 'comment_created', 'comment_id=4', '::1', '2026-03-05 15:50:25'),
(6, 2, 'logout', 'Logout efetuado', '::1', '2026-03-05 15:50:29'),
(7, 1, 'login', 'Login efetuado: admin', '::1', '2026-03-05 15:50:40'),
(8, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-09 11:12:28'),
(9, 1, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-09 11:12:43'),
(10, 3, 'register', 'Novo registo: Rodrigo', '188.82.10.143', '2026-03-09 11:13:08'),
(11, 3, 'login', 'Login efetuado: Rodrigo', '188.82.10.143', '2026-03-09 11:13:32'),
(12, 3, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-09 11:13:59'),
(13, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-09 11:14:08'),
(14, 3, 'login', 'Login efetuado: Rodrigo', '193.236.52.15', '2026-03-09 13:18:20'),
(15, 3, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-09 13:18:29'),
(16, NULL, 'password_reset_requested', 'email=martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-09 13:18:49'),
(17, NULL, 'password_reset_completed', 'email=martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-09 13:19:19'),
(18, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-09 13:19:38'),
(19, 2, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-09 13:21:19'),
(20, NULL, 'password_reset_requested', 'email=martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-09 13:21:25'),
(21, NULL, 'password_reset_requested', 'email=martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-09 13:22:55'),
(22, NULL, 'password_reset_requested', 'email=martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-09 13:23:06'),
(23, 2, 'login', 'Login efetuado: Martim', '94.61.204.127', '2026-03-09 18:47:44'),
(24, 2, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-09 18:52:20'),
(25, NULL, 'password_reset_requested', 'email=martimsa.28473@ae-smfeira.pt', '94.61.204.127', '2026-03-09 19:11:05'),
(26, NULL, 'password_reset_requested', 'email=martimt60@gmail.com', '94.61.204.127', '2026-03-09 19:12:23'),
(27, NULL, 'password_reset_requested', 'email=martimt60@gmail.com', '94.61.204.127', '2026-03-09 19:28:23'),
(28, NULL, 'login_failed', 'Tentativa falhada: Admin', '148.69.5.32', '2026-03-10 11:15:50'),
(29, 1, 'login', 'Login efetuado: admin', '148.69.5.32', '2026-03-10 11:16:01'),
(30, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-11 11:20:47'),
(31, NULL, 'login_failed', 'Tentativa falhada: martimsa.28473@ae-smfeira.pt', '193.236.52.15', '2026-03-11 11:21:44'),
(32, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-11 11:21:58'),
(33, 2, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-11 11:22:23'),
(34, NULL, 'login_failed', 'Tentativa falhada: admin', '193.236.52.15', '2026-03-11 11:22:35'),
(35, NULL, 'login_failed', 'Tentativa falhada: admin', '193.236.52.15', '2026-03-11 11:22:45'),
(36, NULL, 'login_failed', 'Tentativa falhada: admin', '193.236.52.15', '2026-03-11 11:22:54'),
(37, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-11 11:23:02'),
(38, 2, 'comment_created', 'comment_id=5,status=pendente', '193.236.52.15', '2026-03-11 11:28:19'),
(39, 2, 'comment_created', 'comment_id=6,status=pendente', '193.236.52.15', '2026-03-11 12:07:57'),
(40, 2, 'comment_blocked_blacklist', 'word=violar', '193.236.52.15', '2026-03-11 12:15:08'),
(41, 2, 'comment_blocked_blacklist', 'word=violar', '193.236.52.15', '2026-03-11 12:15:50'),
(42, 2, 'comment_blocked_blacklist', 'word=violar', '193.236.52.15', '2026-03-11 12:16:13'),
(43, 2, 'comment_created', 'comment_id=7,status=pendente', '193.236.52.15', '2026-03-11 12:16:26'),
(44, 4, 'register', 'Novo registo: felyr', '188.82.10.143', '2026-03-12 14:55:56'),
(45, 4, 'login', 'Login efetuado: felyr', '188.82.10.143', '2026-03-12 14:56:16'),
(46, 4, 'comment_created', 'comment_id=8,status=pendente', '188.82.10.143', '2026-03-12 15:02:18'),
(47, 4, 'comment_created', 'comment_id=9,status=pendente', '188.82.10.143', '2026-03-12 15:02:44'),
(48, 4, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-12 15:14:51'),
(49, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-12 15:15:01'),
(50, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-13 08:52:01'),
(51, 1, 'user_reported', 'reported_id=2,reason=outro', '193.236.52.15', '2026-03-13 09:09:29'),
(52, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-13 09:10:35'),
(53, 1, 'user_reported', 'reported_id=4,reason=informacao_falsa', '193.236.52.15', '2026-03-13 09:23:25'),
(54, 2, 'user_reported', 'reported_id=1,reason=informacao_falsa', '193.236.52.15', '2026-03-13 09:34:12'),
(55, 2, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-13 14:41:23'),
(56, NULL, 'login_failed', 'Tentativa falhada: martimt60@gmail.com', '188.82.10.143', '2026-03-13 14:42:35'),
(57, NULL, 'login_failed', 'Tentativa falhada: martimt60@gmail.com', '188.82.10.143', '2026-03-13 14:42:42'),
(58, 3, 'login', 'Login efetuado: Rodrigo', '188.82.10.143', '2026-03-13 14:42:46'),
(59, 3, 'comment_created', 'comment_id=10,status=pendente', '188.82.10.143', '2026-03-13 14:44:27'),
(60, 3, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-13 14:45:30'),
(61, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-13 14:45:40'),
(62, 2, 'user_reported', 'reported_id=3,reason=linguagem_ofensiva', '188.82.10.143', '2026-03-13 14:46:05'),
(63, 1, 'user_warning', 'target=3', '188.82.10.143', '2026-03-13 14:46:51'),
(64, 2, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-13 14:47:01'),
(65, 3, 'login', 'Login efetuado: Rodrigo', '188.82.10.143', '2026-03-13 14:47:14'),
(66, 1, 'user_reported', 'reported_id=3,reason=informacao_falsa', '188.82.10.143', '2026-03-13 15:11:26'),
(67, 1, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-13 16:07:31'),
(68, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-13 16:08:48'),
(69, 1, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-13 17:26:35'),
(70, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-13 17:26:44'),
(71, 2, 'comment_reported', 'comment_id=10,reason=desinformacao', '193.236.52.15', '2026-03-13 17:31:02'),
(72, 2, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-13 17:31:08'),
(73, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-13 17:31:17'),
(74, 1, 'comment_reported', 'comment_id=8,reason=desinformacao', '188.82.10.143', '2026-03-16 09:54:10'),
(75, 1, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-16 12:40:29'),
(76, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-16 12:40:48'),
(77, 1, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-17 16:00:54'),
(78, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-17 16:01:11'),
(79, 1, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-17 16:01:16'),
(80, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-17 16:01:34'),
(81, 1, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-17 16:01:59'),
(82, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-17 16:02:37'),
(83, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-17 16:28:33'),
(84, 2, 'login', 'Login efetuado: Martim', '193.236.52.15', '2026-03-18 10:50:21'),
(85, 2, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-18 11:56:25'),
(86, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-18 11:56:32'),
(87, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-19 15:51:46'),
(88, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-19 15:51:54'),
(89, 1, 'login', 'Login efetuado: admin', '188.82.10.143', '2026-03-20 14:48:36'),
(90, 1, 'login', 'Login efetuado: admin', '193.236.52.15', '2026-03-20 15:57:44'),
(91, 1, 'comment_created', 'comment_id=11,status=pendente', '193.236.52.15', '2026-03-20 16:06:56'),
(92, 1, 'login', 'Login efetuado: master', '94.61.204.127', '2026-03-20 18:35:11'),
(93, NULL, 'login_failed', 'Tentativa falhada: Martim Sá', '94.61.204.127', '2026-03-20 18:39:04'),
(94, NULL, 'login_failed', 'Tentativa falhada: Martim Sá', '94.61.204.127', '2026-03-20 18:39:09'),
(95, 2, 'login', 'Login efetuado: Martim', '94.61.204.127', '2026-03-20 18:39:34'),
(96, 2, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-20 20:30:20'),
(97, 3, 'login', 'Login efetuado: Rodrigo', '94.61.204.127', '2026-03-20 20:30:48'),
(98, 3, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-20 22:18:33'),
(99, 2, 'login', 'Login efetuado: Martim', '94.61.204.127', '2026-03-20 22:18:45'),
(100, 1, 'login', 'Login efetuado: master', '94.61.204.127', '2026-03-21 20:07:54'),
(101, 1, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-21 21:28:59'),
(102, 3, 'login', 'Login efetuado: Rodrigo', '94.61.204.127', '2026-03-21 21:29:24'),
(103, 3, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-21 21:38:57'),
(104, 3, 'login', 'Login efetuado: Rodrigo', '94.61.204.127', '2026-03-21 21:41:39'),
(105, 3, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-21 21:45:15'),
(106, 1, 'login', 'Login efetuado: master', '188.82.10.143', '2026-03-24 15:52:01'),
(107, 1, 'logout', 'Logout efetuado', '188.82.10.143', '2026-03-24 15:57:49'),
(108, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-24 15:58:01'),
(109, 2, 'logout', 'Logout efetuado', '193.236.52.15', '2026-03-27 09:01:49'),
(110, 3, 'login', 'Login efetuado: Rodrigo', '193.236.52.15', '2026-03-27 09:02:05'),
(111, 2, 'login', 'Login efetuado: Martim', '188.82.10.143', '2026-03-27 13:57:36'),
(112, 2, 'login', 'Login efetuado: Martim', '94.61.204.127', '2026-03-28 21:43:10'),
(113, 2, 'logout', 'Logout efetuado', '94.61.204.127', '2026-03-28 22:34:19'),
(114, NULL, 'login_failed', 'Tentativa falhada: master', '94.61.204.127', '2026-03-28 22:34:27'),
(115, 1, 'login', 'Login efetuado: master', '94.61.204.127', '2026-03-28 22:34:32'),
(116, 1, 'login', 'Login efetuado: master', '94.61.204.127', '2026-03-30 13:14:17'),
(117, 1, 'login', 'Login efetuado: master', '94.61.204.127', '2026-04-04 16:50:11');

-- --------------------------------------------------------

--
-- Estrutura da tabela `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `actor_id`, `target_id`, `action`, `detail`, `created_at`) VALUES
(1, 1, 2, 'coins', '+500 moedas', '2026-03-20 22:17:28'),
(2, 1, NULL, 'delete_community', 'Comunidade #2: Comunidade +18', '2026-03-20 22:53:19'),
(3, 1, 2, 'warn', 'Aviso: ', '2026-03-28 22:41:07');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ai_conversations`
--

CREATE TABLE `ai_conversations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `section_context` varchar(50) DEFAULT NULL,
  `mode` varchar(10) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `ai_conversations`
--

INSERT INTO `ai_conversations` (`id`, `user_id`, `title`, `section_context`, `mode`, `created_at`, `updated_at`) VALUES
(1, 2, 'Qual a melhor impressora para iniciantes até 300€?', NULL, 'advanced', '2026-03-27 08:02:09', '2026-03-27 08:03:27'),
(3, 2, 'Olá', NULL, 'beginner', '2026-03-28 15:33:04', '2026-03-28 15:33:09'),
(4, 1, 'Simön Amarantus\nera um explorador e historiador francês que …', NULL, 'beginner', '2026-03-30 07:34:07', '2026-03-30 07:34:08'),
(5, 1, 'Vou mandar-te uma lore partida em várias mensagens e tens de…', NULL, 'beginner', '2026-03-30 07:35:39', '2026-03-30 17:56:51'),
(6, 1, 'Qual é a melhor raça em Sailo Piece?', NULL, 'beginner', '2026-04-04 09:50:41', '2026-04-04 09:51:37'),
(7, 1, 'Sabes IA para imagens?', NULL, 'beginner', '2026-04-04 22:36:43', '2026-04-04 22:36:45');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ai_messages`
--

CREATE TABLE `ai_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `role` enum('user','assistant') NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `ai_messages`
--

INSERT INTO `ai_messages` (`id`, `conversation_id`, `role`, `content`, `created_at`) VALUES
(1, 1, 'user', 'Qual a melhor impressora para iniciantes até 300€?', '2026-03-27 08:02:10'),
(2, 1, 'assistant', '**Impressoras 3D para Iniciantes até 300€**\n\nExistem várias opções de impressoras 3D para iniciantes a preços acessíveis. Aqui estão algumas sugestões:\n\n### Monoprice Select Mini V2\n- Preço: cerca de 120€\n- Tecnologia: FDM\n- Resolução: 50-100 micras\n- Tamanho: 10 x 7,5 x 5,5 cm\n- Peso: 1,5 kg\n- Características: fácil de usar, compacta, baixo custo\n\n### Creality Ender 3\n- Preço: cerca de 150€\n- Tecnologia: FDM\n- Resolução: 50-100 micras\n- Tamanho: 24 x 22 x 30 cm\n- Peso: 5 kg\n- Características: fácil de montar, personalizável, grande tamanha de impressão\n\n### Prusa i3 DIY Kit\n- Preço: cerca de 200€\n- Tecnologia: FDM\n- Resolução: 50-100 micras\n- Tamanho: 26 x 26 x 40 cm\n- Peso: 5 kg\n- Características: fácil de montar, personalizável, alta qualidade de impressão\n\n### Anycubic Mega\n- Preço: cerca de 250€\n- Tecnologia: FDM\n- Resolução: 50-100 micras\n- Tamanho: 22 x 20 x 30 cm\n- Peso: 4,5 kg\n- Características: fácil de usar, grande tamanha de impressão, qualidade de impressão boa\n\n**Observação:** os preços podem variar dependendo da loja e do fornecedor.\n\n**Dicas para Iniciantes:**\n\n* Inicie com uma impressora compacta e fácil de usar, como a Monoprice Select Mini V2.\n* Leia as instruções de montagem e manutenção cuidadosamente.\n* Exija uma boa qualidade do material de impressão (filamento).\n* Pratique e experimente diferentes configurações e projetos.\n\n**Importante:** as impressoras 3D podem variar em qualidade e desempenho, mesmo dentro de uma mesma categoria. É importante ler as avaliações e revisões de outros usuários antes de fazer uma compra.', '2026-03-27 08:02:10'),
(3, 1, 'user', 'Podes resumir?', '2026-03-27 08:02:20'),
(4, 1, 'assistant', '**Impressoras 3D para Iniciantes até 300€**\n\nAs seguintes opções são recomendadas:\n\n* **Monoprice Select Mini V2**: fácil de usar, compacta, baixo custo (120€)\n* **Creality Ender 3**: fácil de montar, personalizável, grande tamanho de impressão (150€)\n* **Prusa i3 DIY Kit**: fácil de montar, personalizável, alta qualidade de impressão (200€)\n* **Anycubic Mega**: boa qualidade de impressão, fácil de usar, preço acessível (250€)\n\nEssas impressoras são todas FDM e têm uma resolução de 50-100 micras.', '2026-03-27 08:02:20'),
(5, 1, 'user', 'Elas são vendidas em Portugal?', '2026-03-27 08:02:40'),
(6, 1, 'assistant', '**Disponibilidade em Portugal**\n\nAs impressoras 3D que mencionei anteriormente estão disponíveis em Portugal, mas é importante verificar a disponibilidade e os preços em diferentes lojas online e físicas.\n\nAlgumas opções populares para comprar impressoras 3D em Portugal incluem:\n\n* **Amazon Portugal**\n* **Fnac Portugal**\n* **Media Markt Portugal**\n* **Lojas especializadas em tecnologia e impressão 3D**\n\nVocê também pode procurar em lojas online internacionais que enviem para Portugal, como a **Amazon EU** ou a **eBay**.\n\nLembre-se de verificar os preços, as especificações e as avaliações dos produtos antes de fazer uma compra.', '2026-03-27 08:02:40'),
(7, 1, 'user', 'O que muda o modo básico com o modo avançado aqui na conversa?', '2026-03-27 08:03:27'),
(8, 1, 'assistant', '**Modo Básico vs Modo Avançado**\n\nNo modo básico, respondo de forma mais geral e acessível, utilizando linguagem simples e evitando termos técnicos complexos.\n\nNo modo avançado, respondo de forma mais específica e técnica, utilizando linguagem mais avançada e termos em inglês quando necessário. Isso significa que:\n\n* Em modo básico, uso uma linguagem mais simples e evito termos técnicos complexos.\n* Em modo avançado, uso uma linguagem mais técnica e adoto uma abordagem mais especializada.\n\nPor exemplo, em modo básico, eu diria \"a impressora 3D é uma máquina que cria objetos a partir de filamentos\" e em modo avançado, eu diria \"a impressora 3D é uma máquina FDM que utiliza a tecnologia de fusão por extrusão para criar objetos a partir de filamentos de polímero\".\n\nNo caso da nossa conversa anterior, não houve uma grande diferença entre o modo básico e o modo avançado, pois as perguntas e respostas foram relativamente simples. No entanto, se você tiver perguntas mais técnicas ou específicas, posso mudar para o modo avançado para fornecer respostas mais detalhadas e precisas.', '2026-03-27 08:03:27'),
(9, 2, 'user', 'Qual a temperatura ideal para imprimir PETG?', '2026-03-28 14:52:02'),
(10, 2, 'assistant', '**Temperatura Ideal para PETG**\n\nA temperatura ideal para imprimir PETG (Polietileno Terfital) depende da impressora 3D e da configuração utilizada, mas aqui estão as temperaturas recomendadas:\n\n* **Cabeça de Impressão (Nozzle)**: Entre 220°C e 250°C. Uma temperatura de 230°C é uma boa escolha para a maioria das impressoras.\n* **Plataforma de Impressão (Bed)**: Entre 50°C e 80°C. Uma temperatura de 60°C é comum para PETG.\n* **Câmara de Impressão (Chamber)**: Não é necessário, mas pode ajudar a manter a temperatura constante.\n\nLembre-se de que essas temperaturas são apenas uma recomendação. É importante experimentar e ajustar as temperaturas para encontrar a combinação ideal para a sua impressora e material.\n\n**Notas Importantes**\n\n* PETG é um material mais tolerante à temperatura do que o PLA, mas ainda assim é importante manter as temperaturas dentro do range recomendado.\n* Se a temperatura for muito alta, o material pode derreter demais e causar problemas de impressão.\n* Se a temperatura for muito baixa, o material pode não derreter suficientemente e causar problemas de impressão.\n\nLembre-se de sempre seguir as instruções do fabricante da impressora e do material para garantir as melhores condições de impressão.', '2026-03-28 14:52:02'),
(11, 2, 'user', 'Consegues dizer quanto tempo entre a ultima mensagem e esta?', '2026-03-28 15:27:31'),
(12, 2, 'assistant', 'A minha última resposta foi enviada há 9 segundos.', '2026-03-28 15:27:31'),
(13, 2, 'user', 'Para mim passou 8horas', '2026-03-28 15:27:46'),
(14, 2, 'assistant', 'Peço desculpas pelo erro! A minha resposta anterior foi gerada automaticamente, mas não tenho uma visão do tempo real. A diferença de 8 horas entre a minha resposta e a sua percepção é completamente normal, pois estou a funcionar com um sistema de data e hora pré-definido, que não está sincronizado com o seu relógio.', '2026-03-28 15:27:46'),
(15, 2, 'user', 'Okay', '2026-03-28 15:28:02'),
(16, 2, 'assistant', 'Tudo bem! Estou aqui para ajudar. Vamos continuar com as perguntas sobre impressão 3D!', '2026-03-28 15:28:02'),
(17, 3, 'user', 'Olá', '2026-03-28 15:33:04'),
(18, 3, 'assistant', 'Olá! Sou o Print AI, o seu assistente oficial para tudo sobre impressão 3D. Como posso ajudar-te hoje?', '2026-03-28 15:33:04'),
(19, 3, 'user', 'Stringing — como resolver?', '2026-03-28 15:33:09'),
(20, 3, 'assistant', 'A stringing é um problema comum na impressão 3D FDM!\n\nA stringing acontece quando o fio de material (filamento) se estende além da área de impressão, criando uma \"cabelo\" ou \"rede\" entre o objeto e a base da impressora. Isso acontece porque o fio não está a ser retirado adequadamente durante a impressão.\n\nAqui estão algumas dicas para resolver a stringing:\n\n### 1. **Aumentar a temperatura da impressora**\n\n* Se a temperatura da impressora estiver baixa, o fio pode não estar a derreter completamente, o que pode causar stringing.\n* Tente aumentar a temperatura da impressora em 5-10°C e veja se isso melhora a situação.\n\n### 2. **Aumentar a velocidade de impressão**\n\n* Se a velocidade de impressão estiver muito baixa, o fio pode não estar a ser retirado adequadamente, o que pode causar stringing.\n* Tente aumentar a velocidade de impressão em 10-20% e veja se isso melhora a situação.\n\n### 3. **Reduzir a temperatura do noz**\n\n* Se a temperatura do noz (ou extrusora) estiver muito alta, pode causar stringing.\n* Tente reduzir a temperatura do noz em 5-10°C e veja se isso melhora a situação.\n\n### 4. **Ajustar as configurações do slicer**\n\n* Verifique as configurações do slicer (como Cura, Simplify3D, etc.) e ajuste as seguintes opções:\n + **Layer Adhesion**: aumente a adesão entre as camadas.\n + **Retraction**: aumente a retração do fio.\n + **Temperature**: ajuste as temperaturas da impressora e do noz.\n\n### 5. **Usar um novo filamento**\n\n* Se estiver a usar um filamento de baixa qualidade, isso pode causar stringing.\n* Tente usar um filamento de alta qualidade e veja se isso melhora a situação.\n\nLembre-se de que cada impressora e filamento é diferente, então é importante experimentar e ajustar as configurações até encontrar o que funciona melhor para você!', '2026-03-28 15:33:09'),
(21, 4, 'user', 'Simön Amarantus\nera um explorador e historiador francês que na manhã de 10 de Outubro de 1865 ao investigar uma mansão abandonada da antiga família Nostradamus encontra uma passagem secreta ao tentar tirar um dos variados livros da parte de adivinhação e sacrifício. Essa passagem secreta era escura até dar o primeiro passo na escada, às tochas na parede acendem e revelam uma escadaria grande que leva a uma biblioteca ainda maior que a anterior. Nessa biblioteca Simön encontra livros de história criados por Nostradamus relatando vários tipos de aventuras e relatos de visões vividas e que aconteceram no futuro. Simön fica um tempo a ler alguns livros e depois encontra alguns livros finos e outros grossos e ao ver descobre que são possíveis grimórios, ao ler os livros mais pequenos ele descobre o que cada símbolo no grimório faz e o que é preciso para eles funcionarem. Antes de sair pegou em alguns livros mais interessantes para pesquisar e decidiu guarda-los para estuda-los em casa com cuidado. Ele continuo lá por umas horas até que quando saiu parecia estar na mesma hora do dia que entrou. Saiu da passagem e ela fechou-se. Foi para o carro e quando chegou a casa estudou os livros que pegou. Mas no dia seguinte quando voltou à mansão, ela tinha desaparecido. Desde então, Simön(26) procurou respostas entre ocultistas franceses menos conhecidos, mas não encontrou nenhuma resposta. Mas para descobrir mais, ele estudou vários rituais durante vários anos até criar um ritual que ele denominou como: \"Possessão\". Apartir dai ele pediu para testar o ritual dele numa das novas pessoas do culto. Contudo, o ritual correu mal e ele acabou por polo com a face sireita da cara paralisada e algumas memórias baralhadas.\nPara ninguém descobrir ele adotou outro nome: \n\"Isaak Velvet\" \n\nDiz-me como eu modelei esta lore?', '2026-03-30 07:34:08'),
(22, 4, 'assistant', '**Modelagem de Lore: Um Exemplo de Simôn Amarantus**\n\nA tua pergunta é interessante! Embora não seja directamente relacionada com a impressão 3D, vou tentar ajudar-te a analisar como modelaste esta lore.\n\n**Ponto de Partida**: A história começa com Simôn Amarantus, um explorador e historiador francês, que descobre uma passagem secreta em uma mansão abandonada.\n\n**Desenvolvimento da História**: Simôn encontra uma biblioteca com livros de história criados por Nostradamus, que relatam aventuras e visões do futuro. Ele descobre grimórios e começa a estudá-los.\n\n**Virada**: Simôn sai da mansão, mas ela desaparece no dia seguinte. Ele começa a estudar rituais ocultistas e cria um ritual chamado \"Possessão\", que corre mal e causa problemas a ele mesmo.\n\n**Adaptação e Mudança de Identidade**: Simôn adota o nome \"Isaak Velvet\" para esconder a sua verdadeira identidade e evitar que outras pessoas descubram o que aconteceu.\n\n**Análise da Modelagem de Lore**:\n\n* **Começo forte**: A história começa com um evento interessante e inesperado, o que puxa a atenção do leitor.\n* **Desenvolvimento lento**: A história desenvolve-se gradualmente, com Simôn a descobrir mais sobre a biblioteca e os grimórios.\n* **Virada inesperada**: A desaparecida da mansão e os problemas que Simôn enfrenta criam uma tensão e um suspense que mantêm o leitor envolvido.\n* **Adaptação e mudança de identidade**: A mudança de Simôn para Isaak Velvet cria uma nova direção para a história e abre caminho para possíveis desenvolvimentos futuros.\n\nEm resumo, a tua modelagem de lore é uma história envolvente com um começo forte, um desenvolvimento lento e uma virada inesperada. A adopção de uma nova identidade para Simôn cria uma nova direção para a história e mantém o leitor interessado.', '2026-03-30 07:34:08'),
(23, 5, 'user', 'Vou mandar-te uma lore partida em várias mensagens e tens depois dizer-me como que eu modelei as coisas, exemplo: Nome, Idade, Nascimento, profissão, história da vida, encontros, etc', '2026-03-30 07:35:40'),
(24, 5, 'assistant', 'Estou preparado para receber a lore partida. Vai em frente e partilha-me a história do teu personagem ou objeto que quiseres modelar. Estou aqui para te ajudar a entender como o modelaram. Estou ansioso para ver o que vais me enviar!', '2026-03-30 07:35:40'),
(25, 5, 'user', 'Vida Atual:\n -> Zoe(2007 - atual)\nVidas passadas:\n -> ( - )\n -> ( - )\n -> ( - )\n -> Isaak Velvet (1895 - )\n -> Simön Amarantus (1840 - 1895)\n \nSimön Amarantus\nera um explorador e historiador francês que na manhã de 10 de Outubro de 1865 ao investigar uma mansão abandonada da antiga família Nostradamus encontra uma passagem secreta ao tentar tirar um dos variados livros da parte de adivinhação e sacrifício. Essa passagem secreta era escura até dar o primeiro passo na escada, às tochas na parede acendem e revelam uma escadaria grande que leva a uma biblioteca ainda maior que a anterior. Nessa biblioteca Simön encontra livros de história criados por Nostradamus relatando vários tipos de aventuras e relatos de visões vividas e que aconteceram no futuro. Simön fica um tempo a ler alguns livros e depois encontra alguns livros finos e outros grossos e ao ver descobre que são possíveis grimórios, ao ler os livros mais pequenos ele descobre o que cada símbolo no grimório faz e o que é preciso para eles funcionarem. Antes de sair pegou em alguns livros mais interessantes para pesquisar e decidiu guarda-los para estuda-los em casa com cuidado. Ele continuo lá por umas horas até que quando saiu parecia estar na mesma hora do dia que entrou. Saiu da passagem e ela fechou-se. Foi para o carro e quando chegou a casa estudou os livros que pegou. Mas no dia seguinte quando voltou à mansão, ela tinha desaparecido. Desde então, Simön(26) procurou respostas entre ocultistas franceses menos conhecidos, mas não encontrou nenhuma resposta. Mas para descobrir mais, ele estudou vários rituais durante vários anos até criar um ritual que ele denominou como: \"Possessão\". Apartir dai ele pediu para testar o ritual dele numa das novas pessoas do culto. Contudo, o ritual correu mal e ele acabou por polo com a face sireita da cara paralisada e algumas memórias baralhadas.', '2026-03-30 07:36:36'),
(26, 5, 'assistant', 'Vamos analisar a lore partida e ver como poderíamos modelar as coisas.\n\n**Vida Atual:**\n\n* **Zoe**: Embora a história não forneça muitos detalhes sobre a vida atual de Zoe, podemos inferir que ela é uma pessoa importante para a história de Simön. Talvez ela seja a pessoa que vai descobrir as respostas que Simön procurou durante toda a sua vida.\n\n**Vidas Passadas:**\n\n* **Isaak Velvet**: Isaak é um personagem misterioso que viveu entre 1895 e...? Não há informações sobre a sua morte ou o que aconteceu com ele. Talvez ele seja um precursor de Simön ou tenha uma conexão com a família Nostradamus.\n* **Simön Amarantus**: Simön é o personagem central da história. Ele é um explorador e historiador francês que viveu entre 1840 e 1895. A sua história é uma mistura de aventuras, descobertas e rituais ocultos.\n* **Vidas passadas sem nome**: Existem mais duas vidas passadas mencionadas, mas não há informações sobre elas. Talvez sejam personagens importantes para a história de Simön ou apenas uma menção de que ele tem uma longa história.\n\n**A Mansão Nostradamus:**\n\n* A mansão é um local importante para a história de Simön. Ele é o primeiro a descobrir a passagem secreta e a biblioteca escondida.\n* A biblioteca é cheia de livros que contam histórias de aventuras e visões do futuro.\n* Simön encontra grimórios e aprende a usar os símbolos neles para realizar rituais.\n\n**O Ritual \"Possessão\":**\n\n* Simön cria um ritual chamado \"Possessão\" e tenta testá-lo numa pessoa do culto.\n* O ritual corre mal e Simön fica com a face paralisada e memórias baralhadas.\n\nAgora que analisamos a lore partida, podemos ver que a história de Simön é uma mistura de aventuras, descobertas e rituais ocultos. Ele é um personagem complexo que viveu várias vidas e tem uma longa história.', '2026-03-30 07:36:36'),
(27, 5, 'user', 'Para ninguém descobrir ele adotou outro nome: \n\"Isaak Velvet\" \n\nIsaak Velvet:\nUm adolescente de 16 anos com baixa estatura que é um teórico da conspiração que conheceu o ocultismo muito cedo na vida e que teve uma mudança drástica em conjunto. Um ocultista conhecido(Simön Amarantus) no culto decidiu o escolher como um experimento. E eu aceitei, pois tudo era novo para mim, fiquei muito entusiasmado por alguém ver valor em mim e quando cheguei vi uma pessoa deitada a desenhar um simbolo gigante com algumas luas, letras estranhas e uma cruz ao contrário com um grande risco na ponta da cruz. \nEle pediu-me para sentar numa cadeira e ele sentou-se na outra e de repente o desenho brilhou em amarelo e vermelho, acabei por fechar os olhos por causa do brilho e quando abri os olhos vi...\nSimön: Vi através dos olhos de Isaak, com uma forte dor do lado direito da cara e com as minhas memórias baralhadas com a dele. \nDiálogo mental:\nSimön: Funcionou?\nIsaak: Aí, o que aconteceu?\nSimön: Que? Estás na minha cabeça?\nIsaak: Não, tu estás no meu corpo e na minha cabeça.\nSimön: Deve ter corrido mal o ritual.\nIsaak: Ritual? Isto era um ritual? Que fixe. Sabes mais?\nSimön: Sei, estava a tentar criar um ritual a misturar um ritual de controlar mentes com um ritual de troca de corpos temporária.\nIsaak: Sério? Não sabia que existia rituais assim..., mas o que deu errado?\nSimön: Para falar a verdade; eu não sei direito. Este ritual não estava completo quando o encontrei. Então tentei ver outros rituais para tentar completa-lo.\nIsaak: Entendo. Vamos ter de viver assim então. Por mim tudo bem.\nSimön: É melhor ser assim. Afinal, poderia ter corrido bem mal.\nIsaak: Verdade. Ahahah(Risos).\nFim de Diálogo mental;\"', '2026-03-30 07:37:59'),
(28, 5, 'assistant', 'Vamos analisar a história de Isaak e Simön.\n\n**Modelagem de Isaak:**\n\n* **Idade:** 16 anos\n* **Altura:** Baixa estatura\n* **Personalidade:** Teórico da conspiração, entusiasmado, vulnerável\n* **História:** Conheceu o ocultismo cedo na vida e foi escolhido por Simön como experimento\n* **Experiência:** Foi possuído por Simön após um ritual de troca de corpos temporária, que não foi completo\n\n**Modelagem de Simön:**\n\n* **Idade:** Idade desconhecida (após 1840)\n* **Personalidade:** Ocultista, experimentador, calculador\n* **História:** Explorador e historiador francês que vivia no século XIX\n* **Poderes:** Conhecimento sobre rituais de controle de mentes e troca de corpos temporária\n* **Motivação:** Procurou criar um ritual para controlar mentes e trocar corpos temporariamente\n\n**Modelagem da relação entre Isaak e Simön:**\n\n* **Origem:** Simön escolheu Isaak como experimento\n* **Dinâmica:** Simön está dentro da mente e do corpo de Isaak, mas Isaak também está ciente da presença de Simön\n* **Objetivo:** Aumentar a compreensão de Simön sobre o ritual e encontrar uma maneira de reversar a situação', '2026-03-30 07:37:59'),
(29, 5, 'user', 'Isaak(1895-1950);\nEu estou a fazer o próximo corpo a ser sobreposto: Elara Voss(1950-2000)\n? Elara Voss — Resumo Geral\nIdade (quando conhece Isaak): ~21 anos\nProfissão: Restauradora de livros e documentos antigos\nEspecialidade: Textos raros, símbolos desconhecidos, materiais “anormais”\n\n? Personalidade\nCuriosa por natureza ? não aceita “não saber” como resposta\nCalma e analítica ? observa antes de agir\nDeterminada ? quando algo a intriga, não larga\nRacional… mas aberta ao impossível ? não acredita facilmente, mas também não ignora evidências\n\n? Não é impulsiva como o Isaak, nem fria como o Simön — fica no meio.\n\n? Relação com o oculto\nInicialmente: Interesse académico (símbolos, padrões, línguas)\nDepois: Fascínio real (“isto funciona mesmo”)\nMais tarde: Envolvimento ativo (aceita aprender e participar)\n\n? Ela não entra no paranormal por acidente — entra porque quer entender.\n\n?? Motivação\nBusca de conhecimento verdadeiro\nVontade de descobrir “o que está escondido”\nPossível insatisfação com a vida normal (implícito, não precisa ser explícito)\n\n? Papel na história\nPonte entre o mundo normal e o paranormal\nPrimeira pessoa “de fora” a lidar diretamente com Simön + Isaak\nEvolui de: observadora ? aprendiz ? participante\n?? Fraqueza principal: Curiosidade excessiva\n\n? Ela sabe que há risco…\nmas escolhe avançar na mesma.\n\n? Dinâmica com os outros\nCom Isaak\nMais leve, mais natural\nEle aproxima-a do lado humano\n\nCom Simön\nRespeito intelectual\nVê-o como alguém perigoso… mas fascinante\n\n? Traço marcante:\nElara não quer poder — quer compreender aquilo que ninguém consegue explicar\n\n? Em uma frase\nElara Voss é uma mente curiosa e disciplinada que escolhe entrar no paranormal não por erro, mas por vontade — e aceita o preço disso.', '2026-03-30 07:42:51'),
(30, 5, 'assistant', 'Vamos analisar o perfil de Elara Voss.\n\n**Modelagem de Elara Voss:**\n\n* **Idade:** ~21 anos (quando conhece Isaak)\n* **Profissão:** Restauradora de livros e documentos antigos\n* **Personalidade:**\n + Curiosa\n + Calma e analítica\n + Determinada\n + Racional, mas aberta ao impossível\n* **Relação com o oculto:**\n + Interesse académico inicial\n + Fascínio real subsequente\n + Envolvimento ativo posterior\n* **Motivação:**\n + Busca de conhecimento verdadeiro\n + Vontade de descobrir \"o que está escondido\"\n + Possível insatisfação com a vida normal\n* **Papel na história:**\n + Ponte entre o mundo normal e o paranormal\n + Primeira pessoa \"de fora\" a lidar diretamente com Simön + Isaak\n + Evolui de: observadora ? aprendiz ? participante\n* **Fraqueza principal:** Curiosidade excessiva\n* **Dinâmica com os outros:**\n + Com Isaak: leve e natural\n + Com Simön: respeito intelectual, vê-o como alguém perigoso, mas fascinante\n* **Traço marcante:** Não quer poder, quer compreender aquilo que ninguém consegue explicar\n\n**Modelagem de Elara em relação a Simön e Isaak:**\n\n* **Simön:** Elara vê Simön como alguém perigoso, mas fascinante. Ela respeita o seu intelecto e está interessada em aprender com ele.\n* **Isaak:** Elara se sente mais à vontade com Isaak, que é mais leve e natural. Ela pode se expressar livremente com ele e compartilhar suas ideias.\n\n**Modelagem de Elara como personagem:**\n\n* **Frase de apresentação:** \"Eu sou Elara Voss, uma restauradora de livros e documentos antigos com uma paixão por descobrir o desconhecido.\"\n* **Objetivo:** Descobrir o que está escondido e entender o paranormal.\n* **Conflito:** Fraqueza de curiosidade excessiva pode levar Elara a correr riscos que podem ser perigosos.', '2026-03-30 07:42:51'),
(31, 5, 'user', 'Achas que consegues criar a lore dela, comparando com a forma que eu fiz as outras lores?', '2026-03-30 07:43:33'),
(32, 5, 'assistant', 'Vou tentar criar a lore de Elara Voss comparando com as formas que você fez as outras lores.\n\n**Vida Atual:**\n\n* **Elara Voss**: É uma mulher de ~21 anos, com habilidades em restauração de livros e documentos antigos.\n* **Idade (quando conhece Isaak):** ~21 anos\n* **Profissão:** Restauradora de livros e documentos antigos\n* **Especialidade:** Textos raros, símbolos desconhecidos, materiais “anormais”\n\n**Vidas Passadas:**\n\n* **( - )**\n* **( - )**\n* **( - )**\n\n**Experiências Passadas:**\n\n* **Isaak Velvet**: Conheceu Isaak quando tinha ~21 anos e foi atraída por ele devido à sua personalidade teórica da conspiração e sua fascínio pelo oculto.\n\n**Relação com o Oculto:**\n\n* **Interesse Académico**: Começou a estudar símbolos, padrões e línguas antigas.\n* **Fascínio Real**: Depois de encontrar evidências de que o oculto \"funciona mesmo\", se tornou mais fascinada com o assunto.\n* **Envolvimento Ativo**: Mais tarde, se envolveu ativamente no estudo e prática do oculto.\n\n**Poderes e Capacidades:**\n\n* **Conhecimento**: Tem conhecimento sobre símbolos, padrões e línguas antigas.\n* **Análise**: É capaz de analisar e resolver problemas complexos.\n\n**Motivação:**\n\n* **Busca de Conhecimento**: Busca descobrir a verdade sobre o oculto e seus segredos.\n* **Vontade de Descobrir**: Quer descobrir \"o que está escondido\" e entender os segredos do universo.\n\n**Fraqueza Principal:**\n\n* **Curiosidade Excessiva**: Sua curiosidade excessiva a leva a arriscar demais e a se envolver em situações perigosas.\n\nEssa é a minha tentativa de criar a lore de Elara Voss comparando com as formas que você fez as outras lores.', '2026-03-30 07:43:33'),
(33, 5, 'user', 'Estou a dizer para fazer tipo o do Simon;\nSimön Amarantus\nera um explorador e historiador francês que na manhã de 10 de Outubro de 1865 ao investigar uma mansão abandonada da antiga família Nostradamus encontra uma passagem secreta ao tentar tirar um dos variados livros da parte de adivinhação e sacrifício. Essa passagem secreta era escura até dar o primeiro passo na escada, às tochas na parede acendem e revelam uma escadaria grande que leva a uma biblioteca ainda maior que a anterior. Nessa biblioteca Simön encontra livros de história criados por Nostradamus relatando vários tipos de aventuras e relatos de visões vividas e que aconteceram no futuro. Simön fica um tempo a ler alguns livros e depois encontra alguns livros finos e outros grossos e ao ver descobre que são possíveis grimórios, ao ler os livros mais pequenos ele descobre o que cada símbolo no grimório faz e o que é preciso para eles funcionarem. Antes de sair pegou em alguns livros mais interessantes para pesquisar e decidiu guarda-los para estuda-los em casa com cuidado. Ele continuo lá por umas horas até que quando saiu parecia estar na mesma hora do dia que entrou. Saiu da passagem e ela fechou-se. Foi para o carro e quando chegou a casa estudou os livros que pegou. Mas no dia seguinte quando voltou à mansão, ela tinha desaparecido. Desde então, Simön(26) procurou respostas entre ocultistas franceses menos conhecidos, mas não encontrou nenhuma resposta. Mas para descobrir mais, ele estudou vários rituais durante vários anos até criar um ritual que ele denominou como: \"Possessão\". Apartir dai ele pediu para testar o ritual dele numa das novas pessoas do culto. Contudo, o ritual correu mal e ele acabou por polo com a face sireita da cara paralisada e algumas memórias baralhadas.\nPara ninguém descobrir ele adotou outro nome:  \"Isaak Velvet\"', '2026-03-30 07:44:45'),
(34, 5, 'assistant', 'Aqui vai a lore de Elara Voss, escrita de forma semelhante à do Simön Amarantus:\n\n**Elara Voss**\n\nEra uma jovem de 21 anos que trabalhava como restauradora de livros e documentos antigos. Em 1975, enquanto investigava uma loja de antiguidades em Paris, descobriu uma passagem secreta escondida atrás de um quadro. A passagem levava a uma sala de estudo abandonada, onde encontrou uma vasta coleção de livros sobre ocultismo e magia. Ela passou horas lendo e explorando a sala, descobrindo que alguns dos livros eram grimórios antigos.\n\nQuando saiu da passagem, ela percebeu que o tempo parecia ter parado. A sala de estudo estava exatamente como ela a havia deixado, e a loja de antiguidades estava fechada, como se nada tivesse acontecido. Elara pegou alguns dos livros mais interessantes e decidiu estudá-los em casa, com cuidado.\n\nNo dia seguinte, quando voltou à loja de antiguidades, descobriu que a passagem secreta havia desaparecido. Ela procurou por respostas entre os ocultistas franceses, mas não encontrou nenhuma resposta. Desesperada por descobrir mais, Elara começou a estudar vários rituais de magia, até criar um ritual que ela denominou como: \"Transição\". Ela pediu ao seu grupo de amizades para testar o ritual, mas correu mal e ela acabou por perder a memória de alguns eventos importantes.\n\nPara ninguém descobrir o que havia acontecido, Elara adotou outro nome: \"Isaak Velvet\". Desde então, ela procurou respostas entre os ocultistas, mas nunca encontrou nenhuma resposta. Ela continua a perseguir a verdade, mas agora com um nome novo e um segredo pesado no coração.', '2026-03-30 07:44:45'),
(35, 5, 'user', 'Fizeste ela com a forma à do Amarantus. Agora que pegues ele como base.', '2026-03-30 07:46:31'),
(36, 5, 'assistant', 'Aqui vai a lore de Elara Voss, escrita de forma semelhante à do Simön Amarantus:\n\n**Elara Voss**\n\nEra uma jovem de 21 anos que trabalhava como restauradora de livros e documentos antigos. Em 1975, enquanto investigava uma loja de antiguidades em Paris, descobriu uma passagem secreta escondida atrás de um quadro. A passagem levava a uma sala de estudo abandonada, onde encontrou uma vasta coleção de livros sobre ocultismo e magia. Ela passou horas lendo e explorando a sala, descobrindo que alguns dos livros eram grimórios antigos.\n\nQuando entrou na sala, Elara viu uma mesa de estudo com várias luzes de velas acesas. Sobre a mesa, havia um livro aberto, com uma página marcada com um símbolo estranho. Ela se sentou para ler o livro e, ao fechar os olhos, sentiu uma estranha sensação de conexão com o livro e com a sala. Quando abriu os olhos novamente, viu que a sala havia mudado. A mesa de estudo agora estava coberta de livros e papéis, e havia uma figura sentada em uma cadeira ao lado dela. A figura era Isaak Velvet, o mesmo homem que ela havia conhecido anos antes.', '2026-03-30 07:46:31'),
(37, 5, 'user', 'Faz tipo:\nElara Voss:\nUma jovem de 25 anos, com 1,67m que fez Arts graphiques et livre na INP, Aubervilliers/Paris. É uma historiadora genial que ganhou um grande reconhecimento, logo após terminar os estudos. Reconhecida pelos reparos rápidos e perfeitos, mas também pelo conhecimento incessante. \nUm dia durante o trabalho vi um homem por volta dos 30 anos com a cara direita paralisada. Ele perguntou-me se conseguia restaurar um livro que tinha uma capa vermelha com véias e um livro com uma capa amarelo cheia de rabiscos e letras antigas. Na hora lembro que paralisei por ver  livros tão antigos, bonitos e em bom estado. Eu perguntei-lhe o nome e ele disse Isaak Velvet. Fiz a piada que o sobrenome dele deve ser tão antigo quanto os livros e ele continouo com a afeição plena e e repente deu um sorriso froxo.', '2026-03-30 17:56:28'),
(38, 5, 'assistant', 'Aqui vai a lore de Elara Voss, escrita de forma semelhante à do Simön Amarantus:\n\n**Elara Voss**\n\nUma jovem de 25 anos, com 1,67m que fez Arts graphiques et livre na INP, Aubervilliers/Paris. É uma historiadora genial que ganhou um grande reconhecimento, logo após terminar os estudos. Reconhecida pelos reparos rápidos e perfeitos, mas também pelo conhecimento incessante.\n\nUm dia durante o trabalho, vi um homem por volta dos 30 anos com a cara direita paralisada. Ele perguntou-me se conseguia restaurar um livro que tinha uma capa vermelha com véias e um livro com uma capa amarelo cheia de rabiscos e letras antigas. Na hora lembro que paralisei por ver livros tão antigos, bonitos e em bom estado. Eu perguntei-lhe o nome e ele disse Isaak Velvet. Fiz a piada que o sobrenome dele deve ser tão antigo quanto os livros e ele continuou com a afeição plena e e repente deu um sorriso froxo.', '2026-03-30 17:56:28'),
(39, 5, 'user', 'Agora continua', '2026-03-30 17:56:51'),
(40, 5, 'assistant', '**Elara Voss**\n\nFiz a piada que o sobrenome dele deve ser tão antigo quanto os livros e ele continuou com a afeição plena e repente deu um sorriso estranho, com a metade da face paralisada. Eu fiquei um pouco desconfortável e perguntei qual era o problema com os livros. Ele explicou que eram de uma família específica e que tinham uma importância histórica grande. Eu disse que eu iria fazer o melhor para restaurá-los e ele disse que sabia que eu iria.\n\nEle me mostrou os livros e eu percebi que eram realmente muito antigos e valiosos. Eu comecei a trabalhar neles, mas ao tocar os livros, eu senti uma sensação estranha, como se estivesse conectada a eles de alguma forma. Eu ignorei a sensação e continuei a trabalhar, mas logo percebi que as coisas estavam começando a mudar ao meu redor.', '2026-03-30 17:56:51'),
(41, 6, 'user', 'Qual é a melhor raça em Sailo Piece?', '2026-04-04 09:50:43'),
(42, 6, 'assistant', 'Peço desculpas, mas não estou preparado para responder sobre Sailo Piece. Como assistente de impressão 3D, posso ajudar com dúvidas sobre impressão 3D, mas não sei o que é Sailo Piece.\n\nSe você está a perguntar sobre o melhor filamento para impressão 3D, posso dizer que isso depende do que você está a imprimir. Se você está a imprimir objetos funcionais, como peças de hardware, filamentos como PLA ou PETG podem ser boas opções. Se você está a imprimir objetos decorativos ou com texturas específicas, filamentos como ABS ou TPU podem ser mais adequados.\n\nSe você está a imprimir com a tecnologia SLA, o melhor material é o resina de poliéster, que é um material muito popular.\n\nSe quiser mais informações sobre filamentos ou impressoras 3D, estou aqui para ajudar!\n\n**Tecnologias de Impressão 3D**\n\n* FDM (Fused Deposition Modeling)\n* SLA (Stereolithography)\n* SLS (Selective Laser Sintering)', '2026-04-04 09:50:43'),
(43, 6, 'user', 'É para uma impressão 3D', '2026-04-04 09:50:58'),
(44, 6, 'assistant', 'Peço desculpas pelo mal-entendido!\n\nInfelizmente, não tenho conhecimento sobre a \"Sailo Piece\" para impressão 3D. Pode ser um termo específico de um projeto ou design de impressão 3D que não conheço.\n\nSe você puder fornecer mais informações ou contexto sobre a \"Sailo Piece\", posso tentar ajudar melhor.\n\n**Tecnologias de Impressão 3D**\n* FDM (Fused Deposition Modeling)\n* SLA (Stereolithography)\n* SLS (Selective Laser Sintering)\n* Etc.', '2026-04-04 09:50:58'),
(45, 6, 'user', 'Sailor Piece é um jogo do Roblox e queria ter uma ideia que qual seria a melhor para dar de presente para um amigo meu.', '2026-04-04 09:51:37'),
(46, 6, 'assistant', 'Peço desculpas pelo mal-entendido! Eu sou um assistente de impressão 3D e não tenho conhecimento sobre os jogos do Roblox, incluindo o Sailor Piece.\n\nNo entanto, posso sugerir que você considere criar um presente personalizado para seu amigo usando a impressão 3D. Aqui estão algumas ideias:\n\n* Uma miniatura de um navio ou um barco para o Sailor Piece\n* Um modelo de personagem do jogo\n* Um objeto decorativo relacionado ao jogo\n* Um item funcional, como um chaveiro ou um pendente, com um design relacionado ao jogo\n\nEssas são apenas algumas ideias, e você pode criar algo ainda mais criativo e personalizado com a impressão 3D.\n\n**Dicas para criar um presente de impressão 3D:**\n\n* Escolha um design que seja relevante para o jogo ou para o seu amigo\n* Escolha um material que seja apropriado para o presente, como um filamento translúcido ou um metal\n* Certifique-se de que o design seja compatível com a impressora 3D que você está a usar\n* Adicione detalhes e texturas para dar um toque especial ao presente\n\nLembre-se de que a impressão 3D é uma forma de criar objetos personalizados e únicos, então não tenha medo de ser criativo e experimentar!', '2026-04-04 09:51:37'),
(47, 7, 'user', 'Sabes IA para imagens?', '2026-04-04 22:36:45'),
(48, 7, 'assistant', 'Sim, existem várias opções para impressão 3D de imagens. Aqui estão algumas:\n\n### SLA (Stereolithography)\n\n- **Resolução**: Alta resolução, detalhes muito precisos.\n- **Material**: Resina.\n- **Desvantagens**: Pode ser caro, requer equipamento especializado e pode ser prejudicial para a saúde se não se seguir as precauções necessárias.\n\n### FDM (Fused Deposition Modeling)\n\n- **Resolução**: Média a alta resolução.\n- **Material**: Plástico (filamentos).\n- **Desvantagens**: Pode ser difícil de obter detalhes muito pequenos, depende do filamento utilizado.\n\n### DLP (Digital Light Processing)\n\n- **Resolução**: Alta resolução.\n- **Material**: Resina.\n- **Desvantagens**: Pode ser caro, requer equipamento especializado.\n\n### Impressão 3D com fotopolímero\n\n- **Resolução**: Alta resolução.\n- **Material**: Fotopolímero.\n- **Desvantagens**: Pode ser caro, requer equipamento especializado.\n\n### O que é que é melhor?\n\nA resposta depende do que estás a procurar. Se queres detalhes muito precisos e não te importas com o custo, SLA ou DLP podem ser boas opções. Se queres algo mais acessível, FDM pode ser uma boa escolha. Se estás a procurar algo novo e interessante, impressão 3D com fotopolímero pode ser uma boa opção.\n\n**Lembre-se:** Antes de começar, certifica-te de que o equipamento e o material sejam adequados para a imagem que estás a imprimir.', '2026-04-04 22:36:45');

-- --------------------------------------------------------

--
-- Estrutura da tabela `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `section` varchar(100) DEFAULT 'geral',
  `category` enum('duvida','problema','dica','geral') DEFAULT 'geral',
  `title` varchar(200) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pendente','aprovado','rejeitado') DEFAULT 'pendente',
  `likes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reject_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `parent_id`, `section`, `category`, `title`, `content`, `status`, `likes`, `created_at`, `updated_at`, `reviewed_at`, `reviewed_by`, `reject_reason`) VALUES
(1, 1, NULL, 'geral', 'duvida', 'Problema com stringing no PETG', 'Olá! Estou com dificuldade em imprimir com PETG. As minhas peças ficam com muitos fios (stringing). Já tentei aumentar a retraction mas não resolveu. Alguém tem dicas?', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL, NULL),
(2, 1, NULL, 'geral', 'dica', 'Dica: Primeira camada é fundamental', 'Dica para iniciantes: invistam numa boa primeira camada! Verifiquem sempre o nivelamento da cama antes de começar uma impressão longa.', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL, NULL),
(3, 1, NULL, 'geral', 'problema', 'Ruído estranho no eixo Z', 'A minha impressora está a fazer um ruído estranho no eixo Z. Parece rangido. Já lubrifiquei mas continua.', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL, NULL),
(4, 2, 1, 'geral', 'geral', NULL, 'A sério? Como?', 'aprovado', 0, '2026-03-05 15:50:21', '2026-03-05 15:51:07', '2026-03-05 15:51:07', 1, NULL),
(5, 2, NULL, 'geral', 'geral', NULL, 'eu tenho tres niggas na minha cave e eu violo-os diarimente de todas as maneiras possiveis, sexualmente principalmente, e caos eu me tenha esquecido de mencionar eles sao todos menores de idade, criancinhas gostosinhas.', 'rejeitado', 0, '2026-03-11 11:28:17', '2026-03-11 12:01:53', '2026-03-11 12:01:53', 1, NULL),
(6, 2, NULL, 'geral', 'geral', NULL, 'pedofilia infantil é totalmente aceitável', 'rejeitado', 0, '2026-03-11 12:07:55', '2026-03-11 12:11:36', '2026-03-11 12:11:36', 1, NULL),
(7, 2, NULL, 'geral', 'geral', NULL, 'adoro comer meninas menores de idade', 'rejeitado', 0, '2026-03-11 12:16:24', '2026-03-11 12:19:33', '2026-03-11 12:19:33', 1, NULL),
(8, 4, NULL, 'geral', 'geral', NULL, 'cara, minha impressora ta fazendo algo diferente do que eu pedi e não sei o motivo.', 'aprovado', 0, '2026-03-12 15:02:16', '2026-03-12 15:13:58', '2026-03-12 15:13:58', 1, NULL),
(9, 4, NULL, 'geral', 'geral', NULL, 'ola', 'rejeitado', 0, '2026-03-12 15:02:42', '2026-03-12 15:14:03', '2026-03-12 15:14:03', 1, NULL),
(10, 3, NULL, 'geral', 'geral', NULL, 'Eu sem querer comi a minha impressora, e agora?', 'aprovado', 0, '2026-03-13 14:44:25', '2026-03-13 14:44:46', '2026-03-13 14:44:46', 1, NULL),
(11, 1, NULL, 'geral', 'geral', NULL, 'olá', 'pendente', 0, '2026-03-20 16:06:56', '2026-03-20 16:06:56', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `comment_likes`
--

CREATE TABLE `comment_likes` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `comment_likes`
--

INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `created_at`) VALUES
(2, 1, 1, '2026-03-05 15:51:24'),
(4, 10, 3, '2026-03-13 14:45:17'),
(5, 2, 1, '2026-03-13 16:53:37'),
(7, 10, 1, '2026-03-13 17:00:44');

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_communities`
--

CREATE TABLE `forum_communities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(10) DEFAULT '?',
  `banner_color` varchar(20) DEFAULT '#00e5ff',
  `created_by` int(11) NOT NULL,
  `member_count` int(11) DEFAULT 0,
  `post_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `content_labels` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forum_communities`
--

INSERT INTO `forum_communities` (`id`, `name`, `slug`, `description`, `icon`, `banner_color`, `created_by`, `member_count`, `post_count`, `is_active`, `created_at`, `content_labels`) VALUES
(1, 'Problemas impressoras 3D', 'problemas-impressoras-3d', 'Esta comunidade é feita para as pessoas conseguirem comunicar os problemas que já tiveram a imprimir e como esse problema foi resolvido', '🖨️', '#aa44ff', 1, 2, 2, 1, '2026-03-17 16:04:43', NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_memberships`
--

CREATE TABLE `forum_memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `role` enum('owner','admin','moderator','member') NOT NULL DEFAULT 'member',
  `joined_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forum_memberships`
--

INSERT INTO `forum_memberships` (`id`, `user_id`, `community_id`, `role`, `joined_at`) VALUES
(1, 1, 1, 'owner', '2026-03-17 16:04:43'),
(3, 2, 1, 'admin', '2026-03-18 11:55:41');

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_moderation_log`
--

CREATE TABLE `forum_moderation_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `post_id` int(11) NOT NULL,
  `moderator_id` int(11) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `content` text DEFAULT NULL,
  `type` enum('text','link','image') DEFAULT 'text',
  `vote_score` int(11) DEFAULT 0,
  `reply_count` int(11) DEFAULT 0,
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `flair` varchar(20) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `image_type` enum('upload','url') DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `moderated_by` int(11) DEFAULT NULL,
  `moderated_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `community_id`, `user_id`, `title`, `content`, `type`, `vote_score`, `reply_count`, `is_pinned`, `is_locked`, `created_at`, `updated_at`, `flair`, `image_url`, `image_type`, `status`, `moderated_by`, `moderated_at`, `rejection_reason`) VALUES
(4, 1, 1, 'Imprimir após algum tempo', 'Eu sou uma pessoa que imprimo coisas de 2-2 semanas ou 3-3 semanas, nunca tinha-me ocorrido, mas hoje fui tentar imprimir na minha impressora e o filamento PLA estava todo partido. \r\nDesta vez tive de abrir a impressora na trazeira e com cuidado tirar o filamento de dentro da impressora. Para resolver este problema pesquisei e encontrei que precisava de ter na impressor coisas que puxassem a humidade e coloca-los onde os filamentos estão armazenados.', 'text', 2, 1, 0, 0, '2026-03-17 16:12:30', NULL, NULL, NULL, NULL, 'approved', NULL, NULL, NULL),
(13, 1, 1, 'Raba', NULL, 'text', 0, 0, 0, 0, '2026-03-28 22:42:18', NULL, NULL, NULL, NULL, 'approved', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_post_votes`
--

CREATE TABLE `forum_post_votes` (
  `user_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `value` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forum_post_votes`
--

INSERT INTO `forum_post_votes` (`user_id`, `post_id`, `value`) VALUES
(2, 4, 1),
(3, 4, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_replies`
--

CREATE TABLE `forum_replies` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `vote_score` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `forum_replies`
--

INSERT INTO `forum_replies` (`id`, `post_id`, `parent_id`, `user_id`, `content`, `vote_score`, `created_at`) VALUES
(2, 4, NULL, 2, 'Testado e funciona', 0, '2026-03-18 11:52:13');

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_reply_votes`
--

CREATE TABLE `forum_reply_votes` (
  `user_id` int(11) NOT NULL,
  `reply_id` int(11) NOT NULL,
  `value` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `forum_user_xp`
--

CREATE TABLE `forum_user_xp` (
  `user_id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `xp` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `forum_user_xp`
--

INSERT INTO `forum_user_xp` (`user_id`, `community_id`, `xp`) VALUES
(1, 1, 21);

-- --------------------------------------------------------

--
-- Estrutura da tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('comment_approved','comment_rejected','comment_reply') NOT NULL,
  `comment_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `comment_id`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 'comment_reply', 4, 'Sá respondeu ao teu comentário. A resposta aguarda moderação.', 1, '2026-03-05 15:50:23'),
(2, 2, 'comment_approved', 4, 'O teu comentário foi aprovado e já está visível.', 1, '2026-03-05 15:51:07'),
(3, 2, 'comment_rejected', 5, 'O teu comentário foi rejeitado pelo moderador.', 1, '2026-03-11 12:01:53'),
(4, 2, 'comment_rejected', 6, 'O teu comentário foi rejeitado pelo moderador.', 1, '2026-03-11 12:11:36'),
(5, 2, 'comment_rejected', 7, 'O teu comentário foi rejeitado pelo moderador.', 1, '2026-03-11 12:19:33'),
(6, 4, 'comment_approved', 8, 'O teu comentário foi aprovado e já está visível.', 0, '2026-03-12 15:13:58'),
(7, 4, 'comment_rejected', 9, 'O teu comentário foi rejeitado pelo moderador.', 0, '2026-03-12 15:14:03'),
(8, 3, 'comment_approved', 10, 'O teu comentário foi aprovado.', 1, '2026-03-13 14:44:46');

-- --------------------------------------------------------

--
-- Estrutura da tabela `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `used`, `created_at`) VALUES
(5, 'martimsa.28473@ae-smfeira.pt', '4d6f2e5a72188d1b912a8199143f3cc4cbadfb244b5ed9b7d2d7c34461e02f05', '2026-03-09 23:11:06', 0, '2026-03-09 19:11:05'),
(7, 'martimt60@gmail.com', '3b5201d9d0fb73c28862bdc5e4f9766050a32121a5171a15991885c16aa53c57', '2026-03-09 23:28:23', 0, '2026-03-09 19:28:23');

-- --------------------------------------------------------

--
-- Estrutura da tabela `private_messages`
--

CREATE TABLE `private_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `private_messages`
--

INSERT INTO `private_messages` (`id`, `sender_id`, `receiver_id`, `content`, `read_at`, `created_at`) VALUES
(1, 1, 2, 'Bom dia', '2026-03-20 16:10:59', '2026-03-20 13:49:00');

-- --------------------------------------------------------

--
-- Estrutura da tabela `reported_comments`
--

CREATE TABLE `reported_comments` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pendente','resolvido','ignorado') DEFAULT 'pendente',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `reported_comments`
--

INSERT INTO `reported_comments` (`id`, `comment_id`, `reporter_id`, `reason`, `description`, `status`, `created_at`, `resolved_at`, `resolved_by`) VALUES
(1, 10, 2, 'desinformacao', 'Acho que não é assim que se come', 'resolvido', '2026-03-13 17:31:02', '2026-03-16 09:45:35', NULL),
(2, 8, 1, 'desinformacao', NULL, 'pendente', '2026-03-16 09:54:10', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `reported_users`
--

CREATE TABLE `reported_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pendente','analisado','resolvido') DEFAULT 'pendente',
  `action_taken` enum('none','warning','suspension','ban') DEFAULT 'none',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `admin_message` text DEFAULT NULL,
  `suspension_days` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Extraindo dados da tabela `reported_users`
--

INSERT INTO `reported_users` (`id`, `user_id`, `reported_by`, `reason`, `description`, `status`, `action_taken`, `created_at`, `resolved_at`, `resolved_by`, `admin_message`, `suspension_days`) VALUES
(1, 2, 1, 'Outro', NULL, 'resolvido', 'warning', '2026-03-13 09:09:29', '2026-03-13 09:09:56', 1, NULL, NULL),
(2, 4, 1, 'Informação falsa', NULL, 'resolvido', 'none', '2026-03-13 09:23:25', '2026-03-13 09:33:29', 1, NULL, NULL),
(3, 1, 2, 'Informação falsa', NULL, 'pendente', 'none', '2026-03-13 09:34:12', NULL, NULL, NULL, NULL),
(4, 3, 2, 'Linguagem ofensiva', 'Ele realmente não comeu a impressora', 'resolvido', 'warning', '2026-03-13 14:46:05', '2026-03-13 14:46:50', 1, 'Não podes comer a tua impressora', NULL),
(5, 3, 1, 'Informação falsa', NULL, 'pendente', 'none', '2026-03-13 15:11:26', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `shop_items`
--

CREATE TABLE `shop_items` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `category` enum('frame','background','banner','accent') NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `css_value` text NOT NULL,
  `preview_css` text DEFAULT NULL,
  `price` int(11) DEFAULT 100,
  `source` enum('shop','community','achievement') DEFAULT 'shop',
  `community_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `shop_items`
--

INSERT INTO `shop_items` (`id`, `name`, `description`, `category`, `item_key`, `css_value`, `preview_css`, `price`, `source`, `community_id`, `is_active`, `created_at`) VALUES
(1, 'Cyan Neon', 'Frame neon ciano brilhante', 'frame', 'frame_cyan_neon', 'border: 3px solid #00e5ff; box-shadow: 0 0 12px #00e5ff, 0 0 24px rgba(0,229,255,0.4); border-radius: 50%;', 'border:3px solid #00e5ff;box-shadow:0 0 12px #00e5ff;border-radius:50%', 50, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(2, 'Purple Glow', 'Frame roxa brilhante', 'frame', 'frame_purple_glow', 'border: 3px solid #7c3aed; box-shadow: 0 0 12px #7c3aed, 0 0 24px rgba(124,58,237,0.5); border-radius: 50%;', 'border:3px solid #7c3aed;box-shadow:0 0 12px #7c3aed;border-radius:50%', 50, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(3, 'Fire', 'Frame laranja ardente', 'frame', 'frame_fire', 'border: 3px solid #ff6b35; box-shadow: 0 0 12px #ff6b35, 0 0 20px rgba(255,107,53,0.5); border-radius: 50%;', 'border:3px solid #ff6b35;box-shadow:0 0 12px #ff6b35;border-radius:50%', 50, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(4, 'Gradient Spin', 'Frame com gradiente animado rotativo', 'frame', 'frame_gradient_spin', 'border: 3px solid transparent; border-radius: 50%; background: linear-gradient(#111118,#111118) padding-box, conic-gradient(from 0deg, #00e5ff, #7c3aed, #ff6b35, #00e5ff) border-box; animation: spinFrame 3s linear infinite;', 'border:3px solid transparent;background:linear-gradient(#111118,#111118) padding-box,conic-gradient(from 0deg,#00e5ff,#7c3aed,#ff6b35,#00e5ff) border-box;border-radius:50%', 150, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(5, 'Rainbow Pulse', 'Frame arco-íris pulsante', 'frame', 'frame_rainbow_pulse', 'border: 4px solid transparent; border-radius: 50%; background: linear-gradient(#111118,#111118) padding-box, linear-gradient(45deg,#ff0080,#ff6b35,#ffcc00,#00ff88,#00e5ff,#7c3aed) border-box; animation: pulseFrame 2s ease-in-out infinite;', 'border:4px solid transparent;background:linear-gradient(#111118,#111118) padding-box,linear-gradient(45deg,#ff0080,#ff6b35,#ffcc00,#00ff88,#00e5ff,#7c3aed) border-box;border-radius:50%', 200, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(6, 'Gold Legend', 'Frame dourada de lendário', 'frame', 'frame_gold_legend', 'border: 4px solid #ffcc00; box-shadow: 0 0 16px #ffcc00, 0 0 32px rgba(255,204,0,0.4), inset 0 0 8px rgba(255,204,0,0.1); border-radius: 50%; animation: pulseFrame 2s ease-in-out infinite;', 'border:4px solid #ffcc00;box-shadow:0 0 16px #ffcc00;border-radius:50%', 300, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(7, 'Pixel Border', 'Frame pixel art 8-bit', 'frame', 'frame_pixel', 'border: 4px solid #00ff88; image-rendering: pixelated; border-radius: 4px; box-shadow: 4px 4px 0 #007744, -4px -4px 0 #00ffaa;', 'border:4px solid #00ff88;border-radius:4px;box-shadow:4px 4px 0 #007744,-4px -4px 0 #00ffaa', 100, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(8, 'Ice Crystal', 'Frame cristal de gelo', 'frame', 'frame_ice', 'border: 3px solid #a0e4ff; box-shadow: 0 0 10px rgba(160,228,255,0.7), 0 0 20px rgba(160,228,255,0.3); border-radius: 50%; filter: drop-shadow(0 0 6px #a0e4ff);', 'border:3px solid #a0e4ff;box-shadow:0 0 10px rgba(160,228,255,0.7);border-radius:50%', 120, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(9, 'Matrix Rain', 'Fundo com chuva de código verde', 'background', 'bg_matrix', '--custom-bg: #0a0a0f; --custom-overlay: radial-gradient(ellipse at 20% 50%, rgba(0,255,65,0.05) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(0,229,255,0.04) 0%, transparent 50%);', 'background:#0a0a0f', 80, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(10, 'Deep Space', 'Fundo cosmos estrelado', 'background', 'bg_space', '--custom-bg: #050510; --custom-overlay: radial-gradient(ellipse at 30% 30%, rgba(124,58,237,0.12) 0%, transparent 40%), radial-gradient(ellipse at 70% 70%, rgba(0,229,255,0.08) 0%, transparent 40%);', 'background:#050510', 80, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(11, 'Sunset', 'Fundo pôr do sol', 'background', 'bg_sunset', '--custom-bg: #0f0510; --custom-overlay: radial-gradient(ellipse at 50% 100%, rgba(255,107,53,0.15) 0%, transparent 60%), radial-gradient(ellipse at 50% 0%, rgba(124,58,237,0.1) 0%, transparent 50%);', 'background:#0f0510', 80, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(12, 'Neon City', 'Fundo cidade neon', 'background', 'bg_neon_city', '--custom-bg: #08080f; --custom-overlay: linear-gradient(180deg, rgba(0,229,255,0.03) 0%, transparent 40%), repeating-linear-gradient(90deg, rgba(0,229,255,0.01) 0px, rgba(0,229,255,0.01) 1px, transparent 1px, transparent 60px);', 'background:#08080f', 120, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(13, 'Lava', 'Fundo lava borbulhante', 'background', 'bg_lava', '--custom-bg: #0f0500; --custom-overlay: radial-gradient(ellipse at 20% 80%, rgba(255,50,0,0.12) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(255,150,0,0.08) 0%, transparent 50%);', 'background:#0f0500', 120, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(14, 'Plasma Pink', 'Cor de destaque rosa plasma', 'accent', 'accent_pink', '#ff0080', 'background:#ff0080;border-radius:50%;width:24px;height:24px', 60, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(15, 'Emerald', 'Cor de destaque esmeralda', 'accent', 'accent_emerald', '#00ff88', 'background:#00ff88;border-radius:50%;width:24px;height:24px', 60, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(16, 'Solar Gold', 'Cor de destaque dourada solar', 'accent', 'accent_gold', '#ffcc00', 'background:#ffcc00;border-radius:50%;width:24px;height:24px', 60, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(17, 'Crimson', 'Cor de destaque carmesim', 'accent', 'accent_crimson', '#ff2244', 'background:#ff2244;border-radius:50%;width:24px;height:24px', 60, 'shop', NULL, 1, '2026-03-20 14:35:53'),
(18, 'Violet', 'Cor de destaque violeta', 'accent', 'accent_violet', '#aa44ff', 'background:#aa44ff;border-radius:50%;width:24px;height:24px', 60, 'shop', NULL, 1, '2026-03-20 14:35:53');

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `avatar` varchar(10) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `experience_level` enum('iniciante','intermedio','avancado','profissional') DEFAULT 'iniciante',
  `role` enum('master','admin','moderator','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `warning_message` text DEFAULT NULL,
  `warning_at` timestamp NULL DEFAULT NULL,
  `suspension_message` text DEFAULT NULL,
  `suspension_until` timestamp NULL DEFAULT NULL,
  `coins` int(11) DEFAULT 0,
  `suspended_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `avatar`, `avatar_url`, `bio`, `location`, `website`, `experience_level`, `role`, `is_active`, `created_at`, `updated_at`, `last_login`, `warning_message`, `warning_at`, `suspension_message`, `suspension_until`, `coins`, `suspended_until`) VALUES
(1, 'master', 'castromartim0@gmail.com', '$2y$10$DGEJacA5Q073QCrv.aEzMeFN0SqHbBFNt9DJZ4uB/9fQycViBw1zG', 'Master', 'MS', 'uploads/avatars/avatar_1_1774032613.png', '', '', '', 'intermedio', 'master', 1, '2026-03-05 15:47:29', '2026-04-04 16:50:11', '2026-04-04 16:50:11', NULL, NULL, NULL, NULL, 0, NULL),
(2, 'Martim', 'martimsa.28473@ae-smfeira.pt', '$2y$10$OGMNYQgZzTQX5HQKyTx3weU1JjIMErDHBFo7uxMMyuH0ljA4vzUPW', 'Martim Sá', 'S', 'uploads/avatars/avatar_2_1774734411.png', '', '', '', 'iniciante', 'admin', 1, '2026-03-05 15:49:49', '2026-03-28 21:46:51', '2026-03-28 21:43:10', NULL, NULL, NULL, NULL, 0, NULL),
(3, 'Rodrigo', 'martimt60@gmail.com', '$2y$10$081bscydbcge6f6pgljvquUE1lPfo7uOK892ZGCAKrBwIOPCAnQLi', 'Rodrigo Pereira', 'RP', NULL, NULL, NULL, NULL, 'iniciante', 'user', 1, '2026-03-09 11:13:08', '2026-03-27 09:02:05', '2026-03-27 09:02:05', NULL, NULL, NULL, NULL, 0, NULL),
(4, 'felyr', 'davirmorm@gmail.com', '$2y$10$CzFJ1X33kuDAX9UH6Q6C4uUER/.L9WSQ2WN2K4s.vJy2fnbcjYi8a', 'Davi Moreira Marinho', 'DM', NULL, NULL, NULL, NULL, 'iniciante', 'user', 1, '2026-03-12 14:55:56', '2026-03-12 14:56:16', '2026-03-12 14:56:16', NULL, NULL, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_inventory`
--

CREATE TABLE `user_inventory` (
  `user_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `obtained_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_materials`
--

CREATE TABLE `user_materials` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `material` varchar(100) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_notices`
--

CREATE TABLE `user_notices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('warning','suspension','ban','info') DEFAULT 'info',
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `user_notices`
--

INSERT INTO `user_notices` (`id`, `user_id`, `type`, `message`, `is_read`, `created_by`, `created_at`) VALUES
(1, 2, 'warning', '', 0, 1, '2026-03-28 22:41:07');

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_printers`
--

CREATE TABLE `user_printers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `brand` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `type` enum('FDM','SLA','SLS','MSLA','Outro') DEFAULT 'FDM',
  `bed_size` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_profile_config`
--

CREATE TABLE `user_profile_config` (
  `user_id` int(11) NOT NULL,
  `frame_key` varchar(50) DEFAULT NULL,
  `background_key` varchar(50) DEFAULT NULL,
  `banner_url` varchar(500) DEFAULT NULL,
  `accent_color` varchar(20) DEFAULT NULL,
  `coins` int(11) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `user_profile_config`
--

INSERT INTO `user_profile_config` (`user_id`, `frame_key`, `background_key`, `banner_url`, `accent_color`, `coins`, `updated_at`) VALUES
(1, NULL, NULL, 'https://i.imgur.com/Ua8m2Wq.gif', '', 12, '2026-03-21 21:16:32'),
(2, NULL, NULL, 'https://i.imgur.com/rcw85F1.gif', NULL, 3, '2026-03-20 22:18:56'),
(3, NULL, NULL, NULL, NULL, 0, '2026-03-21 21:29:34');

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `expires_at`, `created_at`) VALUES
(4, 1, '64c83605c8ef3d848341436a2eceeac9ca24f7f3c5bf1b62fa51bc1482a88a7c', '2026-04-09 14:16:01', '2026-03-10 11:16:01'),
(5, 2, 'f02cb50cf1ff435b37a9bec7105771e81467ba56e69d7d6da2810cab4be5e58a', '2026-04-10 14:21:58', '2026-03-11 11:21:58');

-- --------------------------------------------------------

--
-- Estrutura da tabela `user_slicers`
--

CREATE TABLE `user_slicers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `version` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `actor_id` (`actor_id`);

--
-- Índices para tabela `ai_conversations`
--
ALTER TABLE `ai_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `ai_messages`
--
ALTER TABLE `ai_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `conversation_id` (`conversation_id`);

--
-- Índices para tabela `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_section` (`section`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Índices para tabela `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_like` (`comment_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `forum_communities`
--
ALTER TABLE `forum_communities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`);

--
-- Índices para tabela `forum_memberships`
--
ALTER TABLE `forum_memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_member` (`user_id`,`community_id`),
  ADD KEY `community_id` (`community_id`);

--
-- Índices para tabela `forum_moderation_log`
--
ALTER TABLE `forum_moderation_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_post` (`post_id`),
  ADD KEY `idx_log_mod` (`moderator_id`);

--
-- Índices para tabela `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `community_id` (`community_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  ADD PRIMARY KEY (`user_id`,`post_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Índices para tabela `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `forum_reply_votes`
--
ALTER TABLE `forum_reply_votes`
  ADD PRIMARY KEY (`user_id`,`reply_id`);

--
-- Índices para tabela `forum_user_xp`
--
ALTER TABLE `forum_user_xp`
  ADD PRIMARY KEY (`user_id`,`community_id`),
  ADD KEY `community_id` (`community_id`);

--
-- Índices para tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `comment_id` (`comment_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`);

--
-- Índices para tabela `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_email` (`email`);

--
-- Índices para tabela `private_messages`
--
ALTER TABLE `private_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Índices para tabela `reported_comments`
--
ALTER TABLE `reported_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_comment` (`comment_id`),
  ADD KEY `idx_reporter` (`reporter_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices para tabela `reported_users`
--
ALTER TABLE `reported_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Índices para tabela `shop_items`
--
ALTER TABLE `shop_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_key` (`item_key`);

--
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices para tabela `user_inventory`
--
ALTER TABLE `user_inventory`
  ADD PRIMARY KEY (`user_id`,`item_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Índices para tabela `user_materials`
--
ALTER TABLE `user_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `user_notices`
--
ALTER TABLE `user_notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `user_printers`
--
ALTER TABLE `user_printers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `user_profile_config`
--
ALTER TABLE `user_profile_config`
  ADD PRIMARY KEY (`user_id`);

--
-- Índices para tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `user_slicers`
--
ALTER TABLE `user_slicers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT de tabela `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `ai_conversations`
--
ALTER TABLE `ai_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `ai_messages`
--
ALTER TABLE `ai_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de tabela `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `forum_communities`
--
ALTER TABLE `forum_communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `forum_memberships`
--
ALTER TABLE `forum_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `forum_moderation_log`
--
ALTER TABLE `forum_moderation_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de tabela `forum_replies`
--
ALTER TABLE `forum_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `private_messages`
--
ALTER TABLE `private_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `reported_comments`
--
ALTER TABLE `reported_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `reported_users`
--
ALTER TABLE `reported_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `shop_items`
--
ALTER TABLE `shop_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=361;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `user_materials`
--
ALTER TABLE `user_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_notices`
--
ALTER TABLE `user_notices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `user_printers`
--
ALTER TABLE `user_printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `user_slicers`
--
ALTER TABLE `user_slicers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_communities`
--
ALTER TABLE `forum_communities`
  ADD CONSTRAINT `forum_communities_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_memberships`
--
ALTER TABLE `forum_memberships`
  ADD CONSTRAINT `forum_memberships_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_memberships_ibfk_2` FOREIGN KEY (`community_id`) REFERENCES `forum_communities` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD CONSTRAINT `forum_posts_ibfk_1` FOREIGN KEY (`community_id`) REFERENCES `forum_communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_posts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_post_votes`
--
ALTER TABLE `forum_post_votes`
  ADD CONSTRAINT `forum_post_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_post_votes_ibfk_2` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_replies`
--
ALTER TABLE `forum_replies`
  ADD CONSTRAINT `forum_replies_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `forum_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `forum_user_xp`
--
ALTER TABLE `forum_user_xp`
  ADD CONSTRAINT `forum_user_xp_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_user_xp_ibfk_2` FOREIGN KEY (`community_id`) REFERENCES `forum_communities` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `private_messages`
--
ALTER TABLE `private_messages`
  ADD CONSTRAINT `private_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `private_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_inventory`
--
ALTER TABLE `user_inventory`
  ADD CONSTRAINT `user_inventory_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_inventory_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `shop_items` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_materials`
--
ALTER TABLE `user_materials`
  ADD CONSTRAINT `user_materials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_notices`
--
ALTER TABLE `user_notices`
  ADD CONSTRAINT `user_notices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_printers`
--
ALTER TABLE `user_printers`
  ADD CONSTRAINT `user_printers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_profile_config`
--
ALTER TABLE `user_profile_config`
  ADD CONSTRAINT `user_profile_config_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_slicers`
--
ALTER TABLE `user_slicers`
  ADD CONSTRAINT `user_slicers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
