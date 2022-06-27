-- phpMyAdmin SQL Dump
-- version 5.1.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: 27. Jun, 2022 23:25 PM
-- Tjener-versjon: 5.7.38-0ubuntu0.18.04.1
-- PHP Version: 7.2.24-0ubuntu0.18.04.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventory_system`
--
DROP DATABASE IF EXISTS `inventory_system`;
CREATE DATABASE IF NOT EXISTS `inventory_system` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `inventory_system`;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `blurbs`
--

DROP TABLE IF EXISTS `blurbs`;
CREATE TABLE `blurbs` (
  `id` int(11) NOT NULL,
  `npc_id` int(11) DEFAULT '0' COMMENT 'For organizational purposes only!',
  `comments` text NOT NULL COMMENT 'To help with organization.',
  `quest_id` int(11) NOT NULL DEFAULT '0',
  `emote` varchar(512) NOT NULL,
  `blurb_text` varchar(1024) NOT NULL COMMENT 'Max 1024 characters.\r\n1000 characters is safe.',
  `choices` varchar(255) NOT NULL DEFAULT 'OK',
  `choice_data` varchar(255) NOT NULL DEFAULT 'exit'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `character_bank`
--

DROP TABLE IF EXISTS `character_bank`;
CREATE TABLE `character_bank` (
  `char_id` int(11) NOT NULL,
  `stored_money` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `character_inventory`
--

DROP TABLE IF EXISTS `character_inventory`;
CREATE TABLE `character_inventory` (
  `char_id` int(11) NOT NULL,
  `item_1` varchar(255) NOT NULL DEFAULT '0',
  `item_2` varchar(255) NOT NULL DEFAULT '0',
  `item_3` varchar(255) NOT NULL DEFAULT '0',
  `item_4` varchar(255) NOT NULL DEFAULT '0',
  `item_5` varchar(255) NOT NULL DEFAULT '0',
  `item_6` varchar(255) NOT NULL DEFAULT '0',
  `item_7` varchar(255) NOT NULL DEFAULT '0',
  `item_8` varchar(255) NOT NULL DEFAULT '0',
  `item_9` varchar(255) NOT NULL DEFAULT '0',
  `money` int(11) NOT NULL DEFAULT '50',
  `gather_attempts` int(255) NOT NULL DEFAULT '10',
  `next_gather` varchar(255) NOT NULL DEFAULT '0',
  `deleted` int(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `character_storage`
--

DROP TABLE IF EXISTS `character_storage`;
CREATE TABLE `character_storage` (
  `num` bigint(20) UNSIGNED ZEROFILL NOT NULL COMMENT 'Number of entry on the list.',
  `char_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_texture` varchar(255) NOT NULL DEFAULT 'blank',
  `amount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `crafters`
--

