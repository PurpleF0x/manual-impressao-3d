-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09-Mar-2026 às 11:56
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
-- Banco de dados: `manual_3d`
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
(7, 1, 'login', 'Login efetuado: admin', '::1', '2026-03-05 15:50:40');

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
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `parent_id`, `section`, `category`, `title`, `content`, `status`, `likes`, `created_at`, `updated_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 1, NULL, 'geral', 'duvida', 'Problema com stringing no PETG', 'Olá! Estou com dificuldade em imprimir com PETG. As minhas peças ficam com muitos fios (stringing). Já tentei aumentar a retraction mas não resolveu. Alguém tem dicas?', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL),
(2, 1, NULL, 'geral', 'dica', 'Dica: Primeira camada é fundamental', 'Dica para iniciantes: invistam numa boa primeira camada! Verifiquem sempre o nivelamento da cama antes de começar uma impressão longa.', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL),
(3, 1, NULL, 'geral', 'problema', 'Ruído estranho no eixo Z', 'A minha impressora está a fazer um ruído estranho no eixo Z. Parece rangido. Já lubrifiquei mas continua.', 'aprovado', 0, '2026-03-05 15:47:29', '2026-03-05 15:47:29', NULL, NULL),
(4, 2, 1, 'geral', 'geral', NULL, 'A sério? Como?', 'aprovado', 0, '2026-03-05 15:50:21', '2026-03-05 15:51:07', '2026-03-05 15:51:07', 1);

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
(1, 1, 2, '2026-03-05 15:50:12'),
(2, 1, 1, '2026-03-05 15:51:24');

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
(1, 1, 'comment_reply', 4, 'Sá respondeu ao teu comentário. A resposta aguarda moderação.', 0, '2026-03-05 15:50:23'),
(2, 2, 'comment_approved', 4, 'O teu comentário foi aprovado e já está visível.', 0, '2026-03-05 15:51:07');

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
  `role` enum('user','moderator','admin') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `avatar`, `avatar_url`, `bio`, `location`, `website`, `experience_level`, `role`, `is_active`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@exemplo.pt', '$2y$10$dlQhEu1F572M4Nb95HRUduscfderuwFaMFOrlYYLcNQ8lRuyR7xTy', 'Administrador', 'AD', NULL, NULL, NULL, NULL, 'iniciante', 'admin', 1, '2026-03-05 15:47:29', '2026-03-05 15:50:40', '2026-03-05 15:50:40'),
(2, 'Martim', 'martimsa.28473@ae-smfeira.pt', '$2y$10$Zk47vFeMHhxOeuFzfDmVfeDqvWD09L.yZKIHyaO6kRpy7StLF3fHW', 'Sá', 'S', NULL, NULL, NULL, NULL, 'iniciante', 'user', 1, '2026-03-05 15:49:49', '2026-03-05 15:50:00', '2026-03-05 15:50:00');

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
-- Estrutura da tabela `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Índices para tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices para tabela `user_materials`
--
ALTER TABLE `user_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Índices para tabela `user_printers`
--
ALTER TABLE `user_printers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de tabela `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `user_materials`
--
ALTER TABLE `user_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_printers`
--
ALTER TABLE `user_printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
-- Limitadores para a tabela `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_materials`
--
ALTER TABLE `user_materials`
  ADD CONSTRAINT `user_materials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `user_printers`
--
ALTER TABLE `user_printers`
  ADD CONSTRAINT `user_printers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
