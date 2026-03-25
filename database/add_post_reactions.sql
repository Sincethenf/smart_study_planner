-- Add post reactions table
CREATE TABLE IF NOT EXISTS `feed_post_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` enum('like','haha','thumbs_up','angry','wow') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_reaction` (`post_id`, `user_id`),
  KEY `post_id` (`post_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add reaction count columns to feed_posts table
ALTER TABLE `feed_posts` 
  ADD COLUMN `haha_count` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `thumbs_up_count` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `angry_count` int(11) NOT NULL DEFAULT 0,
  ADD COLUMN `wow_count` int(11) NOT NULL DEFAULT 0;
