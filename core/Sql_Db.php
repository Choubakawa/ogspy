<?php
/**
 * MySql database Managment Class
 *
 * @package OGSpy
 * @subpackage MySql
 * @author Kyser
 * @created 15/11/2005
 * @copyright Copyright &copy; 2007, http://ogsteam.fr/
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version 3.04b ($Rev: 7692 $)
 */

namespace Ogsteam\Ogspy;

use mysqli;

/**
 * OGSpy MySQL Database Class
 *
 * @package OGSpy
 * @subpackage MySql
 */
class Sql_Db
{
    /**
     * Instance variable
     *
     * @access private
     * @var int
     */
    private static $_instance = false; //(singleton)
    /**
     * Connection ID
     *
     * @var int
     */
    public $db_connect_id;
    /**
     * DB Result
     *
     * @var mixed
     */
    private $result;
    /**
     * Nb of Queries done
     *
     * @var int
     */
    public $nb_requete = 0;
    /**
     * last query
     *
     * @var int
     */
    private $last_query;

    /**
     * last sql timing
     *
     * @var int
     */
    private $sql_timing = 0;

    /**
     * @return int
     */
    public function getSqlTiming()
    {
        return $this->sql_timing;
    }

    /**
     * Get the current class database instance. Creates it if dosen't exists (singleton)
     *
     * @param string $sqlserver MySQL Server Name
     * @param string $sqluser MySQL User Name
     * @param string $sqlpassword MySQL User Password
     * @param string $database MySQL Database Name
     * @return int|sql_db
     */
    public static function getInstance($sqlserver, $sqluser, $sqlpassword, $database)
    {

        if (self::$_instance === false) {
            self::$_instance = new sql_db($sqlserver, $sqluser, $sqlpassword, $database);
        }

        return self::$_instance;
    }

    /**
     * Class Constructor
     *
     * @param string $sqlserver MySQL Server Name
     * @param string $sqluser MySQL User Name
     * @param string $sqlpassword MySQL User Password
     * @param string $database MySQL Database Name
     */

    private function __construct($sqlserver, $sqluser, $sqlpassword, $database)
    {
        $sql_start = benchmark();

        $this->user = $sqluser;
        $this->password = $sqlpassword;
        $this->server = $sqlserver;
        $this->dbname = $database;

        $this->db_connect_id = new mysqli($this->server, $this->user, $this->password, $this->dbname);

        /* Vérification de la connexion */
        if ($this->db_connect_id->connect_errno) {
            echo("Échec de la connexion : " . $this->db_connect_id->connect_error);
            exit();
        }

        if (!$this->db_connect_id->set_charset("utf8")) {
            echo("Erreur lors du chargement du jeu de caractères utf8 : " . $this->db_connect_id->error);
        } else {
            /*printf("Jeu de caractères courant : %s\n", $this->db_connect_id->character_set_name());*/
        }

        $this->sql_timing += benchmark() - $sql_start;
    }

    /**
     * Overload the __clone function. To forbid the use of this function for this class.
     */
    public function __clone()
    {
        throw new Exception('Cet objet ne peut pas être cloné');
    }

    /**
     * Closing the Connection with the MySQL Server
     */
    public function sql_close()
    {
        unset($this->result);
        $result = @mysqli_close($this->db_connect_id); //deconnection
        self::$_instance = false;
    }

    /**
     * MySQL Request Function
     *
     * @param string  $query The MySQL Query
     * @param boolean $Auth_dieSQLError True if a SQL error sneed to stop the application
     * @param boolean $save True to save the Query in the MySQL Logfile (if enabled)
     * @return bool|mixed|mysqli_result
     */
    public function sql_query($query = "", $Auth_dieSQLError = true, $save = true)
    {
        global $server_config;

        $sql_start = benchmark();

        if ($Auth_dieSQLError) {
            if (!($this->result = $this->db_connect_id->query($query))) {

                $this->DieSQLError($query);
            }

        } else {
            $this->last_query = $query;
            $this->result = $this->db_connect_id->query($query);
        }

        if ($save && isset($server_config["debug_log"])) {

            if ($server_config["debug_log"] == "1") {
                $fichier = "sql_" . date("ymd") . ".sql";
                $date = date("d/m/Y H:i:s");
                $ligne = "/* " . $date . " - " . $_SERVER["REMOTE_ADDR"] . " */ " . $query . ";";
                write_file(PATH_LOG_TODAY . $fichier, "a", $ligne);

            }
        }

        $this->sql_timing += benchmark() - $sql_start;

        $this->nb_requete += 1;
        return $this->result;
    }

    /**
     * Gets the result of the Query and returns it in a simple array
     *
     * @param int $query_id The Query id.
     * @return array|bool the array containing the Database result
     */
    public function sql_fetch_row($query_id = 0)
    {
        if (!$query_id) {
            $query_id = $this->result;
        }
        if ($query_id) {
            return $query_id->fetch_array();
        } else {
            return false;
        }
    }

    /**
     * Gets the result of the Query and returns it in a associative array
     *
     * @param int $query_id The Query id.
     * @return array|bool the associative array containing the Database result
     */
    public function sql_fetch_assoc($query_id = 0)
    {
        if (!$query_id) {
            $query_id = $this->result;
        }
        if ($query_id) {
            return $query_id->fetch_assoc();
        } else {
            return false;
        }
    }

