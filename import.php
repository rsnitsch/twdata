<?php
    /**
     * @author:  Robert Nitsch
     * @package: TWData
     */
    
    error_reporting(E_ALL);

    require("twdata.php");

    if(!isset($_SERVER['argc']))
        die("must be run from shell\n");
    
    if(file_exists("import.php.lock")) {
        die("import.php.lock exists. import.php may already be running.\n");
    }
    
    // create lock file
    file_put_contents("import.php.lock", "");
    
    // execute main script
    main();
    
    // remove lock file
    unlink("import.php.lock");

    // main script
    function main() {
        // get and validate arguments
        $argc = $_SERVER['argc'];
        $argv = $_SERVER['argv'];
        
        if($argc < 2) {
            unlink("import.php.lock");
            die("usage: ".__FILE__." <file1> [<file2>] [<file3>] ...\n");
        }
        
        // dont import any file twice
        $updated = array();
        
        // for preg_match() calls
        $match = array();
        
        array_shift($argv);
        
        $start = microtime(true);
        
        $i = 0;
        
        foreach($argv as $arg) {
            // 3 things to check:
            //  1. must be a readable file
            //  2. must match the worlddata filename pattern: <worldid>_<datatype>.txt
            //  3. must not be updated already (if duplicated entry)
            if(is_file($arg) &&
               is_readable($arg) &&
               preg_match("/([a-z0-9]+)_(ally|player|village|conquer).txt/i",
                          basename($arg), $match) &&
               array_search($arg, $updated) === false) {
                
                $world_id = $match[1];  // e.g. contains de58 for de58_ally.txt
                $type     = $match[2];  // e.g. contains ally for de58_ally.txt
                
                // does a world with such an ID exist?
                if(TWData::get_worldurl($world_id) !== false) {
                    // then import
                    import($world_id, $type, $arg);
                    $i++;
                }
                else {
                    echo "World '$world_id' does not exist!\n";
                }
            }
            else {
                echo "Not importing '$arg' (not a file? not readable? invalid filename?)\n";
            }
        }
        
        $end = microtime(true);
        
        $duration = round($end - $start,  3);
        $average =  round($duration / $i, 3);
        
        echo "$i imports done in $duration seconds (average: $average seconds)\n";
    }
    
    /**
     * The pattern is as follows:
     * 
     * 1. Check whether the table for the given world and the given data already exists.
     *     If it DOES already exist:      empty it for fast import
     *     If it DOES NOT already exist:  create it with the table creation code given in the config file
     *     
     * 2. Import the data file using LOAD DATA INFILE
     * 
     * 3. Perform post-import tasks. Currently just OPTIMIZE TABLE (although unsure whether it has an
     *    effect at all).
     */
    function import($world_id, $type, $filepath) {
        try {
            // get the right table creation code.
            // this piece of code also ensures a valid $type argument is given.
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
            
            // get database connection
            $pdo = TWData::get_db_connection();
            
            // check whether the table already exists
            if(!TWData::table_exists("{$world_id}_{$type}")) {
                // the data table must be created first
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
            
            // import
            echo "{$world_id}: importing {$type}\n";
            $sql = "LOAD DATA INFILE ".$pdo->quote(realpath($filepath))."
                       INTO TABLE `{$world_id}_{$type}`
                       FIELDS TERMINATED BY '\t'
                       LINES TERMINATED BY '\n'";
            $pdo->exec($sql);
            
            // optimize table (although this should not be needed (?))
            echo "{$world_id}: optimizing {$type} table\n";
            $pdo->query("OPTIMIZE TABLE {$world_id}_{$type}")->fetchAll();
            
            echo "{$world_id}: {$type}-import done\n";
        } catch(PDOException $exc) {
            // print
            echo "PDO - ERROR: ".$exc->getMessage()."\n";
            
            // log
            //trigger_error("ERROR: ".$exc->getMessage());
        } catch(InvalidArgumentException $exc) {
            // evil failure
            //trigger_error("FATAL ERROR: ".$exc->getMessage());
            die("FATAL ERROR: ".$exc->getMessage());
        }
    }
?>