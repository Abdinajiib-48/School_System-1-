-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: localhost    Database: school_db1
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary view structure for view `class_student_list`
--

DROP TABLE IF EXISTS `class_student_list`;
/*!50001 DROP VIEW IF EXISTS `class_student_list`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `class_student_list` AS SELECT 
 1 AS `class_id`,
 1 AS `class_name`,
 1 AS `academic_year`,
 1 AS `semester`,
 1 AS `homeroom_teacher`,
 1 AS `student_id`,
 1 AS `registration_number`,
 1 AS `student_name`,
 1 AS `gender`,
 1 AS `parent_phone`,
 1 AS `address`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade` varchar(10) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` varchar(10) NOT NULL,
  `homeroom_teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `homeroom_teacher_id` (`homeroom_teacher_id`),
  CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`homeroom_teacher_id`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `head_of_dept` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `marks`
--

DROP TABLE IF EXISTS `marks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `marks_obtained` int(11) NOT NULL,
  `exam_type` varchar(50) DEFAULT 'Regular',
  `entered_by` int(11) DEFAULT NULL,
  `entered_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_subject` (`student_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  KEY `entered_by` (`entered_by`),
  KEY `idx_marks_student` (`student_id`),
  KEY `idx_marks_subject` (`subject_id`),
  KEY `idx_marks_teacher` (`entered_by`),
  CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `marks_ibfk_3` FOREIGN KEY (`entered_by`) REFERENCES `teachers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `student_performance_report`
--

DROP TABLE IF EXISTS `student_performance_report`;
/*!50001 DROP VIEW IF EXISTS `student_performance_report`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `student_performance_report` AS SELECT 
 1 AS `student_id`,
 1 AS `registration_number`,
 1 AS `student_name`,
 1 AS `gender`,
 1 AS `class_name`,
 1 AS `academic_year`,
 1 AS `semester`,
 1 AS `average_marks`,
 1 AS `total_marks`,
 1 AS `subjects_taken`,
 1 AS `status`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gender` enum('M','F') NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_student_name` (`name`),
  KEY `idx_student_class` (`class_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `max_marks` int(11) DEFAULT 100,
  `department_id` int(11) DEFAULT NULL,
  `pass_mark` int(11) DEFAULT 50,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `teacher_subject_info`
--

DROP TABLE IF EXISTS `teacher_subject_info`;
/*!50001 DROP VIEW IF EXISTS `teacher_subject_info`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `teacher_subject_info` AS SELECT 
 1 AS `teacher_id`,
 1 AS `teacher_name`,
 1 AS `department_name`,
 1 AS `subject_id`,
 1 AS `subject_name`,
 1 AS `subject_code`,
 1 AS `pass_mark`,
 1 AS `email`,
 1 AS `phone`,
 1 AS `is_homeroom`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_homeroom` tinyint(4) DEFAULT 0,
  `subject_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `teachers_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_teacher_insert` BEFORE INSERT ON `teachers` FOR EACH ROW BEGIN

    DECLARE new_username VARCHAR(50);

    DECLARE new_password VARCHAR(255);

    DECLARE new_user_id INT;

    DECLARE counter INT DEFAULT 0;

    DECLARE base_username VARCHAR(50);

    

    -- Step 1: Username = EXACT name as admin typed (keep spaces)

    SET new_username = NEW.name;

    

    -- Step 2: Check if username already exists, if yes add number

    WHILE EXISTS (SELECT 1 FROM users WHERE username = new_username) DO

        SET counter = counter + 1;

        SET new_username = CONCAT(NEW.name, counter);

    END WHILE;

    

    -- Step 3: Create base username for password (remove spaces, lowercase)

    SET base_username = REPLACE(LOWER(NEW.name), ' ', '');

    

    -- Step 4: Create password = base_username + '123'

    SET new_password = CONCAT(base_username, '123');

    

    -- Step 5: Insert into users table

    INSERT INTO users (username, password, role)

    VALUES (new_username, new_password, 'teacher');

    

    -- Step 6: Get the new user_id and set it on the teacher

    SET new_user_id = LAST_INSERT_ID();

    SET NEW.user_id = new_user_id;

    

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_teacher_delete` BEFORE DELETE ON `teachers` FOR EACH ROW BEGIN

    -- Delete the associated user account

    DELETE FROM users WHERE id = OLD.user_id;

END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `class_student_list`
--

/*!50001 DROP VIEW IF EXISTS `class_student_list`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `class_student_list` AS select `c`.`id` AS `class_id`,`c`.`grade` AS `class_name`,`c`.`academic_year` AS `academic_year`,`c`.`semester` AS `semester`,`t`.`name` AS `homeroom_teacher`,`s`.`id` AS `student_id`,`s`.`student_id` AS `registration_number`,`s`.`name` AS `student_name`,`s`.`gender` AS `gender`,`s`.`parent_phone` AS `parent_phone`,`s`.`address` AS `address` from ((`classes` `c` left join `teachers` `t` on(`c`.`homeroom_teacher_id` = `t`.`id`)) left join `students` `s` on(`c`.`id` = `s`.`class_id`)) order by `c`.`grade`,`s`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `student_performance_report`
--

/*!50001 DROP VIEW IF EXISTS `student_performance_report`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `student_performance_report` AS select `s`.`id` AS `student_id`,`s`.`student_id` AS `registration_number`,`s`.`name` AS `student_name`,`s`.`gender` AS `gender`,`c`.`grade` AS `class_name`,`c`.`academic_year` AS `academic_year`,`c`.`semester` AS `semester`,round(avg(`m`.`marks_obtained`),1) AS `average_marks`,sum(`m`.`marks_obtained`) AS `total_marks`,count(`m`.`subject_id`) AS `subjects_taken`,case when round(avg(`m`.`marks_obtained`),1) >= 50 then 'PASS' else 'FAIL' end AS `status` from ((`students` `s` left join `classes` `c` on(`s`.`class_id` = `c`.`id`)) left join `marks` `m` on(`s`.`id` = `m`.`student_id`)) group by `s`.`id`,`s`.`student_id`,`s`.`name`,`s`.`gender`,`c`.`grade`,`c`.`academic_year`,`c`.`semester` order by sum(`m`.`marks_obtained`) desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `teacher_subject_info`
--

/*!50001 DROP VIEW IF EXISTS `teacher_subject_info`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `teacher_subject_info` AS select `t`.`id` AS `teacher_id`,`t`.`name` AS `teacher_name`,`d`.`name` AS `department_name`,`s`.`id` AS `subject_id`,`s`.`name` AS `subject_name`,`s`.`code` AS `subject_code`,`s`.`pass_mark` AS `pass_mark`,`t`.`email` AS `email`,`t`.`phone` AS `phone`,case when `t`.`is_homeroom` = 1 then 'Yes' else 'No' end AS `is_homeroom` from ((`teachers` `t` left join `departments` `d` on(`t`.`department_id` = `d`.`id`)) left join `subjects` `s` on(`t`.`subject_id` = `s`.`id`)) order by `d`.`name`,`t`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-10 13:06:27
