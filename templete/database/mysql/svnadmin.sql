-- MySQL dump 10.13  Distrib 5.6.50, for Linux (x86_64)
--
-- Host: localhost    Database: svnadmin
-- ------------------------------------------------------
-- Server version	5.6.50-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_users` (
  `admin_user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Tên người dùng',
  `admin_user_name` varchar(45) NOT NULL COMMENT 'Tên tài thư mụcản',
  `admin_user_password` varchar(45) NOT NULL COMMENT 'Mật khẩu người dùng',
  `admin_user_phone` char(11) DEFAULT NULL COMMENT 'Số điện thoại của người dùng',
  `admin_user_email` varchar(45) DEFAULT NULL COMMENT 'Hộp thư người dùng',
  `admin_user_token` varchar(255) DEFAULT NULL COMMENT 'Mã thông báo hiện tại của người dùng',
  PRIMARY KEY (`admin_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='Quản lý người dùng hệ thống';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','admin',NULL,NULL,NULL);
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `black_token`
--

DROP TABLE IF EXISTS `black_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `black_token` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'tokenid',
  `token` varchar(200) NOT NULL COMMENT 'token content',
  `start_time` varchar(45) NOT NULL COMMENT 'Token effective time',
  `end_time` varchar(45) NOT NULL COMMENT 'Token expiration time',
  `insert_time` varchar(45) NOT NULL COMMENT 'logout time',
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Blacklist token means that tokens that have not yet reached the expiration time after the user logs out will be added to this blacklist, and the expired tokens will be removed through regular active scanning to achieve the purpose of logout is safe';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `black_token`
--

LOCK TABLES `black_token` WRITE;
/*!40000 ALTER TABLE `black_token` DISABLE KEYS */;
/*!40000 ALTER TABLE `black_token` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crond`
--

DROP TABLE IF EXISTS `crond`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `crond` (
  `crond_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sign` varchar(45) NOT NULL COMMENT 'Nhận dạng duy nhất các tệp shell và tệp nhật ký',
  `task_type` int(11) unsigned NOT NULL COMMENT 'Loại kế hoạch nhiệm vụ\r\n\r\n1 Bản backup [dump-whole amount]\r\n2 Bản backup [dump-increase]\r\n3 Bản backup [hotcopy-whole amount]\r\n4 Bản backup [hotcopy-increase]\r\n5 warehouse inspection\r\n6 shell script',
  `task_name` varchar(450) NOT NULL COMMENT 'Tên nhiệm vụ',
  `cycle_type` varchar(45) NOT NULL COMMENT 'Loại chu kỳ\r\n\r\nminute mỗi phút\r\nminute_n cứ sau N phút\r\nhour trên giờ\r\nhour_n cứ sau n giờ\r\nday Hằng ngày\r\nday_n cứ N ngày một lần\r\nweek hàng tuần\r\nmonth mỗi tháng',
  `cycle_desc` varchar(450) NOT NULL COMMENT 'Mô tả chu kỳ thực hiện',
  `status` int(11) unsigned NOT NULL COMMENT 'Trạng thái kích hoạt',
  `save_count` int(11) unsigned NOT NULL COMMENT 'Số lượng backup',
  `rep_name` varchar(255) DEFAULT NULL COMMENT 'Danh sách thư mục vận hành',
  `week` int(11) unsigned DEFAULT NULL COMMENT 'tuần',
  `day` int(11) unsigned DEFAULT NULL COMMENT 'ngày hay ngày',
  `hour` int(11) unsigned DEFAULT NULL COMMENT 'Giờ',
  `minute` int(11) unsigned DEFAULT NULL COMMENT 'phút',
  `notice` int(11) unsigned NOT NULL COMMENT '0 Đóng thông báo 1 Thông báo thành công 2 Thông báo lỗi 3 Tất cả thông báo',
  `code` varchar(45) NOT NULL COMMENT 'Biểu hiện lịch trình nhiệm vụ',
  `shell` mediumtext COMMENT 'Tập lệnh tùy chỉnh',
  `last_exec_time` varchar(45) NOT NULL COMMENT 'Thời gian thực hiện cuối cùng',
  `create_time` varchar(45) NOT NULL COMMENT 'Thêm thời gian',
  PRIMARY KEY (`crond_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crond`
--

LOCK TABLES `crond` WRITE;
/*!40000 ALTER TABLE `crond` DISABLE KEYS */;
/*!40000 ALTER TABLE `crond` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID đăng nhập',
  `log_type_name` varchar(200) NOT NULL COMMENT 'Loại nhật ký',
  `log_content` varchar(5000) NOT NULL COMMENT 'Nội dung nhật ký',
  `log_add_user_name` varchar(200) NOT NULL COMMENT 'Nhà điều hành',
  `log_add_time` varchar(45) NOT NULL COMMENT 'Thời gian hoạt động',
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bảng nhật ký';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs`
--

LOCK TABLES `logs` WRITE;
/*!40000 ALTER TABLE `logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `options` (
  `option_id` int(11) NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) NOT NULL,
  `option_value` longtext NOT NULL,
  `option_description` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name_UNIQUE` (`option_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Các mục cấu hình toàn cầu';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `options`
--

LOCK TABLES `options` WRITE;
/*!40000 ALTER TABLE `options` DISABLE KEYS */;
/*!40000 ALTER TABLE `options` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subadmin`
--

DROP TABLE IF EXISTS `subadmin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subadmin` (
  `subadmin_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subadmin_name` varchar(255) NOT NULL,
  `subadmin_password` varchar(255) NOT NULL,
  `subadmin_phone` varchar(255) DEFAULT NULL,
  `subadmin_email` varchar(255) DEFAULT NULL,
  `subadmin_status` int(255) NOT NULL,
  `subadmin_note` varchar(255) DEFAULT NULL,
  `subadmin_last_login` varchar(255) DEFAULT NULL,
  `subadmin_create_time` varchar(20) NOT NULL,
  `subadmin_tree` mediumtext,
  `subadmin_functions` mediumtext,
  `subadmin_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`subadmin_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subadmin`
--

LOCK TABLES `subadmin` WRITE;
/*!40000 ALTER TABLE `subadmin` DISABLE KEYS */;
/*!40000 ALTER TABLE `subadmin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `svn_groups`
--

DROP TABLE IF EXISTS `svn_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svn_groups` (
  `svn_group_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Id nhóm',
  `svn_group_name` varchar(200) NOT NULL COMMENT 'Tên nhóm',
  `include_user_count` int(11) NOT NULL,
  `include_group_count` int(11) NOT NULL,
  `include_aliase_count` int(11) NOT NULL,
  `svn_group_note` varchar(1000) DEFAULT NULL COMMENT 'Nhận xét nhóm',
  PRIMARY KEY (`svn_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Bảng nhóm SVN';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `svn_groups`
--

LOCK TABLES `svn_groups` WRITE;
/*!40000 ALTER TABLE `svn_groups` DISABLE KEYS */;
/*!40000 ALTER TABLE `svn_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `svn_reps`
--

DROP TABLE IF EXISTS `svn_reps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svn_reps` (
  `rep_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Mã archives',
  `rep_name` varchar(1000) NOT NULL COMMENT 'Tên archives',
  `rep_size` double DEFAULT NULL COMMENT 'Khối lượng archives',
  `rep_note` varchar(1000) DEFAULT NULL COMMENT 'Ghi chú archives',
  `rep_rev` int(11) DEFAULT NULL COMMENT 'Sửa đổi archives',
  `rep_uuid` varchar(45) DEFAULT NULL COMMENT 'UUID archives',
  PRIMARY KEY (`rep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='table archives';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `svn_reps`
--

LOCK TABLES `svn_reps` WRITE;
/*!40000 ALTER TABLE `svn_reps` DISABLE KEYS */;
/*!40000 ALTER TABLE `svn_reps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `svn_second_pri`
--

DROP TABLE IF EXISTS `svn_second_pri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svn_second_pri` (
  `svn_second_pri_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `svnn_user_pri_path_id` int(10) unsigned NOT NULL,
  `svn_object_type` varchar(255) NOT NULL,
  `svn_object_name` varchar(255) NOT NULL,
  PRIMARY KEY (`svn_second_pri_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `svn_second_pri`
--

LOCK TABLES `svn_second_pri` WRITE;
/*!40000 ALTER TABLE `svn_second_pri` DISABLE KEYS */;
/*!40000 ALTER TABLE `svn_second_pri` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `svn_user_pri_paths`
--

DROP TABLE IF EXISTS `svn_user_pri_paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svn_user_pri_paths` (
  `svnn_user_pri_path_id` int(11) NOT NULL AUTO_INCREMENT,
  `rep_name` varchar(1000) NOT NULL COMMENT 'warehouse name',
  `pri_path` mediumtext NOT NULL COMMENT 'warehouse path',
  `rep_pri` varchar(45) DEFAULT NULL COMMENT 'The permissions that this user has',
  `svn_user_name` varchar(200) NOT NULL COMMENT 'Owner of the permissions for this path',
  `unique` varchar(20000) NOT NULL COMMENT 'Unique values ​​concatenated using repository name and path and permissions',
  `second_pri` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Is it possible to re-authorize',
  PRIMARY KEY (`svnn_user_pri_path_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The path of the warehouse that the SVN user has permission';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `svn_user_pri_paths`
--

LOCK TABLES `svn_user_pri_paths` WRITE;
/*!40000 ALTER TABLE `svn_user_pri_paths` DISABLE KEYS */;
/*!40000 ALTER TABLE `svn_user_pri_paths` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `svn_users`
--

DROP TABLE IF EXISTS `svn_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `svn_users` (
  `svn_user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'user id',
  `svn_user_name` varchar(200) CHARACTER SET utf8 NOT NULL COMMENT 'username',
  `svn_user_pass` varchar(200) CHARACTER SET utf8 NOT NULL COMMENT 'user password',
  `svn_user_status` int(1) NOT NULL COMMENT 'User Enabled Status\n0 Disabled\n1 Enabled',
  `svn_user_note` varchar(1000) CHARACTER SET utf8 DEFAULT NULL COMMENT 'User Remarks',
  `svn_user_last_login` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'Last Login Time',
  `svn_user_token` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT 'user token',
  `svn_user_mail` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT '用户token',
  PRIMARY KEY (`svn_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='svn user table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `svn_users`
--

LOCK TABLES `svn_users` WRITE;
/*!40000 ALTER TABLE `svn_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `svn_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `task_name` varchar(1000) NOT NULL,
  `task_status` tinyint(1) NOT NULL COMMENT '1 pending\r\n2 executing\r\n3 completed\r\n4 canceled\r\n5 unexpectedly interrupted',
  `task_cmd` varchar(5000) NOT NULL,
  `task_type` varchar(255) NOT NULL,
  `task_unique` varchar(255) NOT NULL,
  `task_log_file` varchar(5000) DEFAULT NULL,
  `task_optional` varchar(5000) DEFAULT NULL,
  `task_create_time` varchar(45) NOT NULL,
  `task_update_time` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `verification_code`
--

DROP TABLE IF EXISTS `verification_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_code` (
  `code_id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(45) NOT NULL COMMENT 'Unique identifier for each captcha request',
  `code` varchar(45) NOT NULL COMMENT 'verification code',
  `start_time` varchar(45) NOT NULL COMMENT 'effective start time',
  `end_time` varchar(45) NOT NULL COMMENT 'Expiration time',
  `insert_time` varchar(45) NOT NULL COMMENT 'insert time',
  PRIMARY KEY (`code_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='verification code';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_code`
--

LOCK TABLES `verification_code` WRITE;
/*!40000 ALTER TABLE `verification_code` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_code` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'svnadmin'
--

--
-- Dumping routines for database 'svnadmin'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-12-31  0:35:18
