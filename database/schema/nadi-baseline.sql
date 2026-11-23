-- MySQL dump 10.13  Distrib 8.4.13, for Linux (x86_64)
--
-- Host: localhost    Database: nadi
-- ------------------------------------------------------
-- Server version	8.4.13-0ubuntu0.26.04.2

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
-- Table structure for table `nadi_activation_grants`
--

DROP TABLE IF EXISTS `nadi_activation_grants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_activation_grants` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `registration_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `grant_version` int unsigned NOT NULL DEFAULT '1',
  `key_id` varchar(100) NOT NULL,
  `grant_payload` json NOT NULL,
  `signature` text NOT NULL,
  `grant_status` varchar(30) NOT NULL DEFAULT 'active',
  `issued_at` datetime(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_grants_application` (`application_id`),
  KEY `idx_nadi_grants_agent` (`agent_id`),
  KEY `idx_nadi_grants_registration` (`registration_id`),
  KEY `idx_nadi_grants_status_expiry` (`grant_status`,`expires_at`),
  CONSTRAINT `fk_nadi_grants_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_grants_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_grants_registration` FOREIGN KEY (`registration_id`) REFERENCES `nadi_registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_agent_commands`
--

DROP TABLE IF EXISTS `nadi_agent_commands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_agent_commands` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `command_type` varchar(50) NOT NULL,
  `command_payload` json DEFAULT NULL,
  `command_status` varchar(30) NOT NULL DEFAULT 'pending',
  `issued_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `delivered_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  `result_payload` json DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_commands_agent_status` (`agent_id`,`command_status`),
  KEY `idx_nadi_commands_application` (`application_id`),
  KEY `idx_nadi_commands_expiry` (`expires_at`),
  CONSTRAINT `fk_nadi_commands_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_commands_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_agent_configurations`
--

DROP TABLE IF EXISTS `nadi_agent_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_agent_configurations` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `config_version` int unsigned NOT NULL,
  `configuration` json NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `published_at` datetime(6) DEFAULT NULL,
  `applied_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `application_scope_key` char(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS (coalesce(`application_id`,_ascii'00000000-0000-0000-0000-000000000000')) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_config_version` (`agent_id`,`application_scope_key`,`config_version`),
  KEY `idx_nadi_config_agent_status` (`agent_id`,`status`),
  KEY `idx_nadi_config_application` (`application_id`),
  CONSTRAINT `fk_nadi_config_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_config_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_agent_credentials`
--

DROP TABLE IF EXISTS `nadi_agent_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_agent_credentials` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key_version` varchar(30) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `issued_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `expires_at` datetime(6) DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `last_used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_agent_credential_hash` (`credential_hash`),
  KEY `idx_nadi_agent_credentials_agent` (`agent_id`),
  KEY `idx_nadi_agent_credentials_status` (`status`),
  CONSTRAINT `fk_nadi_agent_credentials_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_agents`
--

DROP TABLE IF EXISTS `nadi_agents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_agents` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `server_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_instance_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_version` varchar(50) DEFAULT NULL,
  `protocol_version` varchar(30) DEFAULT NULL,
  `installation_state` varchar(30) NOT NULL DEFAULT 'installed',
  `status` varchar(30) NOT NULL DEFAULT 'registered',
  `last_control_at` datetime(6) DEFAULT NULL,
  `last_heartbeat_at` datetime(6) DEFAULT NULL,
  `installed_at` datetime(6) DEFAULT NULL,
  `uninstalled_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `active_server_id` char(36) CHARACTER SET ascii COLLATE ascii_bin GENERATED ALWAYS AS ((case when (`installation_state` = _utf8mb4'installed') then `server_id` else NULL end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_agents_instance` (`agent_instance_id`),
  UNIQUE KEY `uq_nadi_agents_active_server` (`active_server_id`),
  KEY `idx_nadi_agents_server` (`server_id`),
  KEY `idx_nadi_agents_status` (`status`),
  CONSTRAINT `fk_nadi_agents_server` FOREIGN KEY (`server_id`) REFERENCES `nadi_servers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_application_credentials`
--

DROP TABLE IF EXISTS `nadi_application_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_application_credentials` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key_version` varchar(30) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `issued_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `expires_at` datetime(6) DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `last_used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_application_credential_hash` (`credential_hash`),
  KEY `idx_nadi_application_credentials_app` (`application_id`),
  KEY `idx_nadi_application_credentials_status` (`status`),
  CONSTRAINT `fk_nadi_application_credentials_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_application_endpoints`
--

DROP TABLE IF EXISTS `nadi_application_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_application_endpoints` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `base_url` varchar(2048) NOT NULL,
  `endpoint_prefix` varchar(255) NOT NULL DEFAULT '/_nadi/v1',
  `verification_status` varchar(30) NOT NULL DEFAULT 'pending',
  `last_verified_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_application_endpoint` (`application_id`),
  CONSTRAINT `fk_nadi_endpoints_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_applications`
--

DROP TABLE IF EXISTS `nadi_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_applications` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_name` varchar(255) NOT NULL,
  `application_key` varchar(100) NOT NULL,
  `environment` varchar(50) NOT NULL,
  `framework` varchar(50) NOT NULL DEFAULT 'laravel',
  `framework_version` varchar(50) DEFAULT NULL,
  `php_version` varchar(30) DEFAULT NULL,
  `registration_status` varchar(30) NOT NULL DEFAULT 'pending',
  `activation_status` varchar(30) NOT NULL DEFAULT 'pending',
  `monitoring_status` varchar(30) NOT NULL DEFAULT 'dormant',
  `registered_at` datetime(6) DEFAULT NULL,
  `unregistered_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_applications_key` (`application_key`),
  KEY `idx_nadi_applications_agent` (`agent_id`),
  KEY `idx_nadi_applications_registration` (`registration_status`),
  KEY `idx_nadi_applications_activation` (`activation_status`),
  KEY `idx_nadi_applications_monitoring` (`monitoring_status`),
  CONSTRAINT `fk_nadi_applications_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_approval_logs`
--

DROP TABLE IF EXISTS `nadi_approval_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_approval_logs` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `registration_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `admin_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `action` varchar(30) NOT NULL,
  `reason` text,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_approval_registration` (`registration_id`),
  KEY `idx_nadi_approval_application` (`application_id`),
  KEY `idx_nadi_approval_admin` (`admin_id`),
  KEY `idx_nadi_approval_action` (`action`),
  CONSTRAINT `fk_nadi_approval_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_approval_registration` FOREIGN KEY (`registration_id`) REFERENCES `nadi_registrations` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_audit_logs`
--

DROP TABLE IF EXISTS `nadi_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_audit_logs` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_type` varchar(30) NOT NULL,
  `actor_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `server_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_audit_actor` (`actor_type`,`actor_id`),
  KEY `idx_nadi_audit_server` (`server_id`),
  KEY `idx_nadi_audit_agent` (`agent_id`),
  KEY `idx_nadi_audit_application` (`application_id`),
  KEY `idx_nadi_audit_action` (`action`),
  KEY `idx_nadi_audit_created` (`created_at`),
  CONSTRAINT `fk_nadi_audit_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_audit_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_audit_server` FOREIGN KEY (`server_id`) REFERENCES `nadi_servers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_enrollment_tokens`
--

DROP TABLE IF EXISTS `nadi_enrollment_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_enrollment_tokens` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `token_name` varchar(100) DEFAULT NULL,
  `environment_scope` varchar(50) DEFAULT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `revoked_at` datetime(6) DEFAULT NULL,
  `created_by` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_enrollment_token_hash` (`token_hash`),
  KEY `idx_nadi_enrollment_expiry` (`expires_at`),
  KEY `idx_nadi_enrollment_status` (`used_at`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_registrations`
--

DROP TABLE IF EXISTS `nadi_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_registrations` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `application_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `agent_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `registration_status` varchar(30) NOT NULL DEFAULT 'pending',
  `requested_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `reviewed_at` datetime(6) DEFAULT NULL,
  `reviewed_by` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `rejection_reason` text,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_registrations_application` (`application_id`),
  KEY `idx_nadi_registrations_agent` (`agent_id`),
  KEY `idx_nadi_registrations_status` (`registration_status`),
  KEY `idx_nadi_registrations_requested` (`requested_at`),
  CONSTRAINT `fk_nadi_registrations_agent` FOREIGN KEY (`agent_id`) REFERENCES `nadi_agents` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_nadi_registrations_application` FOREIGN KEY (`application_id`) REFERENCES `nadi_applications` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_retention_policies`
--

DROP TABLE IF EXISTS `nadi_retention_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_retention_policies` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `data_type` varchar(50) NOT NULL,
  `retention_days` int unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) DEFAULT NULL,
  `updated_by` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nadi_retention_data_type` (`data_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nadi_servers`
--

DROP TABLE IF EXISTS `nadi_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nadi_servers` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `hostname` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `os_name` varchar(100) DEFAULT NULL,
  `os_version` varchar(100) DEFAULT NULL,
  `architecture` varchar(30) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'registered',
  `last_seen_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_nadi_servers_hostname` (`hostname`),
  KEY `idx_nadi_servers_status` (`status`),
  KEY `idx_nadi_servers_last_seen` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-17  7:00:30
