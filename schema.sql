-- SQL TABLE FOR THE simple twitter follower tipbot
--
-- FOR supporting multiple users:
-- WHEN a USER creates an account ON the website through the twitter CONNECT,
-- CREATE a NEW TABLE  tip_'.$user_id.'_followers
--
-- IF you don't want multiple users, just use:   tip_followers
--
--
-- MYSQL DUMP for the table:
--
--
-- Table structure for table tip_1_followers
--

CREATE TABLE tip_1_followers (
 id INTEGER PRIMARY KEY ASC,
 uid INTEGER NULL,
 fid INTEGER,
 screen_name TEXT NULL,
 tipped INTEGER DEFAULT '0',
 confirmed INTEGER DEFAULT '0',
 amount REAL,
 ts timestamp DEFAULT CURRENT_TIMESTAMP
);
