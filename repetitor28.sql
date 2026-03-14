-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Мар 14 2026 г., 13:24
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `repetitor28`
--

-- --------------------------------------------------------

--
-- Структура таблицы `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `color` varchar(20) DEFAULT '#6c757d',
  `is_visible` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `categories`
--

INSERT INTO `categories` (`id`, `name`, `color`, `is_visible`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'ЕГЭ 2026', '#6c757d', 1, 0, 2, '2026-03-13 21:55:09', '2026-03-13 21:56:45'),
(3, '3D', '#f3b816', 1, 0, 2, '2026-03-13 23:07:59', '2026-03-13 23:07:59'),
(4, 'Тип занятия', '#c12fa1', 1, 0, 2, '2026-03-14 00:14:13', '2026-03-14 00:14:13');

-- --------------------------------------------------------

--
-- Структура таблицы `diaries`
--

CREATE TABLE `diaries` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `student_id` int(11) NOT NULL,
  `tutor_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int(11) DEFAULT NULL COMMENT 'в минутах',
  `public_token` varchar(64) DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `diaries`
--

INSERT INTO `diaries` (`id`, `name`, `student_id`, `tutor_id`, `category_id`, `lesson_cost`, `lesson_duration`, `public_token`, `is_public`, `notes`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Тупикова Мария', 2, 2, 3, 1500.00, 60, 'd388a5671cdf582ac6420a166e2722ab00d706843be963bc733d094e169ce471', 1, '', '2026-03-13 22:56:52', '2026-03-13 23:20:53', 2),
(2, 'Худяков Василий', 1, 2, 2, 2500.00, 90, 'ecbbe07fbc9d0345159fdc40ba3d0ef1c2dfac47d8f322c3fc81cae5aadba0e2', 0, '', '2026-03-13 23:20:39', '2026-03-13 23:20:39', 2),
(3, 'Нуретдинов Раиль - подготовка к ЕГЭ', 3, 2, 2, 2000.00, 90, '76dfca56483482d5e0f144f6a32cf596c9dc8b26959e2cdb451c88cdcf531ec2', 0, '', '2026-03-13 23:57:03', '2026-03-13 23:57:03', 2),
(4, 'Пуставойт Серафим - подготовка к ЕГЭ', 4, 2, 2, 2500.00, 90, '21ed6b602fac805b4b7ca248636ae57a5b67decdd7e349803b2385866c08b977', 0, '', '2026-03-13 23:59:35', '2026-03-13 23:59:35', 2),
(5, 'Родин Дима - подготовка к ЕГЭ', 6, 2, 2, 2000.00, 90, '9868ddf7182b929b8e9de3a5b17a44608e3441a51ae17cfe9919d5ab50bc61ad', 0, '', '2026-03-14 00:05:00', '2026-03-14 00:05:00', 2),
(6, 'Князькина Алина - подготовка к ЕГЭ', 7, 2, 2, 2500.00, 90, '40d6aa549f1e5a077cecb98f29ac077d477cc4e000d130d500da54ee2ddfa4a8', 0, '', '2026-03-14 00:07:28', '2026-03-14 00:07:28', 2),
(7, 'Курин Тимофей - подготовка к выпускным экзаменам в колледже', 8, 2, NULL, 2500.00, 90, '22d1da848d03048d1876b75f5bc84ee331512f671fe0d699e41f80d639279dd3', 0, '', '2026-03-14 00:11:46', '2026-03-14 00:11:46', 2),
(8, 'Ходаков Роман - помощь по учебе', 5, 2, NULL, 2000.00, 120, '283b4d4a5da587e3de7947cdda8c70fa71fc4488454dc9f1a8350edccea6c649', 0, '', '2026-03-14 11:44:38', '2026-03-14 11:44:38', 2);

-- --------------------------------------------------------

--
-- Структура таблицы `diary_history`
--

CREATE TABLE `diary_history` (
  `id` int(11) NOT NULL,
  `diary_id` int(11) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_type` enum('create','update','copy','settings_change') NOT NULL,
  `comment` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `diary_history`
--

INSERT INTO `diary_history` (`id`, `diary_id`, `changed_by`, `change_type`, `comment`, `changed_at`) VALUES
(1, 1, 2, 'create', 'Создан новый дневник', '2026-03-13 22:56:52'),
(2, 1, 2, 'update', 'Обновлен дневник', '2026-03-13 22:57:04'),
(3, 1, 2, 'update', 'Обновлен дневник', '2026-03-13 22:57:10'),
(4, 1, 2, 'settings_change', 'Изменен статус публичности', '2026-03-13 22:57:17'),
(5, 1, 2, 'settings_change', 'Изменен статус публичности', '2026-03-13 22:57:22'),
(6, 1, 2, 'settings_change', 'Сгенерирована публичная ссылка', '2026-03-13 22:57:25'),
(7, 1, 2, 'settings_change', 'Изменен статус публичности', '2026-03-13 22:57:38'),
(8, 1, 2, 'settings_change', 'Изменен статус публичности', '2026-03-13 22:57:48'),
(9, 2, 2, 'create', 'Создан новый дневник', '2026-03-13 23:20:39'),
(10, 1, 2, 'update', 'Обновлен дневник', '2026-03-13 23:20:53'),
(11, 3, 2, 'create', 'Создан новый дневник', '2026-03-13 23:57:03'),
(12, 4, 2, 'create', 'Создан новый дневник', '2026-03-13 23:59:35'),
(13, 5, 2, 'create', 'Создан новый дневник', '2026-03-14 00:05:00'),
(14, 6, 2, 'create', 'Создан новый дневник', '2026-03-14 00:07:28'),
(15, 7, 2, 'create', 'Создан новый дневник', '2026-03-14 00:11:46'),
(16, 8, 2, 'create', 'Создан новый дневник', '2026-03-14 11:44:38');

-- --------------------------------------------------------

--
-- Структура таблицы `import_export_log`
--

CREATE TABLE `import_export_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_type` enum('categories','tags','topics','diaries') NOT NULL,
  `action` enum('import','export') NOT NULL,
  `file_format` enum('csv','json') NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `import_export_log`
--

INSERT INTO `import_export_log` (`id`, `user_id`, `entity_type`, `action`, `file_format`, `file_name`, `status`, `details`, `created_at`) VALUES
(1, 2, 'tags', 'export', 'json', NULL, 'success', NULL, '2026-03-13 22:05:02'),
(2, 2, 'topics', 'import', 'csv', 'topics.csv', 'success', 'Импортировано: 32, ошибок: 0', '2026-03-13 22:50:02');

-- --------------------------------------------------------

--
-- Структура таблицы `lessons`
--

CREATE TABLE `lessons` (
  `id` int(11) NOT NULL,
  `diary_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `start_time` time NOT NULL,
  `duration` int(11) DEFAULT NULL COMMENT 'в минутах',
  `cost` decimal(10,2) DEFAULT NULL,
  `is_conducted` tinyint(1) DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT 0,
  `homework` text DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `link_comment` varchar(255) DEFAULT NULL,
  `grade_lesson` decimal(2,1) DEFAULT NULL CHECK (`grade_lesson` >= 0 and `grade_lesson` <= 5),
  `grade_comment` text DEFAULT NULL,
  `grade_homework` decimal(2,1) DEFAULT NULL CHECK (`grade_homework` >= 0 and `grade_homework` <= 5),
  `grade_homework_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `lessons`
--

INSERT INTO `lessons` (`id`, `diary_id`, `lesson_date`, `start_time`, `duration`, `cost`, `is_conducted`, `is_paid`, `homework`, `comment`, `link_url`, `link_comment`, `grade_lesson`, `grade_comment`, `grade_homework`, `grade_homework_comment`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 1, '2026-03-14', '10:00:00', 60, 1500.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-13 23:07:30', '2026-03-14 11:46:45', 2),
(2, 1, '2026-03-21', '10:00:00', 60, 1500.00, 0, 0, '', '', '', '', NULL, '', NULL, '', '2026-03-13 23:13:47', '2026-03-13 23:13:47', 2),
(3, 2, '2026-03-15', '09:00:00', 90, 2500.00, 0, 0, '', '', '', '', NULL, '', NULL, '', '2026-03-13 23:21:37', '2026-03-14 11:38:48', 2),
(4, 1, '2026-03-10', '19:00:00', 60, 1500.00, 1, 1, '', 'Подготовка к защите', '', '', NULL, '', NULL, '', '2026-03-13 23:25:51', '2026-03-13 23:25:51', 2),
(5, 3, '2026-03-14', '11:00:00', 90, 2000.00, 1, 0, '', '', '', '', NULL, '', NULL, '', '2026-03-13 23:57:33', '2026-03-14 11:38:16', 2),
(6, 4, '2026-03-14', '15:00:00', 90, 2500.00, 0, 0, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:00:08', '2026-03-14 00:00:08', 2),
(7, 5, '2026-03-11', '18:00:00', 90, 2000.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:05:47', '2026-03-14 00:05:47', 2),
(8, 5, '2026-03-12', '18:00:00', 90, 2000.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:06:14', '2026-03-14 11:56:35', 2),
(9, 6, '2026-03-03', '18:00:00', 90, 2500.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:08:59', '2026-03-14 00:08:59', 2),
(10, 6, '2026-03-11', '11:00:00', 90, 2500.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:10:05', '2026-03-14 00:10:05', 2),
(11, 7, '2026-03-12', '20:00:00', 90, 2500.00, 1, 1, '', '', '', '', NULL, '', NULL, '', '2026-03-14 00:12:37', '2026-03-14 00:12:37', 2),
(12, 8, '2026-03-15', '11:00:00', 120, 2000.00, 0, 0, '', '', '', '', NULL, '', NULL, '', '2026-03-14 11:46:09', '2026-03-14 11:46:09', 2);

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_resources`
--

CREATE TABLE `lesson_resources` (
  `id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `lesson_topics`
--

CREATE TABLE `lesson_topics` (
  `lesson_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `lesson_topics`
--

INSERT INTO `lesson_topics` (`lesson_id`, `topic_id`) VALUES
(1, 34),
(1, 35),
(2, 33),
(4, 37),
(6, 10),
(7, 10),
(8, 10),
(9, 1),
(10, 10),
(11, 38);

-- --------------------------------------------------------

--
-- Структура таблицы `representatives`
--

CREATE TABLE `representatives` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `messenger_contact` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `representatives`
--

INSERT INTO `representatives` (`id`, `student_id`, `relationship`, `first_name`, `last_name`, `middle_name`, `phone`, `messenger_contact`, `email`, `is_primary`, `created_at`, `updated_at`) VALUES
(1, 1, 'Мать', 'Юлия', 'Худякова', '', '', '', '', 1, '2026-03-13 21:36:21', '2026-03-13 23:56:21'),
(3, 2, 'Отец', 'Павел', 'Тупиков', '', '', '', '', 1, '2026-03-13 22:56:08', '2026-03-13 22:56:08'),
(4, 3, 'Отец', 'Рафик', 'Нуретдинов', '', '', '', '', 1, '2026-03-13 23:55:51', '2026-03-13 23:56:07'),
(5, 5, 'Мать', 'Анастасия', 'Ходакова', '', '', '', '', 1, '2026-03-14 00:01:28', '2026-03-14 00:01:28'),
(6, 6, 'Мать', 'Светлана', 'Родина', 'Юрьевна', '', '', '', 1, '2026-03-14 00:04:11', '2026-03-14 00:04:24');

-- --------------------------------------------------------

--
-- Структура таблицы `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('page','document','video','audio','other') DEFAULT 'page',
  `tags` text DEFAULT NULL COMMENT 'теги через точку с запятой',
  `category_id` int(11) DEFAULT NULL,
  `topic_id` int(11) DEFAULT NULL,
  `quality` int(11) NOT NULL DEFAULT 0,
  `is_global` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `resources`
--

INSERT INTO `resources` (`id`, `url`, `description`, `type`, `tags`, `category_id`, `topic_id`, `quality`, `is_global`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'https://disk.yandex.ru/i/kzKnIIIzvG0DYQ', 'Запись урока по 11 задаче', 'video', NULL, 2, 10, 1, 1, 2, '2026-03-11 12:47:00', '2026-03-13 22:50:27');

-- --------------------------------------------------------

--
-- Структура таблицы `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  `class` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `messenger1` varchar(255) DEFAULT NULL,
  `messenger2` varchar(255) DEFAULT NULL,
  `messenger3` varchar(255) DEFAULT NULL,
  `lesson_cost` decimal(10,2) DEFAULT NULL,
  `lesson_duration` int(11) DEFAULT NULL COMMENT 'в минутах',
  `lessons_per_week` int(11) DEFAULT 1,
  `goals` text DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `tutor_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `students`
--

INSERT INTO `students` (`id`, `first_name`, `last_name`, `middle_name`, `birth_year`, `class`, `phone`, `email`, `city`, `messenger1`, `messenger2`, `messenger3`, `lesson_cost`, `lesson_duration`, `lessons_per_week`, `goals`, `additional_info`, `start_date`, `planned_end_date`, `is_active`, `tutor_id`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'Василий', 'Худяков', '', 2008, '', '', '', '', '', '', '', 2500.00, 90, 1, '', '', NULL, NULL, 1, 2, '2026-03-13 21:29:29', '2026-03-13 21:30:19', 2),
(2, 'Мария', 'Тупикова', '', 2012, '7', '', '', 'Одинцово', '', '', '', 1500.00, 60, 1, 'Перевестись в класс с углубленным изучением информатики.\r\nИзучить 3D', '', '2025-12-06', NULL, 1, 2, '2026-03-13 22:55:45', '2026-03-13 22:55:45', 2),
(3, 'Раиль', 'Нуретдинов', 'Рафикович', NULL, '', '', '', '', '', '', '', 2000.00, 90, 2, 'Сдача ЕГЭ', '', NULL, NULL, 1, 2, '2026-03-13 23:55:31', '2026-03-13 23:55:31', 2),
(4, 'Серафим', 'Пустовойт', '', 2008, '11', '', '', 'Одинцово', '', '', '', 2500.00, 90, 2, 'Сдача ЕГЭ', '', NULL, NULL, 1, 2, '2026-03-13 23:58:56', '2026-03-13 23:58:56', 2),
(5, 'Роман', 'Ходаков', '', 2010, '9', '', '', 'Москва', '', '', '', 2000.00, 120, 1, 'Помощь в учебе', '', NULL, NULL, 1, 2, '2026-03-14 00:01:07', '2026-03-14 00:01:07', 2),
(6, 'Дмитрий', 'Родин', '', 2008, '11', '', '', 'Одинцово', '', '', '', 2000.00, 90, 2, 'Сдача ЕГЭ по информатике', '', NULL, NULL, 1, 2, '2026-03-14 00:02:22', '2026-03-14 00:02:22', 2),
(7, 'Князькина', 'Князькина', '', 2008, '', '', '', 'Одинцово', '', '', '', NULL, 60, 1, '', '', NULL, NULL, 1, 2, '2026-03-14 00:06:56', '2026-03-14 00:06:56', 2),
(8, 'Тимофей', 'Курин', '', 2008, '', '', '', '', '', '', '', 2500.00, 90, 3, '', '', NULL, NULL, 1, 2, '2026-03-14 00:11:04', '2026-03-14 00:11:04', 2),
(9, 'Роман', 'Ходаков', '', 2010, '9', '', '', 'Москва', '', '', '', 2000.00, 120, 1, '', '', NULL, NULL, 0, 2, '2026-03-14 11:40:49', '2026-03-14 11:43:51', 2);

--
-- Триггеры `students`
--
DELIMITER $$
CREATE TRIGGER `validate_student_dates` BEFORE INSERT ON `students` FOR EACH ROW BEGIN
    IF NEW.start_date IS NOT NULL AND NEW.planned_end_date IS NOT NULL 
       AND NEW.start_date > NEW.planned_end_date THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Дата начала не может быть позже даты окончания';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `student_comments`
--

CREATE TABLE `student_comments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `student_comments`
--

INSERT INTO `student_comments` (`id`, `student_id`, `author_id`, `comment`, `is_deleted`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Ленивый', 0, '2026-03-13 21:30:08', '2026-03-13 21:30:08');

-- --------------------------------------------------------

--
-- Структура таблицы `student_history`
--

CREATE TABLE `student_history` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `change_type` enum('create','update','activate','deactivate') NOT NULL,
  `old_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_data`)),
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `student_history`
--

INSERT INTO `student_history` (`id`, `student_id`, `changed_by`, `change_type`, `old_data`, `new_data`, `changed_at`) VALUES
(1, 1, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"\",\"city\":\"\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-13 21:29:29'),
(2, 1, 2, 'update', NULL, '{\"student_id\":\"1\",\"last_name\":\"\\u0425\\u0443\\u0434\\u044f\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0412\\u0430\\u0441\\u0438\\u043b\\u0438\\u0439\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"\",\"city\":\"\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2500.00\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-13 21:30:19'),
(3, 2, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u0422\\u0443\\u043f\\u0438\\u043a\\u043e\\u0432\\u0430\",\"first_name\":\"\\u041c\\u0430\\u0440\\u0438\\u044f\",\"middle_name\":\"\",\"birth_year\":\"2012\",\"class\":\"7\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"1500\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u0435\\u0440\\u0435\\u0432\\u0435\\u0441\\u0442\\u0438\\u0441\\u044c \\u0432 \\u043a\\u043b\\u0430\\u0441\\u0441 \\u0441 \\u0443\\u0433\\u043b\\u0443\\u0431\\u043b\\u0435\\u043d\\u043d\\u044b\\u043c \\u0438\\u0437\\u0443\\u0447\\u0435\\u043d\\u0438\\u0435\\u043c \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0438.\\r\\n\\u0418\\u0437\\u0443\\u0447\\u0438\\u0442\\u044c 3D\",\"is_active\":\"on\",\"start_date\":\"2025-12-06\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-13 22:55:45'),
(4, 3, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u041d\\u0443\\u0440\\u0435\\u0442\\u0434\\u0438\\u043d\\u043e\\u0432\",\"first_name\":\"\\u0420\\u0430\\u0438\\u043b\\u044c\",\"middle_name\":\"\\u0420\\u0430\\u0444\\u0438\\u043a\\u043e\\u0432\\u0438\\u0447\",\"birth_year\":\"\",\"class\":\"\",\"city\":\"\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"2\",\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-13 23:55:31'),
(5, 4, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u041f\\u0443\\u0441\\u0442\\u043e\\u0432\\u043e\\u0439\\u0442\",\"first_name\":\"\\u0421\\u0435\\u0440\\u0430\\u0444\\u0438\\u043c\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"11\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"2\",\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-13 23:58:56'),
(6, 5, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"middle_name\":\"\",\"birth_year\":\"2010\",\"class\":\"9\",\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"120\",\"lessons_per_week\":\"1\",\"goals\":\"\\u041f\\u043e\\u043c\\u043e\\u0449\\u044c \\u0432 \\u0443\\u0447\\u0435\\u0431\\u0435\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-14 00:01:07'),
(7, 6, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u0420\\u043e\\u0434\\u0438\\u043d\",\"first_name\":\"\\u0414\\u043c\\u0438\\u0442\\u0440\\u0438\\u0439\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"11\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"2\",\"goals\":\"\\u0421\\u0434\\u0430\\u0447\\u0430 \\u0415\\u0413\\u042d \\u043f\\u043e \\u0438\\u043d\\u0444\\u043e\\u0440\\u043c\\u0430\\u0442\\u0438\\u043a\\u0435\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-14 00:02:22'),
(8, 7, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u041a\\u043d\\u044f\\u0437\\u044c\\u043a\\u0438\\u043d\\u0430\",\"first_name\":\"\\u041a\\u043d\\u044f\\u0437\\u044c\\u043a\\u0438\\u043d\\u0430\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"\",\"city\":\"\\u041e\\u0434\\u0438\\u043d\\u0446\\u043e\\u0432\\u043e\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"\",\"lesson_duration\":\"60\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-14 00:06:56'),
(9, 8, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u041a\\u0443\\u0440\\u0438\\u043d\",\"first_name\":\"\\u0422\\u0438\\u043c\\u043e\\u0444\\u0435\\u0439\",\"middle_name\":\"\",\"birth_year\":\"2008\",\"class\":\"\",\"city\":\"\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2500\",\"lesson_duration\":\"90\",\"lessons_per_week\":\"3\",\"goals\":\"\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-14 00:11:04'),
(10, 9, 2, 'create', NULL, '{\"student_id\":\"\",\"last_name\":\"\\u0425\\u043e\\u0434\\u0430\\u043a\\u043e\\u0432\",\"first_name\":\"\\u0420\\u043e\\u043c\\u0430\\u043d\",\"middle_name\":\"\",\"birth_year\":\"2010\",\"class\":\"9\",\"city\":\"\\u041c\\u043e\\u0441\\u043a\\u0432\\u0430\",\"phone\":\"\",\"email\":\"\",\"messenger1\":\"\",\"messenger2\":\"\",\"messenger3\":\"\",\"lesson_cost\":\"2000\",\"lesson_duration\":\"120\",\"lessons_per_week\":\"1\",\"goals\":\"\",\"is_active\":\"on\",\"start_date\":\"\",\"planned_end_date\":\"\",\"additional_info\":\"\",\"save_student\":\"\"}', '2026-03-14 11:40:49'),
(11, 9, 2, 'deactivate', NULL, '{\"is_active\":0}', '2026-03-14 11:43:51');

-- --------------------------------------------------------

--
-- Структура таблицы `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color` enum('green','red','blue','grey') DEFAULT 'grey',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `tags`
--

INSERT INTO `tags` (`id`, `name`, `color`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Хорошая работа', 'green', 2, '2026-03-13 22:04:20', '2026-03-13 22:04:20'),
(2, 'плохая работа', 'red', 2, '2026-03-13 22:04:34', '2026-03-13 22:04:34'),
(3, 'Обещал лучше работать', 'grey', 2, '2026-03-13 22:04:56', '2026-03-13 22:04:56');

-- --------------------------------------------------------

--
-- Структура таблицы `topics`
--

CREATE TABLE `topics` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `topics`
--

INSERT INTO `topics` (`id`, `name`, `category_id`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '3. Базы данных. Файловая система', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(2, '4. Кодирование и декодирование информации', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(3, '5. Анализ и построение алгоритмов для исполнителей', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(4, '6. Анализ программ', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(5, '7. Кодирование и декодирование информации. Передача информации', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(6, '8. Перебор слов и системы счисления', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(7, '9. Эксель в Excel', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(8, '9. Эксель в Python', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(9, '10. Поиск символов в текстовом редакторе', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(10, '11. Вычисление количества информации', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(11, '12. Выполнение алгоритмов для исполнителей', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(12, '13. IP адреса', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(13, '14. Кодирование чисел. Системы счисления', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(14, '15. Преобразование логических выражений', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(15, '16. Рекурсивные алгоритмы', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(16, '17. Проверка на делимость', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(17, '18. Робот-сборщик монет', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(18, '19. Выигрышная стратегия. Задание 1', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(19, '20. Выигрышная стратегия. Задание 2', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(20, '21. Выигрышная стратегия. Задание 3', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(21, '22. Анализ программы с циклами и условными операторами', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(22, '22. Посимвольная обработка восьмеричных чисел', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(23, '22. Многопроцессорные системы', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(24, '23. Количество программ', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(25, '24. Обработка символьных строк', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(26, '25. Обработка целочисленной информации', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(27, '26. Обработка целочисленной информации', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(28, '27. Программирование', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(29, 'Яндекс. Учебник. Программирование', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(30, 'Программирование', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(31, 'КЕГЭ', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(32, 'Повторение', 2, 2, '2026-03-13 22:50:02', '2026-03-13 22:50:02'),
(33, 'Блокинг', 3, 2, '2026-03-13 23:08:44', '2026-03-13 23:08:44'),
(34, 'Детализация', 3, 2, '2026-03-13 23:08:56', '2026-03-13 23:08:56'),
(35, 'Скульптинг', 3, 2, '2026-03-13 23:09:04', '2026-03-13 23:09:04'),
(36, 'Новая тема', NULL, 2, '2026-03-13 23:13:25', '2026-03-13 23:13:25'),
(37, 'Заполнение паспорта проекта', NULL, 2, '2026-03-13 23:25:46', '2026-03-13 23:25:46'),
(38, 'Подготовка к демоэкзамену', NULL, 2, '2026-03-14 00:12:34', '2026-03-14 00:12:34'),
(39, 'Изучение новой темы', 4, 2, '2026-03-14 00:14:46', '2026-03-14 00:15:43'),
(40, 'Закрепление', 4, 2, '2026-03-14 00:14:58', '2026-03-14 00:14:58'),
(41, 'Повторение', 4, 2, '2026-03-14 00:15:08', '2026-03-14 00:15:08');

-- --------------------------------------------------------

--
-- Структура таблицы `topic_resources`
--

CREATE TABLE `topic_resources` (
  `id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `topic_tags`
--

CREATE TABLE `topic_tags` (
  `topic_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','tutor') DEFAULT 'tutor',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `middle_name`, `phone`, `role`, `is_active`, `created_at`, `updated_at`, `last_login`, `created_by`) VALUES
(2, 'admin', 'admin@example.com', '$2y$10$SvPDQTySuG39aPVXTh8V4ur.WOUWuadp40xUL6sBy76/6Hdtdf9hO', 'Admin', 'User', NULL, NULL, 'admin', 1, '2026-03-13 21:19:57', '2026-03-14 11:28:17', '2026-03-14 11:28:17', NULL),
(3, 'ivanovii', 'ivanov@mail.ru', '$2y$10$7Y9/JtD3Fvh3p7Pg1IxCeugQ1sqg8fDmZIY1UkbKug2bkZCCnH/Zi', 'Иван', 'Иванов', 'Иванович', '', 'tutor', 1, '2026-03-13 23:15:20', '2026-03-13 23:15:44', '2026-03-13 23:15:44', 2);

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `diaries`
--
ALTER TABLE `diaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_token` (`public_token`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_diaries_public` (`public_token`,`is_public`),
  ADD KEY `idx_diaries_tutor` (`tutor_id`,`student_id`);

--
-- Индексы таблицы `diary_history`
--
ALTER TABLE `diary_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `diary_id` (`diary_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Индексы таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_lessons_date` (`lesson_date`,`start_time`),
  ADD KEY `idx_lessons_diary` (`diary_id`),
  ADD KEY `idx_lessons_conducted` (`is_conducted`,`is_paid`);

--
-- Индексы таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Индексы таблицы `lesson_topics`
--
ALTER TABLE `lesson_topics`
  ADD PRIMARY KEY (`lesson_id`,`topic_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Индексы таблицы `representatives`
--
ALTER TABLE `representatives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Индексы таблицы `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_resources_type` (`type`),
  ADD KEY `idx_resources_category` (`category_id`),
  ADD KEY `idx_resources_topic` (`topic_id`);

--
-- Индексы таблицы `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_students_tutor` (`tutor_id`,`is_active`),
  ADD KEY `idx_students_name` (`last_name`,`first_name`),
  ADD KEY `idx_students_class` (`class`);

--
-- Индексы таблицы `student_comments`
--
ALTER TABLE `student_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Индексы таблицы `student_history`
--
ALTER TABLE `student_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Индексы таблицы `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Индексы таблицы `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_topics_name` (`name`);

--
-- Индексы таблицы `topic_resources`
--
ALTER TABLE `topic_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Индексы таблицы `topic_tags`
--
ALTER TABLE `topic_tags`
  ADD PRIMARY KEY (`topic_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `diaries`
--
ALTER TABLE `diaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT для таблицы `diary_history`
--
ALTER TABLE `diary_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT для таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT для таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `representatives`
--
ALTER TABLE `representatives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `student_comments`
--
ALTER TABLE `student_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `student_history`
--
ALTER TABLE `student_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT для таблицы `topic_resources`
--
ALTER TABLE `topic_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `diaries`
--
ALTER TABLE `diaries`
  ADD CONSTRAINT `diaries_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diaries_ibfk_2` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diaries_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `diaries_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `diary_history`
--
ALTER TABLE `diary_history`
  ADD CONSTRAINT `diary_history_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `diary_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `import_export_log`
--
ALTER TABLE `import_export_log`
  ADD CONSTRAINT `import_export_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`diary_id`) REFERENCES `diaries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lessons_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `lesson_resources`
--
ALTER TABLE `lesson_resources`
  ADD CONSTRAINT `lesson_resources_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `lesson_topics`
--
ALTER TABLE `lesson_topics`
  ADD CONSTRAINT `lesson_topics_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lesson_topics_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `representatives`
--
ALTER TABLE `representatives`
  ADD CONSTRAINT `representatives_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `resources_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `resources_ibfk_3` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`tutor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `student_comments`
--
ALTER TABLE `student_comments`
  ADD CONSTRAINT `student_comments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_comments_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `student_history`
--
ALTER TABLE `student_history`
  ADD CONSTRAINT `student_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `tags`
--
ALTER TABLE `tags`
  ADD CONSTRAINT `tags_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `topics_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `topic_resources`
--
ALTER TABLE `topic_resources`
  ADD CONSTRAINT `topic_resources_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `topic_tags`
--
ALTER TABLE `topic_tags`
  ADD CONSTRAINT `topic_tags_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `topic_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