    /**
     * Gets the number of results returned by the Query
     *
     * @param int $query_id The Query id.
     * @return int|bool the number of results
     */
    public function sql_numrows($query_id = 0)
    {
        if (!$query_id) {
            $query_id = $this->result;
        }
        if ($query_id) {
            $result = $query_id->num_rows;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Gets the number of affected rows by the Query
     *
     * @return int|bool the number of affected rows
     */
    public function sql_affectedrows()
    {
        if ($this->db_connect_id) {
            $result = $this->db_connect_id->affected_rows;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Identifier of the last insertion Query
     *
     * @return int|bool Returns the id
     */
    public function sql_insertid()
    {
        if ($this->db_connect_id) {
            $result = $this->db_connect_id->insert_id;
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Free MySQL ressources on the latest Query result
     *
     * @param int $query_id The Query id.
     */
    public function sql_free_result($query_id = 0)
    {
        mysqli_free_result($query_id);
    }

    /**
     * Returns the latest Query Error.
     *
     * @param int $query_id The Query id.
     */
    public function sql_error($query_id = 0)
    {
        $result["message"] = $this->db_connect_id->error;
        $result["code"] = $this->db_connect_id->errno;
        echo("<h3 style='color: #FF0000;text-align: center'>Erreur lors de la requête MySQL</h3>");
        echo("<b>- " . $result["message"] . "</b>");
        echo($this->last_query);
        exit();
    }

    /**
     * Returns the number of queries done.
     *
     * @return int The number of queries done.
     */
    public function sql_nb_requete()
    {
        return $this->nb_requete;
    }

    /**
     * Escapes all characters to set up the Query
     *
     * @param string $str The string to escape
     * @return int|bool the escaped string
     */
    public function sql_escape_string($str)
    {
        if (isset($str)) {
            return mysqli_real_escape_string($this->db_connect_id, $str);
        } else {
            return false;
        }
    }

    /**
     * Displays an Error message and exits OGSpy
     *
     * @param string $query Faulty SQL Request
     */
    private function DieSQLError($query)
    {
        echo "<table align=center border=1>\n";
        echo "<tr><td class='c' colspan='3'>Database MySQL Error</td></tr>\n";
        echo "<tr><th colspan='3'>ErrNo:" . $this->db_connect_id->errno . "</th></tr>\n";
        echo "<tr><th colspan='3'><u>Query:</u><br>" . $query . "</th></tr>\n";
        echo "<tr><th colspan='3'><u>Error:</u><br>" . $this->db_connect_id->error . "</th></tr>\n";
        echo "</table>\n";

        log_("mysql_error", array($query, $this->db_connect_id->errno, $this->db_connect_id->error, debug_backtrace()));
        exit();
    }

    /**
     * Returns the Status of the Database used size.
     * @return array [Server], et [Total]
     */
    public function db_size_info()
    {
        global $table_prefix;

        $dbSizeServer = 0;
        $dbSizeTotal = 0;

        $request = "SHOW TABLE STATUS";
        $result = $this->sql_query($request);
        while ($row = $this->sql_fetch_assoc($result)) {
            $dbSizeTotal += $row['Data_length'] + $row['Index_length'];
            if (preg_match("#^" . $table_prefix . ".*$#", $row['Name'])) {
                $dbSizeServer += $row['Data_length'] + $row['Index_length'];
            }
        }

        $bytes = array('Octets', 'Ko', 'Mo', 'Go', 'To');
        $dbSize_info = array();

        if ($dbSizeServer < 1024) {
                    $dbSizeServer = 1;
        }
        for ($i = 0; $dbSizeServer > 1024; $i++) {
                    $dbSizeServer /= 1024;
        }
        $dbSize_info["Server"] = round($dbSizeServer, 2) . " " . $bytes[$i];

        if ($dbSizeTotal < 1024) {
                    $dbSizeTotal = 1;
        }
        for ($i = 0; $dbSizeTotal > 1024; $i++) {
                    $dbSizeTotal /= 1024;
        }
        $dbSize_info["Total"] = round($dbSizeTotal, 2) . " " . $bytes[$i];

        return $dbSize_info;
    }

    /**
     * Function to Optimize all tables of the OGSpy Database
     * @param boolean $maintenance_action true if no url redirection is requested,false to redirect to another page
     */
    public function db_optimize($maintenance_action = false)
    {
        $dbSize_before = $this->db_size_info();
        $dbSize_before = $dbSize_before["Total"];

        $request = 'SHOW TABLES';
        $res = $this->sql_query($request);
        while (list($table) = $this->sql_fetch_row($res)) {
            $request = 'OPTIMIZE TABLE ' . $table;
            $this->sql_query($request);
        }

        $dbSize_after = $this->db_size_info();
        $dbSize_after = $dbSize_after["Total"];

        if (!$maintenance_action) {
            redirection("index.php?action=message&id_message=db_optimize&info=" . $dbSize_before .
                "¤" . $dbSize_after);
        }
    }

}

