DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `origin_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `level` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `child_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `target_id` INT UNSIGNED NOT NULL,
        `content` TEXT NOT NULL,
        `time` TIMESTAMP NOT NULL DEFAULT 0,
        `state` TINYINT UNSIGNED DEFAULT 1,
        KEY (`level`, `target_id`),
        KEY (`origin_id`)
) ENGINE InnoDB CHARACTER SET UTF8;
