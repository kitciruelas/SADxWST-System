-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 08, 2024 at 11:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dormio_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_details` text DEFAULT NULL,
  `activity_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `activity_type`, `activity_details`, `activity_timestamp`) VALUES
(474, 95, 'Logout', 'Kitss (General User), logged out.', '2024-12-02 11:16:56'),
(475, 95, 'Logout', 'Kitss (General User), logged out.', '2024-12-02 11:18:21'),
(476, 95, 'Logout', 'Kitss (General User), logged out.', '2024-12-03 00:00:29'),
(477, 95, 'Check-Out Visitor', 'Visitor \'kc\' checked out.', '2024-12-03 05:01:06'),
(478, 95, 'Edit Visitor', 'Visitor ID \'76\' updated successfully.', '2024-12-03 05:01:32'),
(479, 95, 'Archive Visitor', 'Visitor \'adsfs\' archived.', '2024-12-03 05:03:27'),
(480, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 07:43:48'),
(481, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 07:44:19'),
(482, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 07:49:37'),
(483, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-03 07:50:12'),
(484, 99, 'Add Visitor', 'Visitor \'xzcxz\' added successfully.', '2024-12-03 07:51:20'),
(485, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 07:56:48'),
(486, 99, 'Create Payment', 'Created payment of amount 121', '2024-12-03 08:27:35'),
(487, 99, 'Update Payment', 'Updated payment ID 47', '2024-12-03 08:28:59'),
(488, 99, 'Delete Payment', 'Deleted payment ID 47', '2024-12-03 08:29:44'),
(489, 99, 'Create Payment', 'Created payment of amount 21332 to resident ERYER sd', '2024-12-03 08:31:36'),
(490, 99, 'Update Payment', 'Updated payment ID 48 to resident Unknown Resident', '2024-12-03 08:31:42'),
(491, 99, 'Update Payment', 'Updated payment ID 48 to resident Unknown Resident', '2024-12-03 08:31:47'),
(492, 99, 'Update Payment', 'Updated payment ID 48 to resident Unknown Resident', '2024-12-03 08:31:52'),
(493, 99, 'Delete Payment', 'Deleted payment ID 48 to resident ERYER sd', '2024-12-03 08:31:56'),
(494, 99, 'Update Payment', 'Updated payment ID 46 to resident Unknown Resident', '2024-12-03 08:34:29'),
(495, 99, 'Update Payment', 'Updated payment ID 46 to resident Unknown Resident', '2024-12-03 08:35:54'),
(496, 99, 'Update Payment', 'Updated payment ID 46 to resident ERYER sd', '2024-12-03 08:37:07'),
(497, 99, 'Create Payment', 'Payment of 2131 created for resident asdsad sadsad', '2024-12-03 08:39:37'),
(498, 99, 'Update Payment', 'Payment ID 49 updated for resident asdsad sadsad', '2024-12-03 08:39:42'),
(499, 99, 'Delete Payment', 'Payment ID 49 deleted for resident asdsad sadsad', '2024-12-03 08:39:45'),
(500, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 09:13:27'),
(501, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-03 09:18:02'),
(502, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-03 09:20:46'),
(503, 91, 'Logout', 'ERYER (General User), logged out.', '2024-12-03 11:30:23'),
(504, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 03:23:07'),
(505, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 03:42:08'),
(506, 101, 'Logout', 'Geo (Staff), logged out.', '2024-12-04 03:42:41'),
(507, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-04 03:43:04'),
(508, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 05:59:11'),
(509, 91, 'Check-Out Visitor', 'Visitor \'xzcxz\' checked out.', '2024-12-04 06:15:14'),
(510, 91, 'Logout', 'ERYER (General User), logged out.', '2024-12-04 06:55:11'),
(511, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-04 06:56:24'),
(512, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-04 07:23:55'),
(513, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 07:29:43'),
(514, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-04 09:06:34'),
(515, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-04 10:21:03'),
(516, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-04 10:21:22'),
(517, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 11:45:42'),
(518, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 12:13:22'),
(519, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-04 12:40:40'),
(520, 91, 'Logout', 'ERYER (General User), logged out.', '2024-12-05 01:07:16'),
(521, 99, 'Update Announcement', 'Updated announcement ID 31', '2024-12-06 03:42:37'),
(522, 99, 'Add Announcement', 'Added announcement titled \'FDSFDS\'', '2024-12-06 03:42:54'),
(523, 99, 'Archive Announcement', 'Archived announcement ID 41', '2024-12-06 03:43:02'),
(524, 99, 'Update Announcement', 'Updated announcement ID 31', '2024-12-06 03:44:44'),
(525, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-06 03:52:08'),
(526, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-06 03:52:55'),
(527, 99, 'Create', 'Added room: dsa', '2024-12-06 04:08:06'),
(528, 99, 'Delete', 'Deleted room number: dsa', '2024-12-06 04:08:56'),
(529, 99, 'Archive', 'Archived room number: sadas', '2024-12-06 04:12:12'),
(530, 1, 'Create', 'Added room: dsf', '2024-12-06 04:19:01'),
(531, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-06 04:23:21'),
(532, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-06 04:25:45'),
(533, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 04:26:10'),
(534, 91, 'Add Visitor', 'Visitor \'fsdf\' added successfully.', '2024-12-06 04:34:09'),
(535, 91, 'Archive Visitor', 'Visitor \'fsdf\' archived.', '2024-12-06 04:38:34'),
(536, 91, 'Logout', 'Keith Andrei (General User), logged out.', '2024-12-06 04:39:01'),
(537, 1, 'Add Visitor', 'Visitor \'3dfgrwe\' added successfully.', '2024-12-06 04:41:10'),
(538, 99, 'Delete Payment', 'Payment ID 52 deleted for resident Keith Andrei Ciruelas', '2024-12-06 04:49:10'),
(539, 99, 'Archive Payment', 'Payment ID 51 archived for resident Keith Andrei Ciruelas', '2024-12-06 04:51:09'),
(540, 99, 'Add Visitor', 'Visitor \'fdgfdgfd\' added successfully.', '2024-12-06 05:17:54'),
(541, 99, 'Delete Visitor', 'Visitor ID \'72\'', '2024-12-06 05:21:28'),
(542, 1, 'Insert', 'Assigned room number AB102', '2024-12-06 05:24:28'),
(543, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:26:19'),
(544, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:27:34'),
(545, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:27:37'),
(546, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:28:36'),
(547, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:28:52'),
(548, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:29:25'),
(549, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:29:28'),
(550, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:30:22'),
(551, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 05:30:45'),
(552, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-06 10:37:07'),
(553, 1, 'Update', 'Updated room: dsf', '2024-12-06 10:38:56'),
(554, 95, 'Room Reassignment Request', 'Requested reassignment from Room AB102 to Room ROOM 6. Reason: fdsfsdf', '2024-12-06 11:12:49'),
(555, 1, 'Status Update', 'Status changed to rejected for reassignment ID 109', '2024-12-06 11:28:48'),
(556, 1, 'Reassignment request rejected', 'Rejection email sent to 22-33950@g.batstate-u.edu.ph', '2024-12-06 11:28:53'),
(557, 1, 'Create Payment', 'Payment of 2 created for resident Keith Andrei Ciruelas', '2024-12-07 01:08:37'),
(558, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:32:50'),
(559, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:34:24'),
(560, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:38:43'),
(561, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:41:26'),
(562, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:44:58'),
(563, 91, 'Logout', 'Keith Andrei (General User), logged out.', '2024-12-07 01:47:20'),
(564, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:49:27'),
(565, 95, 'Login', 'Status: Successful', '2024-12-07 01:49:34'),
(566, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:51:52'),
(567, 95, 'Login', 'General User Boss with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 01:51:59'),
(568, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 01:54:57'),
(569, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 05:03:47'),
(570, 95, 'Room Reassignment Request', 'Requested reassignment from Room AB102 to Room 43fgd. Reason: dsadsa', '2024-12-07 05:04:05'),
(571, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 05:05:27'),
(572, 99, 'Login', 'Jhoana (Staff) with email jo@gmail.com logged in. Status: Successful', '2024-12-07 05:05:41'),
(573, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 10:43:05'),
(574, 95, 'Add Visitor', 'Visitor \'keith\' added successfully.', '2024-12-07 10:43:26'),
(575, 95, 'Check-Out Visitor', 'Visitor \'keith\' checked out.', '2024-12-07 10:43:29'),
(576, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:49:21'),
(577, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:49:46'),
(578, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:49:47'),
(579, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:51:10'),
(580, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:51:12'),
(581, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:51:13'),
(582, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-07 10:51:15'),
(583, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 11:08:13'),
(584, 99, 'Login', 'Jhoana (Staff) with email jo@gmail.com logged in. Status: Successful', '2024-12-07 11:08:21'),
(585, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 11:22:42'),
(586, 95, 'Move Out Request', 'User submitted a move out request for Room AB102.', '2024-12-07 11:23:08'),
(587, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 12:06:29'),
(588, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-07 12:07:52'),
(589, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-07 12:08:06'),
(590, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:15:44'),
(591, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 01:22:46'),
(592, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:23:46'),
(593, 95, 'Move Out Request', 'User submitted a move out request for Room AB102.', '2024-12-08 01:24:10'),
(594, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:24:26'),
(595, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:24:32'),
(596, 95, 'Move Out Request Approved', 'Admin approved move-out request for Room ID: 46', '2024-12-08 01:24:32'),
(597, 95, 'Move Out Request', 'User submitted a move out request for Room AB102.', '2024-12-08 01:27:59'),
(598, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:28:13'),
(599, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:28:18'),
(600, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:31:07'),
(601, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:31:11'),
(602, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:33:58'),
(603, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:34:47'),
(604, 99, 'Login', 'Jhoana (Staff) with email jo@gmail.com logged in. Status: Successful', '2024-12-08 01:48:15'),
(605, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:48:24'),
(606, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:48:27'),
(607, 99, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:48:44'),
(608, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:49:33'),
(609, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:51:24'),
(610, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:51:29'),
(611, 95, 'Move Out Request Approved', 'Admin approved move-out request for Room ID: 46', '2024-12-08 01:51:29'),
(612, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:52:45'),
(613, 1, 'Accessed manage move-out requests', 'Accessed manage move-out requests', '2024-12-08 01:52:53'),
(614, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-08 03:11:02'),
(615, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 03:11:10'),
(616, 95, 'Add Visitor', 'Visitor \'Boss\' added successfully.', '2024-12-08 03:27:06'),
(617, 95, 'Check-Out Visitor', 'Visitor \'Boss\' checked out.', '2024-12-08 03:27:20'),
(618, 95, 'Add Visitor', 'Visitor \'Gerio\' added successfully.', '2024-12-08 03:32:29'),
(619, 95, 'Add Visitor', 'Visitor \'dsf\' added successfully.', '2024-12-08 03:35:02'),
(620, 95, 'Archive Visitor', 'Visitor \'Boss\' archived.', '2024-12-08 03:35:34'),
(621, 95, 'Archive Visitor', 'Visitor \'Gerio\' archived.', '2024-12-08 03:35:39'),
(622, 95, 'Check-Out Visitor', 'Visitor \'dsf\' checked out.', '2024-12-08 03:36:00'),
(623, 95, 'Login', 'Boss (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 05:01:06'),
(624, 1, 'Status Update', 'Status changed to rejected for reassignment ID 110', '2024-12-08 05:16:30'),
(625, 1, 'Reassignment request rejected', 'Rejection email sent to 22-33950@g.batstate-u.edu.ph', '2024-12-08 05:16:35'),
(626, 95, 'Room Reassignment Request', 'Requested reassignment from Room AB102 to Room ROOM 6. Reason: ayuko na dito', '2024-12-08 05:17:31'),
(627, 1, 'Status Update', 'Status changed to rejected for reassignment ID 110', '2024-12-08 05:18:01'),
(628, 1, 'Reassignment request rejected', 'Rejection email sent to 22-33950@g.batstate-u.edu.ph', '2024-12-08 05:18:05'),
(629, 1, 'Status Update', 'Status changed to approved for reassignment ID 111', '2024-12-08 05:18:13'),
(630, 1, 'Reassignment request approved', 'Approval email sent to 22-33950@g.batstate-u.edu.ph', '2024-12-08 05:18:16'),
(631, 95, 'Add Visitor', 'Visitor \'Geo\' added successfully.', '2024-12-08 05:20:18'),
(632, 95, 'Check-Out Visitor', 'Visitor \'Geo\' checked out.', '2024-12-08 05:20:29'),
(633, 95, 'Logout', 'Boss (General User), logged out.', '2024-12-08 05:38:17'),
(634, 95, 'Login', 'Keith Andrei (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 05:38:25'),
(635, 95, 'Logout', 'Keith Andrei (General User), logged out.', '2024-12-08 05:57:11'),
(636, 99, 'Login', 'Jhoana (Staff) with email jo@gmail.com logged in. Status: Successful', '2024-12-08 05:57:23'),
(637, 99, 'Update', 'Updated room: 555', '2024-12-08 05:58:10'),
(638, 99, 'Update', 'Updated room: 555', '2024-12-08 05:58:24'),
(639, 99, 'Update', 'Updated room: 555', '2024-12-08 05:58:35'),
(640, 99, 'Update', 'Updated room: 555', '2024-12-08 05:58:41'),
(641, 95, 'Login', 'Keith Andrei (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 07:43:39'),
(642, 95, 'Logout', 'Keith Andrei (General User), logged out.', '2024-12-08 07:53:05'),
(643, 99, 'Login', 'Jhoana (Staff) with email jo@gmail.com logged in. Status: Successful', '2024-12-08 07:53:17'),
(644, 99, 'Logout', 'Jhoana (Staff), logged out.', '2024-12-08 08:30:55'),
(645, 95, 'Login', 'Keith Andrei (General User) with email 22-33950@g.batstate-u.edu.ph logged in. Status: Successful', '2024-12-08 08:31:03'),
(646, 95, 'Logout', 'Keith Andrei (General User), logged out.', '2024-12-08 09:03:23');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `mi` char(50) DEFAULT NULL,
  `Suffix` varchar(255) DEFAULT NULL,
  `Birthdate` date DEFAULT NULL,
  `age` int(11) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `contact` varchar(15) NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `fname`, `lname`, `mi`, `Suffix`, `Birthdate`, `age`, `Address`, `contact`, `sex`, `username`, `email`, `profile_pic`, `password`, `updated_at`) VALUES
(1, 'Admin1', 'Admin1', 'Admin', '', '2004-06-08', 20, 'Pinagbayanan San Juan Batangas', '09127262312', 'Male', 'Admin1', 'admin1@gmail.com', '../uploads/6750f3b8b8691-admin.png', '$2y$10$nHKpiHXrsRXTk0Bt.AgmqeErgF4XsEgX2Om3u1quNFVVYLSvcyuim', '2024-12-05 00:32:25'),
(3, 'ADMINAKOAKO', 'ADMIN', NULL, NULL, NULL, 0, NULL, '', 'Male', 'ADMINAKO', 'ADMIN@gmail.com', NULL, '$2y$10$sgbo553cVgOxnFr/0OijheC6p0VWQxc9jKB7QPiW1d/9Muu1WicUu', '2024-11-21 04:10:29');

-- --------------------------------------------------------

--
-- Table structure for table `announce`
--

CREATE TABLE `announce` (
  `announcementId` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `date_published` datetime DEFAULT current_timestamp(),
  `is_displayed` tinyint(1) DEFAULT 1,
  `archive_status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announce`
--

INSERT INTO `announce` (`announcementId`, `title`, `content`, `date_published`, `is_displayed`, `archive_status`) VALUES
(31, 'Pasko na', 'ilang pagdudusa nalang pasko YES\\\\\\\\r\\\\\\\\nSAS haGSHGAhgs\\r\\n', '2024-12-06 11:54:30', 1, 'active'),
(39, 'fdsfdsf', 'fsdfsdfdsf', '2024-12-06 11:14:08', 0, 'archived'),
(41, 'FDSFDS', 'FDSFSDF', '2024-12-06 11:42:54', 1, 'active'),
(42, 'dsadsdfwqeqwew', 'asdasdasd', '2024-12-06 11:57:38', 0, 'archived'),
(43, 'ewqeEWR', 'wqewqedsa', '2024-12-06 18:38:00', 0, 'archived');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `sender_id`, `receiver_id`, `message`, `timestamp`) VALUES
(74, 1, 0, 'dsds', '2024-11-25 12:13:28'),
(77, 1, 0, 'good job', '2024-11-25 12:59:38'),
(78, 91, 0, 'sdsds', '2024-11-25 13:35:37'),
(79, 1, 0, 'sasa', '2024-11-25 13:36:23'),
(80, 91, 0, 'dsadsa', '2024-11-25 13:46:40'),
(81, 1, 0, 'hahahah', '2024-11-26 23:25:16'),
(82, 99, 0, 'sasas', '2024-11-30 12:38:33'),
(83, 95, 0, 'weh\n', '2024-12-02 03:05:41');

-- --------------------------------------------------------

--
-- Table structure for table `move_out_requests`
--

CREATE TABLE `move_out_requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `target_date` date NOT NULL,
  `request_date` datetime NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_remarks` text DEFAULT NULL,
  `processed_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `move_out_requests`
--

INSERT INTO `move_out_requests` (`request_id`, `user_id`, `room_id`, `reason`, `target_date`, `request_date`, `status`, `admin_remarks`, `processed_date`) VALUES
(3, 91, 46, 'xzXZXZ', '2024-11-30', '2024-11-26 13:20:20', 'rejected', 'no', '2024-11-27 08:51:31'),
(4, 95, 46, 'AsASA', '2024-12-28', '2024-11-30 22:31:44', 'rejected', 'no', '2024-12-01 17:35:50'),
(5, 95, 57, 'sdfsfsdfds', '2024-12-02', '2024-12-02 09:01:01', 'approved', 'asdsadsadsad', '2024-12-02 09:01:21'),
(6, 95, 46, 'dsadsa', '2024-12-13', '2024-12-07 19:23:08', 'rejected', 'GFDGF', '2024-12-08 09:23:46'),
(7, 95, 46, 'ZSDFGFD', '2024-12-08', '2024-12-08 09:24:10', 'approved', 'YES\r\n', '2024-12-08 09:24:32'),
(8, 95, 46, 'SDFGSD', '2024-12-08', '2024-12-08 09:27:59', 'approved', 'sdfgsdf', '2024-12-08 09:51:29');

-- --------------------------------------------------------

--
-- Table structure for table `presencemonitoring`
--

CREATE TABLE `presencemonitoring` (
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `check_in` datetime DEFAULT current_timestamp(),
  `check_out` datetime DEFAULT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `presencemonitoring`
--

INSERT INTO `presencemonitoring` (`attendance_id`, `user_id`, `check_in`, `check_out`, `date`) VALUES
(7, 95, '2024-12-01 14:46:13', '2024-12-01 14:48:42', '2024-12-01'),
(8, 91, '2024-12-05 08:41:57', '2024-12-05 08:43:43', '2024-12-05'),
(9, 95, '2024-12-08 13:30:29', '2024-12-08 13:31:08', '2024-12-08');

-- --------------------------------------------------------

--
-- Table structure for table `rentpayment`
--

CREATE TABLE `rentpayment` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('paid','pending','overdue') DEFAULT 'pending',
  `payment_method` enum('cash','online banking') NOT NULL DEFAULT 'cash',
  `reference_number` varchar(255) DEFAULT NULL,
  `archive_status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rentpayment`
--

INSERT INTO `rentpayment` (`payment_id`, `user_id`, `amount`, `payment_date`, `status`, `payment_method`, `reference_number`, `archive_status`) VALUES
(46, 91, 23432.00, '2024-12-03', 'paid', 'cash', NULL, 'active'),
(50, 94, 2312.00, '2024-12-03', 'paid', 'cash', '', 'active'),
(51, 91, 3212.00, '2024-09-24', 'paid', 'cash', NULL, 'archived'),
(53, 91, 2.00, '2024-12-07', 'paid', 'cash', '', 'active'),
(54, 95, 1212.00, '2024-12-07', 'paid', 'cash', '', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `roomassignments`
--

CREATE TABLE `roomassignments` (
  `assignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `assignment_date` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roomassignments`
--

INSERT INTO `roomassignments` (`assignment_id`, `user_id`, `room_id`, `assignment_date`) VALUES
(40, 94, 46, '2024-11-25'),
(41, 91, 49, '2024-11-27'),
(44, 100, 46, '2024-12-01'),
(55, 95, 60, '2024-12-08');

-- --------------------------------------------------------

--
-- Table structure for table `roomfeedback`
--

CREATE TABLE `roomfeedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `feedback` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archive_status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roomfeedback`
--

INSERT INTO `roomfeedback` (`id`, `user_id`, `assignment_id`, `feedback`, `submitted_at`, `archive_status`) VALUES
(12, 91, 41, 'goods yeahSD HAHAHHAASa', '2024-11-25 14:51:00', 'active'),
(18, 91, 41, 'dfgdfgsadasdSAsa', '2024-11-26 08:51:36', 'archived');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `room_desc` varchar(255) DEFAULT NULL,
  `room_pic` varchar(255) DEFAULT NULL,
  `room_monthlyrent` decimal(10,2) NOT NULL,
  `capacity` int(11) NOT NULL,
  `status` enum('available','occupied','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archive_status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_number`, `room_desc`, `room_pic`, `room_monthlyrent`, `capacity`, `status`, `created_at`, `archive_status`) VALUES
(46, 'AB102', 'sadsadsad', '../uploads/pexels-jplenio-1435075.jpg', 2311.00, 3, 'available', '2024-11-25 01:57:32', 'active'),
(49, 'aass', 'dBCVSFB', '../uploads/pexels-stywo-1261728.jpg,../uploads/pexels-picturemechaniq-1749303.jpg,../uploads/pexels-eberhardgross-1367192.jpg', 341234.00, 1, 'occupied', '2024-11-25 02:56:19', 'active'),
(57, 'sadas', 'sfdfsdf', '../uploads/QR_Code_User_1.png', 234.00, 2, 'available', '2024-12-01 11:59:14', 'archived'),
(60, 'ROOM 6', 'gdsfg', '../uploads/like_6605663.png,../uploads/Dormio-QR_Code_Keith_Ciruelas_Room_AB102_1.png,../uploads/QR_Code_User_1.png', 435.00, 2, 'available', '2024-12-06 04:19:01', 'active'),
(61, '43fgd', 'fdgdfg', '../uploads/pexels-jplenio-1435075.jpg,../uploads/pexels-king-siberia-1123639-2277981.jpg,../uploads/pexels-stywo-1261728.jpg', 43453.00, 43, 'available', '2024-12-06 10:51:51', 'archived'),
(62, 'sd', 'sfd', '../uploads/pexels-jplenio-1435075.jpg,../uploads/pexels-king-siberia-1123639-2277981.jpg,../uploads/pexels-john-lester-pantaleon-782820693-27076936.jpg', 32.00, 3, 'maintenance', '2024-12-06 10:59:42', 'active'),
(63, '555', 'awsedrf', '../uploads/roronoa-zoro-neon-2560x1080-19829.png,../uploads/8726048_home_icon.png,../uploads/like_6605663.png', 434.00, 3, 'available', '2024-12-08 05:14:36', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `room_reassignments`
--

CREATE TABLE `room_reassignments` (
  `reassignment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `old_room_id` int(11) DEFAULT NULL,
  `new_room_id` int(11) NOT NULL,
  `reassignment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_reassignments`
--

INSERT INTO `room_reassignments` (`reassignment_id`, `user_id`, `old_room_id`, `new_room_id`, `reassignment_date`, `status`, `comment`) VALUES
(94, 95, NULL, 46, '2024-11-26 02:16:35', 'rejected', 'sadsad'),
(98, 91, 46, 49, '2024-11-27 00:54:49', 'approved', 'hgjh'),
(99, 91, 49, 46, '2024-11-30 03:07:13', 'rejected', 'sadasdsa'),
(100, 91, 49, 46, '2024-11-30 03:34:19', 'rejected', 'SASASA'),
(104, 95, 46, 57, '2024-12-02 00:37:43', 'rejected', 'rghgf'),
(105, 95, 46, 57, '2024-12-02 00:41:41', 'rejected', 'tyujyt'),
(106, 95, 46, 57, '2024-12-02 00:44:55', 'rejected', 'asdasd'),
(107, 95, 46, 57, '2024-12-02 00:45:50', 'approved', 'dsfsdfsd'),
(108, 95, 57, 46, '2024-12-02 00:51:02', 'rejected', 'dsaasd'),
(109, 95, 46, 60, '2024-12-06 11:12:49', 'rejected', 'fdsfsdf'),
(110, 95, 46, 61, '2024-12-07 05:04:05', 'rejected', 'dsadsa'),
(111, 95, 46, 60, '2024-12-08 05:17:31', 'approved', 'ayuko na dito');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `mi` char(50) DEFAULT NULL,
  `Suffix` varchar(255) DEFAULT NULL,
  `Birthdate` date DEFAULT NULL,
  `age` int(11) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `contact` varchar(15) NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `role` enum('General User','Staff') NOT NULL DEFAULT 'Staff',
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `fname`, `lname`, `mi`, `Suffix`, `Birthdate`, `age`, `Address`, `contact`, `sex`, `role`, `email`, `password`, `created_at`, `profile_pic`, `status`) VALUES
(99, 'Jhoana', 'Robles', 'AHAHHAH', '', '2004-01-26', 20, 'lagunaa', '09121212122', 'Male', 'Staff', 'jo@gmail.com', '$2y$10$Id9qEW0OFtwWIod5we59jehPzbYv28C2veUFL0/ex5X0whTOPVWwu', '2024-11-26 14:51:09', NULL, 'active'),
(101, 'Geo', 'Ong', 'Ong', '', '2004-01-06', 20, '76 Palawan', '09121212122', 'Male', 'Staff', 'geos@gmail.com', '$2y$10$Pxq7Rv1dyALtQfQQRZSUhem4HTRriqUgSCEHDWwE9eLfxLkPrQFXu', '2024-12-04 03:27:07', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `lname` varchar(50) NOT NULL,
  `mi` char(50) DEFAULT NULL,
  `Suffix` varchar(255) DEFAULT NULL,
  `Birthdate` date DEFAULT NULL,
  `age` int(11) NOT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `contact` varchar(15) NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `role` enum('General User','Staff') NOT NULL DEFAULT 'General User',
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `login_time` timestamp NULL DEFAULT NULL,
  `logout_time` timestamp NULL DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fname`, `lname`, `mi`, `Suffix`, `Birthdate`, `age`, `Address`, `contact`, `sex`, `role`, `email`, `password`, `created_at`, `login_time`, `logout_time`, `profile_pic`, `status`) VALUES
(91, 'Keith Andrei', 'Ciruelas', 'Aposaga', '', '2005-06-29', 19, 'San Juan Batangas', '09127262311', 'Male', 'General User', 'ha@gmail.com', '$2y$10$k3h4hVP9mjEX5BmweEuim.RwG09m..Uzp.fbHfrsd/w7/ikDX0VMG', '2024-11-25 10:40:23', '2024-12-07 01:45:08', NULL, '../uploads/674568a782597-2022-03-02 (7).png', 'active'),
(94, 'asdsad', 'sadsad', 'sadsa', 'xcsdafsdfds', '2004-05-25', 20, 'sadasdsada', '09127262311', 'Male', 'General User', '122@gmail.com', '$2y$10$W1SHmvo6X1I6y/aG5qtRn.0LC9m5OGucorlEQqw6knMz/JQQRP6C.', '2024-11-25 11:48:46', NULL, NULL, NULL, 'active'),
(95, 'Keith Andrei', 'Bossing', 'aasad', '', '2005-06-08', 19, 'Pinagbayanan San Juan Batangas', '09121223223', 'Male', 'General User', '22-33950@g.batstate-u.edu.ph', '$2y$10$jWOXWTqzeZer8Bz4kYPlOuqr7gWSKE10ZhZrCA3k/f2egZSpnLrTK', '2024-11-26 02:09:30', '2024-12-08 08:52:23', NULL, '../uploads/67552c46db6ca-roronoa-zoro-neon-2560x1080-19829.png', 'active'),
(100, 'sa', 'SA', 'SAA', '', '2004-02-27', 20, 'Pinagbayanan San Juan Batangas', '09127262311', 'Male', 'General User', 'sas@gmail.com', '$2y$10$u9BUptrGw.558pIZkBOYY.SrMnIIv1aNo7jFD53icQrl4.J8w1oHG', '2024-11-27 03:23:03', NULL, NULL, NULL, 'active'),
(102, 'Cong', 'TV', 'V', '', '2004-02-04', 20, '87 Manila CIty', '09121212122', 'Male', 'General User', 'cong@gmail.com', '$2y$10$cJfuYc91U/XRjLXLkcQPFupsamETK/ISNWJY3pq7RRkejNbAORjwe', '2024-12-04 03:35:10', NULL, NULL, NULL, 'inactive');

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_info` varchar(100) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `visiting_user_id` int(11) DEFAULT NULL,
  `check_in_time` datetime NOT NULL,
  `check_out_time` datetime DEFAULT NULL,
  `archive_status` enum('active','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `visitors`
--

INSERT INTO `visitors` (`id`, `name`, `contact_info`, `purpose`, `visiting_user_id`, `check_in_time`, `check_out_time`, `archive_status`) VALUES
(71, 'dsfafs', '09122222221', 'fsdf', 91, '2024-12-02 09:43:00', '2024-12-02 10:01:19', 'active'),
(72, 'Ako HAHA', '09122222221', 'ewrwer', 95, '2024-12-02 10:30:00', '2024-12-02 10:34:52', 'archived'),
(80, 'kc', '09876512788', 'jdhjsdjbdjsdc', 95, '2024-12-03 10:03:00', '2024-12-03 13:01:06', 'archived'),
(83, 'fsdf', '09122222221', 'fsdf', 91, '2024-12-06 12:34:00', NULL, 'archived'),
(84, '3dfgrwe', '09122222221', 'ZDFSDFD', 91, '2024-12-06 12:41:00', NULL, 'archived'),
(85, 'fdgfdgfd', '09123456789', 'secret', 91, '2024-12-06 13:17:00', NULL, 'archived'),
(86, 'keith', '09876512788', 'dsad', 95, '2024-12-07 18:43:00', '2024-12-07 18:43:29', 'active'),
(87, 'Boss', '09876512788', 'wlaa kang pake', 95, '2024-12-08 04:27:06', '2024-12-08 11:27:20', 'archived'),
(88, 'Gerio', '09876512788', 'sadsadsad', 95, '2024-12-08 04:32:28', NULL, 'archived'),
(89, 'dsf', '09876512788', 'sdfsdf', 95, '2024-12-08 11:35:02', '2024-12-08 11:36:00', 'active'),
(90, 'Geo', '09122222221', 'Birthday', 95, '2024-12-08 13:20:18', '2024-12-08 13:20:29', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announce`
--
ALTER TABLE `announce`
  ADD PRIMARY KEY (`announcementId`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_receiver` (`sender_id`,`receiver_id`);

--
-- Indexes for table `move_out_requests`
--
ALTER TABLE `move_out_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `presencemonitoring`
--
ALTER TABLE `presencemonitoring`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `rentpayment`
--
ALTER TABLE `rentpayment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roomassignments`
--
ALTER TABLE `roomassignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_room_id` (`room_id`);

--
-- Indexes for table `roomfeedback`
--
ALTER TABLE `roomfeedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `room_reassignments`
--
ALTER TABLE `room_reassignments`
  ADD PRIMARY KEY (`reassignment_id`),
  ADD KEY `new_room_id` (`new_room_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_old_room` (`old_room_id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visitors_ibfk_1` (`visiting_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=647;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announce`
--
ALTER TABLE `announce`
  MODIFY `announcementId` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `move_out_requests`
--
ALTER TABLE `move_out_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `presencemonitoring`
--
ALTER TABLE `presencemonitoring`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `rentpayment`
--
ALTER TABLE `rentpayment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `roomassignments`
--
ALTER TABLE `roomassignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `roomfeedback`
--
ALTER TABLE `roomfeedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `room_reassignments`
--
ALTER TABLE `room_reassignments`
  MODIFY `reassignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `move_out_requests`
--
ALTER TABLE `move_out_requests`
  ADD CONSTRAINT `move_out_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `move_out_requests_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Constraints for table `presencemonitoring`
--
ALTER TABLE `presencemonitoring`
  ADD CONSTRAINT `presencemonitoring_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rentpayment`
--
ALTER TABLE `rentpayment`
  ADD CONSTRAINT `rentpayment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `roomassignments`
--
ALTER TABLE `roomassignments`
  ADD CONSTRAINT `fk_roomassignments_room_id` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_roomassignments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `roomfeedback`
--
ALTER TABLE `roomfeedback`
  ADD CONSTRAINT `roomfeedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `roomfeedback_ibfk_2` FOREIGN KEY (`assignment_id`) REFERENCES `roomassignments` (`assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_reassignments`
--
ALTER TABLE `room_reassignments`
  ADD CONSTRAINT `fk_old_room` FOREIGN KEY (`old_room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `room_reassignments_ibfk_1` FOREIGN KEY (`old_room_id`) REFERENCES `rooms` (`room_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `room_reassignments_ibfk_2` FOREIGN KEY (`new_room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_reassignments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visitors`
--
ALTER TABLE `visitors`
  ADD CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`visiting_user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
