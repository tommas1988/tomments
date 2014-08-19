CREATE TABLE IF NOT EXISTS `comment` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `parent_id` INT UNSIGNED DEFAULT NULL,
        `origin_id` INT UNSIGNED DEFAULT NULL,
        `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `child_count` TINYINT UNSIGNED DEFAULT NULL,
        `target_id` INT UNSIGNED NOT NULL,
        `comment` TEXT NOT NULL,
        `date` DATETIME
);
