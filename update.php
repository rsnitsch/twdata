<?php
    error_reporting(E_ALL);

    require("config.php");

    if(!isset($_SERVER['argc']))
        die("must be run from shell\n");
    
    if(file_exists("update.php.lock")) {
        die("update.php.lock exists. update.php may already be running.\n");
    }
    
    // create lock file
    file_put_contents("update.php.lock", "");
    
    // execute main script
    main();
    
    // remove lock file
    unlink("update.php.lock");

    // main script
    function main() {
        // get and validate arguments
        $argc = $_SERVER['argc'];
        $argv = $_SERVER['argv'];
        
        if($argc < 3)
            die("usage: ".__FILE__." <worldid> [ally] [village] [player] [conquer]\n");
            
        $worldid = $argv[1];
        
        // update each given category once
        $updated = array('ally' => false,
                         'village' => false,
                         'player' => false,
                         'conquer' => false);
        
        foreach($argv as $arg) {
            if(array_search($arg, array_keys($updated)) !== false &&
               !$updated[$arg])
                update($worldid, $arg);
        }
    }
    
    function update($world_id, $type) {
        try {
            switch($type) {
                case 'ally':
                    $sql_create_table = TWD_CREATE_ALLY_TABLE_TEMPLATE;
                    break;
                case 'village':
                    $sql_create_table = TWD_CREATE_VILLAGE_TABLE_TEMPLATE;
                    break;
                case 'player':
                    $sql_create_table = TWD_CREATE_PLAYER_TABLE_TEMPLATE;
                    break;
                case 'conquer':
                    $sql_create_table = TWD_CREATE_CONQUER_TABLE_TEMPLATE;
                    break;
                default:
                    throw new InvalidArgumentException("Invalid update type: ".htmlspecialchars($type));
            }
            
            $pdo = get_db_connection();
            
            // check whether the table already exists
            if(!table_exists("{$world_id}_{$type}")) {
                // the ally table for this world must be created first
                $sql = str_replace("<world_id>",
                                   $world_id,
                                   $sql_create_table);
                
                $pdo->exec($sql);
                
                echo "{$world_id}: created {$type} table\n";
            }
            else {
                // the table already exists: clean it!
                $pdo->exec("DELETE FROM {$world_id}_{$type}");
                
                echo "{$world_id}: emptied {$type} table\n";
            }
            
            // download
            if(!file_exists("/tmp/{$world_id}_{$type}.txt.gz") ||
               (time() - filemtime("/tmp/{$world_id}_{$type}.txt.gz")) > 3600) {
                echo "{$world_id}: downloading {$type} data\n";
                if(!copy(get_worldurl($world_id)."/map/{$type}.txt.gz", "/tmp/{$world_id}_{$type}.txt.gz"))
                    throw new Exception("{$type} - copy failed (download)");
            }
            else {
                echo "{$world_id}: using already downloaded {$type} data\n";
            }
            
            // unzip
            if(system("gunzip -f /tmp/{$world_id}_{$type}.txt.gz") === false) {
                throw new Exception("{$type} - gunzip failed");
            }
            
            // decode
            if(system("python urldecode.py /tmp/{$world_id}_{$type}.txt") === false) {
                throw new Exception("{$type} - urldecode failed");
            }
            
            // delete original file after decoding
            unlink("/tmp/{$world_id}_{$type}.txt");
            
            // import
            echo "{$world_id}: importing {$type}\n";
            $pdo->exec("LOAD DATA INFILE '/tmp/{$world_id}_{$type}.txt.decoded'
                       INTO TABLE {$world_id}_{$type}
                       FIELDS TERMINATED BY ','
                       LINES TERMINATED BY '\n'");
            
            // delete imported file
            unlink("/tmp/{$world_id}_{$type}.txt.decoded");
            
            // optimize table (although this should not be needed (?))
            echo "{$world_id}: optimizing {$type} table\n";
            $pdo->exec("OPTIMIZE TABLE {$world_id}_{$type}");
            
            echo "{$world_id}: {$type}-import done\n";
        } catch(PDOException $exc) {
            // print
            echo "PDO - ERROR: ".$exc->getMessage();
            
            // log
            trigger_error("ERROR: ".$exc->getMessage());
        } catch(InvalidArgumentException $exc) {
            // evil failure
            trigger_error("FATAL ERROR: ".$exc->getMessage());
            die("FATAL ERROR: ".$exc->getMessage());
        }
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
    function get_worldurl($worldid) {
        $urls = null;
        
        // runtime cache: dont read the urls cache file more than 1 time!
        static $urls_unserialized = array();
        
        if(empty($urls_unserialized)) {
            // use the cache file if it exists and is not older than 1 hour.
            // otherwise re-download the ID -> URL mapping.
            if(!file_exists('cache/urls.cache') ||
               (time() - filemtime('cache/urls.cache')) > 3600) {
                
                // redownload and cache mapping
                $urls = file_get_contents('http://www.die-staemme.de/backend/get_servers.php');
                file_put_contents('cache/urls.cache', $urls);
            }
            else {
                // cache file can be used
                $urls = file_get_contents('cache/urls.cache');
            }
            
            // save unserialized assoc-array in runtime cache
            $urls_unserialized = unserialize($urls);
        }
        
        return isset($urls_unserialized[$worldid]) ? trim($urls_unserialized[$worldid], "/") : false;
    }
    
    /**
     * Establishes the connection to the database and returns the handle.
     *
     * Due to caching the connection is only established once each time the
     * script is run.
     *
     * @return: PDO
     */
    function get_db_connection() {
        static $pdo = null;
        
        if($pdo == null) {
            // for security reasons:
            // encapsulate in try-catch to prevent exceptions' backtraces to expose
            // database credentials (in case for some reason the exception isnt
            // catched otherwise)
            try {
                $pdo = new PDO("mysql:host=".TWD_MYSQL_HOST.";dbname=".TWD_MYSQL_DATABASE,
                               TWD_MYSQL_USER,
                               TWD_MYSQL_PASS,
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
     * Returns the table list as given by "SHOW TABLES;".
     *
     * This function uses caching, too, so the table list is only retrieved
     * once.
     *
     * @return: array
     */
    function get_tables() {
        static $tables = null;
        
        if(empty($tables)) {
            $pdo = get_db_connection();
            
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
    function table_exists($table) {
        $tables = get_tables();
        
        $exists = false;
        foreach($tables as $t) {
            if($t[0] == $table) {
                $exists = true;
                break;
            }
        }
        
        return $exists;
    }
?>