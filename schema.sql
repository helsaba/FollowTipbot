-- SQL TABLE FOR THE simple twitter follower tipbot
--
-- FOR supporting multiple users:
-- WHEN a USER creates an account ON the website through the twitter CONNECT,
-- CREATE a NEW TABLE  `tip_'.$user_id.'_followers`
--
-- IF you don't want multiple users, just use:   `tip_followers`
--
--
-- MYSQL DUMP for the table:
--
--
-- Table structure for table `tip_1_followers`
--

CREATE TABLE IF NOT EXISTS `tip_1_followers` (
 `id` int(20) unsigned NOT NULL AUTO_INCREMENT,
 `uid` int(12) unsigned NOT NULL,
 `fid` int(12) unsigned NOT NULL COMMENT 'my follower''s twitter id',
 `screen_name` varchar(15) NOT NULL,
 `tipped` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'tipped?',
 `confirmed` tinyint(1) NOT NULL DEFAULT '0',
 `amount` decimal(10,0) NOT NULL COMMENT 'how much tipped',
 `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`)
);
