CREATE TABLE IF NOT EXISTS `testdb`.`user` (
  `user_id` INT(11) NOT NULL auto_increment,
  `login_name` VARCHAR(50) CHARACTER SET 'utf8mb4' NOT NULL COMMENT 'name for login',
  `password` CHAR(60) CHARACTER SET 'utf8mb4' NOT NULL,
  `email` VARCHAR(100) CHARACTER SET 'utf8mb4' NOT NULL,
  `first_name` VARCHAR(50) CHARACTER SET 'utf8mb4' NOT NULL COMMENT 'First name',
  `last_name` VARCHAR(50) CHARACTER SET 'utf8mb4' NOT NULL,
  `street` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL,
  `zip_code` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL,
  `city` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL,
  `country_id` INT(11) NULL,
  `mobile_no` VARCHAR(50) CHARACTER SET 'utf8mb4' NULL,
  `birth_date` DATE NULL,
  `birth_place` VARCHAR(100) CHARACTER SET 'utf8mb4' DEFAULT NULL null,
  `end_date` DATETIME NULL DEFAULT NULL,
  `user_hash_value` CHAR(64) CHARACTER SET 'utf8mb4' NOT NULL,
  `is_verified` TINYINT DEFAULT '0' NULL,
  unique KEY `unique_email` (`email`),
  INDEX `index_verified_end_date` (`is_verified` ASC, `end_date` ASC),
  FULLTEXT KEY fulltext_street (street),
  unique index `fk_club_idx` (`user_id`),
  CONSTRAINT `fk_club`
    FOREIGN KEY (`user_id`)
    REFERENCES `testdb`.`club` (`club_id`)
    ON DELETE NO ACTION
    ON UPDATE CASCADE,
  PRIMARY KEY (`user_id`));

create table testdb.club (
  club_id INT,
  /*point POINT,*/
  rating DECIMAL(2,1) UNSIGNED NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'test'
);

create table testdb.user_new
(
    `user_id` INT(11) NOT NULL auto_increment,
    `club_id` INT(11) NULL,
    `geometry` geometry NOT NULL,
    `color` ENUM('red', 'blue', 'yellow') NOT NULL,
    SPATIAL INDEX `geometry` (`geometry`),
    CONSTRAINT `unique_color`
        UNIQUE (`color`),
    CONSTRAINT `fk_club`
        FOREIGN KEY (`club_id`)
            REFERENCES `testdb`.`club` (`club_id`)
            ON DELETE NO ACTION
            ON UPDATE CASCADE
);
