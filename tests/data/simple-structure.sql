CREATE TABLE IF NOT EXISTS `testdb`.`user` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
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
  `birth_place` VARCHAR(100) CHARACTER SET 'utf8mb4' NULL DEFAULT NULL,
  `end_date` DATETIME NULL DEFAULT NULL,
  `user_hash_value` CHAR(64) CHARACTER SET 'utf8mb4' NOT NULL,
  `is_verified` TINYINT NOT NULL DEFAULT '0',
  UNIQUE KEY `unique_email` (`email`),
  INDEX `index_verified_end_date` (`is_verified` ASC, `end_date` ASC),
  CONSTRAINT `fk_club`
    FOREIGN KEY (`user_id`)
    REFERENCES `testdb`.`club` (`club_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  PRIMARY KEY (`user_id`));

create table testdb.club (
  club_id INT,
  /*point POINT,*/
  rating DECIMAL(2,1) UNSIGNED NULL,
)