DROP TABLE IF EXISTS `crafters`;
CREATE TABLE `crafters` (
  `char_id` int(11) NOT NULL COMMENT 'Which character has which job.',
  `job` varchar(255) NOT NULL,
  `experience` varchar(255) NOT NULL DEFAULT '0',
  `exp_to_level` varchar(255) NOT NULL DEFAULT '10',
  `level` varchar(255) NOT NULL DEFAULT '1',
  `max_level` varchar(255) NOT NULL DEFAULT '5'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `crafting_jobs`
--

DROP TABLE IF EXISTS `crafting_jobs`;
CREATE TABLE `crafting_jobs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `required_tool` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `crafting_recipes`
--

DROP TABLE IF EXISTS `crafting_recipes`;
CREATE TABLE `crafting_recipes` (
  `id` int(11) NOT NULL,
  `job_id` int(255) NOT NULL,
  `job_level_requirement` varchar(255) NOT NULL DEFAULT '1',
  `recipe_name` varchar(255) NOT NULL,
  `material_one` varchar(255) NOT NULL DEFAULT '0',
  `material_two` varchar(255) NOT NULL DEFAULT '0',
  `material_three` varchar(255) NOT NULL DEFAULT '0',
  `material_four` varchar(255) NOT NULL DEFAULT '0',
  `required_item` int(11) NOT NULL DEFAULT '0',
  `high_quality` int(11) NOT NULL DEFAULT '0',
  `result` int(11) NOT NULL DEFAULT '0' COMMENT 'Resulting item from the craft',
  `result_min_amount` varchar(255) NOT NULL DEFAULT '1',
  `result_max_amount` varchar(255) NOT NULL DEFAULT '3',
  `experience_min` varchar(255) NOT NULL DEFAULT '1',
  `experience_max` varchar(255) NOT NULL DEFAULT '5'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `faction`
--

DROP TABLE IF EXISTS `faction`;
CREATE TABLE `faction` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(16000) NOT NULL DEFAULT 'n/a',
  `pronoun` varchar(255) DEFAULT NULL,
  `starter_rank` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `faction_members`
--

DROP TABLE IF EXISTS `faction_members`;
CREATE TABLE `faction_members` (
  `num` int(11) NOT NULL,
  `char_id` int(11) NOT NULL,
  `char_name` varchar(255) NOT NULL,
  `char_faction` int(11) NOT NULL,
  `char_faction_rank` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `faction_ranks`
--

DROP TABLE IF EXISTS `faction_ranks`;
CREATE TABLE `faction_ranks` (
  `id` int(11) NOT NULL,
  `rank_sort` int(11) NOT NULL DEFAULT '8' COMMENT 'For rank sorting, highest is 1, lowest is however many ranks you''ve got',
  `rank_name` varchar(255) DEFAULT NULL,
  `rank_faction` int(11) NOT NULL,
  `rank_pronoun` varchar(255) DEFAULT NULL,
  `rank_permissions` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id` int(11) NOT NULL COMMENT 'leave empty, auto increments',
  `name` text NOT NULL COMMENT 'Limited to 12 characters.',
  `description` text NOT NULL COMMENT 'Limit to 300 characters ideally.',
  `type` varchar(255) NOT NULL DEFAULT 'material',
  `assoc_job` int(11) NOT NULL DEFAULT '0',
  `vendor_value` int(11) NOT NULL DEFAULT '0' COMMENT 'value 0 = non-purchaseable',
  `sell_price` int(11) NOT NULL DEFAULT '0' COMMENT 'price 0 = unsellable',
  `max_stack` int(11) NOT NULL DEFAULT '0',
  `texture_name` varchar(255) NOT NULL DEFAULT 'pouch',
  `texture_color` varchar(255) NOT NULL DEFAULT '<255,255,255>',
  `usable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 if craftable',
  `use_effect` varchar(255) NOT NULL DEFAULT '0' COMMENT 'material_id:amount,material_id:amount, etc.',
  `max_gather` int(11) NOT NULL DEFAULT '5',
  `gather_success_chance` float NOT NULL DEFAULT '0.2'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Item list for Lismore';

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `items_effects`
--

DROP TABLE IF EXISTS `items_effects`;
CREATE TABLE `items_effects` (
  `id` int(11) NOT NULL,
  `what_for` varchar(16000) NOT NULL COMMENT 'Which item is this for?',
  `effect_out` varchar(6000) NOT NULL COMMENT 'What to output.'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `entry` int(11) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `username` varchar(255) NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `char_id` int(11) NOT NULL,
  `char_name` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `log` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `npc_table`
--

DROP TABLE IF EXISTS `npc_table`;
CREATE TABLE `npc_table` (
  `npc_id` int(11) NOT NULL,
  `npc_name` varchar(255) NOT NULL,
  `opening_blurb` int(11) NOT NULL DEFAULT '0',
  `vendor` int(11) NOT NULL DEFAULT '0',
  `vendor_table_row` int(11) NOT NULL DEFAULT '1',
  `dialogue_spreadsheet` varchar(16000) NOT NULL COMMENT 'URLs to NPC dialogue sheets'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `quests`
--

DROP TABLE IF EXISTS `quests`;
CREATE TABLE `quests` (
  `quest_id` int(11) NOT NULL,
  `questline` int(11) NOT NULL DEFAULT '0',
  `quest_name` varchar(255) NOT NULL,
  `quest_desc` varchar(255) NOT NULL,
  `quest_string` varchar(255) NOT NULL,
  `blurb` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `money` int(11) NOT NULL COMMENT 'deprecated',
  `money_name` varchar(255) NOT NULL COMMENT 'Name of currency',
  `coercion` int(11) NOT NULL COMMENT 'Coercive features',
  `gathercooldown` int(11) NOT NULL DEFAULT '86400',
  `max_gather_attempts` int(11) NOT NULL DEFAULT '5'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `vendor_tables`
--

DROP TABLE IF EXISTS `vendor_tables`;
CREATE TABLE `vendor_tables` (
  `id` int(11) NOT NULL,
  `items_list` varchar(16400) NOT NULL DEFAULT '0',
  `description` varchar(255) NOT NULL COMMENT 'Not used in program; fill with info on who uses row/what it''s for'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `blurbs`
--
ALTER TABLE `blurbs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `character_bank`
--
ALTER TABLE `character_bank`
  ADD UNIQUE KEY `char_id` (`char_id`);

--
-- Indexes for table `character_inventory`
--
ALTER TABLE `character_inventory`
  ADD UNIQUE KEY `inventory_charId` (`char_id`),
  ADD KEY `money` (`money`);

--
-- Indexes for table `character_storage`
--
ALTER TABLE `character_storage`
  ADD PRIMARY KEY (`num`),
  ADD KEY `char_id` (`char_id`) USING BTREE;

--
-- Indexes for table `crafters`
--
ALTER TABLE `crafters`
  ADD UNIQUE KEY `char_ids` (`char_id`);

--
-- Indexes for table `crafting_jobs`
--
ALTER TABLE `crafting_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `crafternames` (`name`);

--
-- Indexes for table `crafting_recipes`
--
ALTER TABLE `crafting_recipes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `faction`
--
ALTER TABLE `faction`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faction_members`
--
ALTER TABLE `faction_members`
  ADD PRIMARY KEY (`num`),
  ADD UNIQUE KEY `char_id` (`char_id`);

--
-- Indexes for table `faction_ranks`
--
ALTER TABLE `faction_ranks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `items_effects`
--
ALTER TABLE `items_effects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`entry`),
  ADD KEY `ids` (`username`,`char_id`),
  ADD KEY `time` (`time`);

--
-- Indexes for table `npc_table`
--
ALTER TABLE `npc_table`
  ADD PRIMARY KEY (`npc_id`);

--
-- Indexes for table `quests`
--
ALTER TABLE `quests`
  ADD PRIMARY KEY (`quest_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD UNIQUE KEY `money_name` (`money_name`);

--
-- Indexes for table `vendor_tables`
--
ALTER TABLE `vendor_tables`
  ADD UNIQUE KEY `npcIdUnique` (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `blurbs`
--
ALTER TABLE `blurbs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `character_storage`
--
ALTER TABLE `character_storage`
  MODIFY `num` bigint(20) UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT COMMENT 'Number of entry on the list.';

--
-- AUTO_INCREMENT for table `crafting_jobs`
--
ALTER TABLE `crafting_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crafting_recipes`
--
ALTER TABLE `crafting_recipes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faction`
--
ALTER TABLE `faction`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faction_members`
--
ALTER TABLE `faction_members`
  MODIFY `num` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faction_ranks`
--
ALTER TABLE `faction_ranks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'leave empty, auto increments';

--
-- AUTO_INCREMENT for table `items_effects`
--
ALTER TABLE `items_effects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `entry` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `npc_table`
--
ALTER TABLE `npc_table`
  MODIFY `npc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quests`
--
ALTER TABLE `quests`
  MODIFY `quest_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_tables`
--
ALTER TABLE `vendor_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
--
-- Database: `rp_tool`
--
DROP DATABASE IF EXISTS `rp_tool`;
CREATE DATABASE IF NOT EXISTS `rp_tool` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `rp_tool`;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `rp_tool_character_repository`
--

DROP TABLE IF EXISTS `rp_tool_character_repository`;
CREATE TABLE `rp_tool_character_repository` (
  `character_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `char_name` varchar(255) DEFAULT NULL,
  `date_created` text NOT NULL,
  `last_loaded` text NOT NULL,
  `constants` varchar(255) NOT NULL DEFAULT 'Name:=>Species:=>Mood:=>Info:=>Body:=>Scent:=>Currently:=>Energy:',
  `titles` varchar(255) NOT NULL DEFAULT 'My name=>My species=>My mood=>My info=>My body=>My scent=>My action=>100',
  `settings` varchar(2000) NOT NULL DEFAULT '255,255,255=>1.0=>0=>0=>100=>1' COMMENT '"setting"=>"value" etc',
  `deleted` int(1) NOT NULL DEFAULT '0' COMMENT 'Deleted = 1',
  `job` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Tabellstruktur for tabell `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` text NOT NULL,
  `uuid` text NOT NULL,
  `version` varchar(150) NOT NULL COMMENT 'For statistics',
  `lastchar` int(11) DEFAULT '0',
  `registered` varchar(100) NOT NULL,
  `lastactive` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='User repo for RP Tool';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `rp_tool_character_repository`
--
ALTER TABLE `rp_tool_character_repository`
  ADD PRIMARY KEY (`character_id`),
  ADD KEY `dtrack` (`deleted`),
  ADD KEY `job` (`job`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `rp_tool_character_repository`
--
ALTER TABLE `rp_tool_character_repository`
  MODIFY `character_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
