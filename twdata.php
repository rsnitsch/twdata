<?php
    require dirname(__FILE__).'/config.php';

    /**
     * @author:  Robert Nitsch
     * @package: TWData
     */
    
    final class TWData {
        private static $db_host = 'localhost';
        private static $db_name = 'twdata';
        private static $db_user = 'twdata';
        private static $db_pass = 'twdata';
        
        /**
         * Establishes the connection to the database and returns the handle.
         *
         * Due to caching the connection is only established once each time the
         * script is run.
         *
         * @return: PDO
         */
        public static function get_db_connection() {
            static $pdo = null;
            
            if($pdo == null) {
                // Parse database credentials from config file.
                if (file_exists(dirname(__FILE__).'/config.ini')) {
                    $config = parse_ini_file(dirname(__FILE__).'/config.ini', false);
                } else if (file_exists(dirname(__FILE__).'/config.sample.ini')) {
                    $config = parse_ini_file(dirname(__FILE__).'/config.sample.ini', false);
                } else {
                    throw new Exception("TWData config file does not exist.");
                }
                
                self::$db_host = $config["db_host"];
                self::$db_name = $config["db_name"];
                self::$db_user = $config["db_user"];
                self::$db_pass = $config["db_pass"];
                
                // for security reasons:
                // encapsulate in try-catch to prevent exceptions' backtraces to expose
                // database credentials (in case for some reason the exception isnt
                // catched otherwise)
                try {
                    $pdo = new PDO("mysql:host=".self::$db_host.";dbname=".self::$db_name,
                                   self::$db_user,
                                   self::$db_pass,
                                   array(PDO::ATTR_PERSISTENT => true));
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
                catch(Exception $exc) {
                    throw new Exception("PDO connection failed. Exception: ".htmlspecialchars($exc->getMessage()));
                }
            }
            
            return $pdo;
        }
        
        /**
         * Returns the URL to the world with the given ID. If there is no URL
         * mapped to this ID the function returns false.
         *
         * The URL is returned without trailing slash.
         *
         * Example:
         * >>> get_worldurl("de51")
         * http://de51.die-staemme.de
         *
         * 2-way caching ensures that the backend file containing the ID -> URL mapping
         * is downloaded at most 1 time per hour. Additionally, consecutive calls of
         * this function won't read the cache file 'urls.cache' more than 1 time.
         *
         * @return: string | false
         */
        public static function get_worldurl($worldid) {
            $urls = null;
            
            // runtime cache: dont read the urls cache file more than 1 time!
            static $urls_unserialized = array();
            
            if(empty($urls_unserialized)) {
                // use the cache file if it exists and is not older than 1 hour.
                // otherwise re-download the ID -> URL mapping.
                
                $cache_file = dirname(__FILE__)."/cache/urls.cache";
                
                if(!file_exists($cache_file) ||
                   (time() - filemtime($cache_file)) > 1800) {
                    
                    // redownload and cache mapping
                    $urls = file_get_contents('http://www.die-staemme.de/backend/get_servers.php');
                    file_put_contents($cache_file, $urls);
                }
                else {
                    // cache file can be used
                    $urls = file_get_contents($cache_file);
                }
                
                // save unserialized assoc-array in runtime cache
                $urls_unserialized = unserialize($urls);
            }
            
            return isset($urls_unserialized[$worldid]) ? trim($urls_unserialized[$worldid], "/") : false;
        }
        
        /**
         * Returns the table list as given by "SHOW TABLES;".
         *
         * This function uses caching, too, so the table list is only retrieved
         * once.
         *
         * @return: array
         */
        public static function get_tables() {
            static $tables = null;
            
            if(empty($tables)) {
                $pdo = self::get_db_connection();
                
                $tables = $pdo->query("SHOW TABLES")->fetchAll();
            }
            
            return $tables;
        }
        
        /**
         * Checks whether a table with the given name exists, making
         * use of get_tables().
         *
         * @return: boolean
         */
        public static function table_exists($table) {
            $tables = self::get_tables();
            
            $exists = false;
            foreach($tables as $t) {
                if($t[0] == $table) {
                    $exists = true;
                    break;
                }
            }
            
            return $exists;
        }
    }
?>
