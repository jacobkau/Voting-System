-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 16, 2024 at 01:14 PM
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
-- Database: `wandabi`
--

-- --------------------------------------------------------

--
-- Table structure for table `contesters`
--

CREATE TABLE `contesters` (
  `id` int(30) NOT NULL,
  `postname` text NOT NULL,
  `name` text NOT NULL,
  `date` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contesters`
--

INSERT INTO `contesters` (`id`, `postname`, `name`, `date`) VALUES
(1, 'Chairperson', 'Balletic Penchant ', '2024-05-07 22:03:25'),
(2, 'Vice Chairperson', 'Joyce Kau', '2024-05-07 23:46:09'),
(3, 'Secretary', 'Kivuitu Murume', '2024-05-07 23:54:40');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(30) NOT NULL,
  `name` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `name`) VALUES
(1, 'Chairperson'),
(2, 'Secretary'),
(3, 'Treasurer'),
(4, 'Vice Secretary'),
(5, 'Vice Chairperson'),
(6, 'Coordinator');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(30) NOT NULL,
  `username` varchar(50) NOT NULL,
  `name` text NOT NULL,
  `email` varchar(100) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `password` text NOT NULL,
  `date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `email`, `profile_photo`, `password`, `date`) VALUES
(2, 'Balletic', 'Balletic Penchant ', 'Balletic4@gmail.com', '1715117760_AirBrush_20231217124835.jpg', '$2y$10$heqweff9LAMBFTYjCuRqO.qUPaaXt.lT7B6c7cSZpLaIk8tpTvbM.', '2024-05-08 00:52:14.056448'),
(3, 'paul', 'Paul Kioko', 'kioko4@gmail.com', NULL, '$2y$10$7gEej.gX/WfplRQsPZa2Oupmqf9OXAAh3uKNqL2o.somGKGl0M8xC', '2024-05-08 00:52:14.056448'),
(4, 'joyce', 'Joyce Kau', 'joyce@gmail.com', NULL, '$2y$10$.Q4aCYEycSTXkynd2kz5v.lzYi6.tDsDWw1/l8rl2QYRrr/B2Pxo6', '2024-05-08 00:52:14.056448'),
(5, 'kivuitu', 'Kivuitu Murume', 'kivuitu12@gmail.com', '1715129280_Capture.PNG', '$2y$10$wzkbxhtlGYQkBkvHX.JhgusCapPRQhwwsdyx9AqScSVADvaLzR8Om', '2024-05-08 00:52:14.056448'),
(6, 'witty', 'Jacob Mwambwa', 'kaujacob4@gmail.com', '1715159280_IMG-20240416-WA0017.jpg', '$2y$10$KPOawkJI77XD8Mdctr1oVuOS.60SsDIbMPMwVBRXp8mDEXzrPcZxS', '2024-05-08 09:08:23.706847'),
(7, 'Kimotho ', 'Kimotho Mutua', 'kimotho@gmail.com', '1715156640_IMG_7273.JPG', '$2y$10$KHRzRY7ot5NxxYPooH4Ps.GA3MItuvow7FJHWID1UuaKNjRYIIFKW', '2024-05-08 08:24:52.170703');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(30) NOT NULL,
  `username` text NOT NULL,
  `date` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `chairperson` text NOT NULL,
  `vicechairperson` text NOT NULL,
  `secretary` text NOT NULL,
  `vicesecretary` text NOT NULL,
  `treasurer` text NOT NULL,
  `coordinator` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `username`, `date`, `chairperson`, `vicechairperson`, `secretary`, `vicesecretary`, `treasurer`, `coordinator`) VALUES
(1, 'kivuitu', '2024-05-08 00:09:50.138477', 'Balletic Penchant ', 'Joyce Kau', ' Kivuitu Murume', ' ', ' ', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `contesters`
--
ALTER TABLE `contesters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `contesters`
--
ALTER TABLE `contesters`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
