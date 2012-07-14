<?php
    /**
     * @author:  Robert Nitsch
     * @package: TWData
     */
    
    define('TWD_MYSQL_HOST', 'localhost');
    define('TWD_MYSQL_DATABASE', 'twdata');
    define('TWD_MYSQL_USER', 'twdata');
    define('TWD_MYSQL_PASS', 'nY7tpBwpfhYwBjSS');
    
    define('TWD_CREATE_ALLY_TABLE_TEMPLATE',
           "CREATE TABLE `<world_id>_ally` (
  `id` mediumint(8) unsigned NOT NULL,
  `name` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `tag` varchar(6) COLLATE utf8_unicode_ci NOT NULL,
  `members` smallint(5) unsigned NOT NULL,
  `villages` mediumint(8) unsigned NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `all_points` int(10) unsigned NOT NULL,
  `rank` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`name`,`tag`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    
    define('TWD_CREATE_VILLAGE_TABLE_TEMPLATE',
           "CREATE TABLE IF NOT EXISTS `<world_id>_village` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `x` smallint(5) unsigned NOT NULL,
  `y` smallint(5) unsigned NOT NULL,
  `player` int(11) NOT NULL,
  `points` smallint(5) unsigned NOT NULL,
  `rank` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`name`,`player`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    
    define('TWD_CREATE_PLAYER_TABLE_TEMPLATE',
           "CREATE TABLE IF NOT EXISTS `<world_id>_player` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `ally` mediumint(8) unsigned NOT NULL,
  `villages` mediumint(8) unsigned NOT NULL,
  `points` int(10) unsigned NOT NULL,
  `rank` mediumint(8) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `index` (`name`,`ally`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
    
    define('TWD_CREATE_CONQUER_TABLE_TEMPLATE',
           "CREATE TABLE IF NOT EXISTS `<world_id>_conquer` (
  `village_id` int(10) unsigned NOT NULL,
  `unix_timestamp` int(10) unsigned NOT NULL,
  `new_owner` int(10) unsigned NOT NULL,
  `old_owner` int(10) unsigned NOT NULL,
  KEY `index` (`unix_timestamp`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");
?>
