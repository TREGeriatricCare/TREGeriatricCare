-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2025 at 03:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tre_geriatric_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `card_holder_name` varchar(255) NOT NULL,
  `card_number_last_four` varchar(4) NOT NULL,
  `card_type` varchar(50) NOT NULL,
  `expiry_month_year` varchar(5) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `application_data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`application_data_json`)),
  `payment_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `full_name`, `email`, `phone_number`, `card_holder_name`, `card_number_last_four`, `card_type`, `expiry_month_year`, `amount`, `payment_method`, `transaction_id`, `application_data_json`, `payment_date`) VALUES
(1, 'ZELLAH RODRICK KIHEGULO', 'client@gmail.com', '0765330694', 'hala hala', '4444', 'Visa', '06/25', 15.00, '0', NULL, '{\"fullName\":\"ZELLAH RODRICK KIHEGULO\",\"age\":\"23\",\"gender\":\"male\",\"countryOfOrigin\":\"Tanzania\",\"currentAddress\":\"P. O Box 54230\",\"phoneNumber\":\"0765330694\",\"email\":\"client@gmail.com\",\"maritalStatus\":\"married\",\"hasPassport\":\"yes\",\"workedWithElderly\":\"no\",\"englishProficiency\":\"good\",\"whyWorkWithElderly\":\"[0j0pg wetew\",\"personalElderlyCareExperience\":\"no\",\"workPreference\":\"both\",\"physicalStrengthComfort\":\"no\",\"eagerToLearn\":\"no\",\"desirePromotion\":\"no\",\"livingPreference\":\"with_colleagues\",\"moveTimeframe\":\"yes\",\"applicationLetter\":\"i wje0jw0tew\",\"childrenDetails\":\"\",\"hasChildren\":\"no\",\"additionalTravelDocsDetails\":\"\",\"hasAdditionalTravelDocs\":\"no\",\"educationLevel\":\"diploma\",\"universityDetails\":\"\",\"additionalCertificatesDetails\":\"\",\"hasAdditionalCertificates\":\"no\",\"workExperience\":\"noj 0kwept er-p\",\"workedWithElderlyDetails\":\"\",\"personalElderlyCareExperienceDetails\":\"\",\"hobbies\":\"\",\"ref1Name\":\"\",\"ref1Title\":\"\",\"ref1Contact\":\"\",\"ref2Name\":\"\",\"ref2Title\":\"\",\"ref2Contact\":\"\"}', '2025-06-08 15:23:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
