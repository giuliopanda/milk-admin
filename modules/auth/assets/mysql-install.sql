CREATE TABLE `#__sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `phpsessid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `session_date` datetime NOT NULL,
  `user_id` int NOT NULL,
  `secret_key` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `#__users` (
  `id` bigint UNSIGNED NOT NULL,
  `username` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_login` datetime DEFAULT NULL,
  `activation_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `status` int NOT NULL DEFAULT '0',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `permissions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `#__login_attempts` (
  `id` int NOT NULL,
  `username_email` varchar(255) NOT NULL,
  `ip_address` varchar(64) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

ALTER TABLE `#__sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phpsessid` (`phpsessid`);

ALTER TABLE `#__login_attempts` ADD PRIMARY KEY (`id`);

INSERT INTO `#__users` (`id`, `username`, `email`, `password`, `registered`, `activation_key`, `status`, `is_admin`, `permissions`) VALUES
(1, 'admin', 'admin@gmail.com', '$2y$10$DoAOvm.Od/07BVorCe0A5uoDtdHLkSV2JZnmKRSMJc8N7zrY4Olc6', '0000-00-00 00:00:00', '', 1, 1, '{}');
