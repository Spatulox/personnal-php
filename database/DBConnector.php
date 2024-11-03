<?php

class DBConnector
{

    private $db;

    public function __construct()
    {
        // Lire le contenu du fichier JSON
        $json_file = file_get_contents('/var/www/html/env.json');

        // Décoder le contenu JSON en un tableau PHP
        $data = json_decode($json_file, true);

        $dbHost = $data['DB_HOST'];
        $dbPort = $data['DB_PORT'];
        $dbName = $data['DB_NAME'];
        $dbUser = $data['DB_USER'];
        $dbPassword = $data['DB_PASSWORD'];

        try {
            $db = new PDO(
                'mysql:host='.$dbHost.';
	        port='.$dbPort.';
	        dbname='.$dbName.';
	        user='.$dbUser.';
	        password='.$dbPassword.'',
                null,
                null,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch (Exception $e) {
            die($e);
        }

        $this->db = $db;
    }

    # -------------------------------------------------------------- #

    /**
     * Termine l'exécution du script en envoyant un message d'erreur au format JSON.
     *
     * @param string $message Le message d'erreur à afficher. Par défaut, "Internal Server Error".
     * @param int $code Le code de statut HTTP à envoyer. Par défaut, 500 (Internal Server Error).
     * @return void
     */
    private function exit_with_message($message = "Internal Server Error", $code = 500) {
        http_response_code($code);
        echo '{"message": "' . $message . '"}';
        exit();
    }

    # -------------------------------------------------------------- #

    /**
     * Vérifie si les données nécessaires pour une opération de base de données sont présentes.
     *
     * @param mixed $table Le nom de la table. -10 par défaut pour indiquer qu'aucune valeur n'a été fournie.
     * @param mixed $columnArray Un tableau ou une chaîne représentant les colonnes. -10 par défaut.
     * @param mixed $columnData Les données à insérer ou mettre à jour. -10 par défaut.
     * @param mixed $condition La condition pour les requêtes WHERE. -10 par défaut.
     * @return void
     */
    private function checkData($table = -10, $columnArray = -10, $columnData = -10, $condition = -10){
        $bool = false;

        $sentence = "Please specifie ";
        $addSentence = "";
        if (empty($table)){
            $bool = true;
            $sentence .= "the table, ";
        }
        if (empty($columnArray)){
            $bool = true;
            $sentence .= "the colums, ";
        }
        if (empty($columnData)){
            $bool = true;
            $sentence .= "the data, ";
        }

        if (empty($condition) && $condition)
        {
            $bool = true;
            $sentence .= "the condition, ";
            $addSentence .= " To apply no condition, plz give -1.";
        }

        if ($bool == true){
            $sentence .= "(to execute the function, each args has to be not null).". $addSentence;
            $this->exit_with_message($sentence);
        }
    }

    # -------------------------------------------------------------- #

    /**
     * Vérifie si un mot spécifique est présent dans un message donné.
     *
     * @param string $msg Le message dans lequel chercher.
     * @param string $wordToSearch Le mot à rechercher dans le message.
     * @return bool Retourne true si le mot est trouvé, false sinon.
     */
    private function checkMsg($msg, $wordToSearch){

        if (empty($msg) || empty($wordToSearch))
        {
            $this->exit_with_message("ERROR : Plz enter a valid msg or a valid wordToSearg in the msg");
        }

        if (strpos($msg, $wordToSearch) !== false){
            return true;
        }

        return false;
    }

    # -------------------------------------------------------------- #

    /**
     * Exécute une requête SELECT sur la base de données.
     *
     * @param string $table Le nom de la table sur laquelle effectuer la requête.
     * @param string $columns Les colonnes à sélectionner, séparées par des virgules.
     * @param mixed $condition La condition WHERE de la requête. -1 par défaut pour aucune condition.
     * @param string|null $debug Mode de débogage. Renvoie False si rien dans la base de donnée, sinon renvoie les données. NULL par défaut
     * @return array|false Retourne un tableau de résultats ou false en cas d'erreur.
     */
    public function selectDB($table, $colums, $condition = -1, $debug = NULL){
        // -1 : the user want no condition or no condition entered by the user.
        // $colums must be like that : $columns = "idusers, role"

        $this->checkData($table, $colums, -10, $condition);

        $dbRequest = 'SELECT ' . $colums . ' FROM ' . $table;
        if ($condition !== -1) {
            $dbRequest .= ' WHERE ' . $condition;
        }

        if($debug == "-@"){
            var_dump($dbRequest);
        }

        try {
            $result = $this->db->prepare($dbRequest);
            $result->execute();
            $response = $result->fetchAll();

            if (!$response) {
                if ($debug === "bool") {
                    return false;
                }
                $this->exit_with_message("Nothing to show", 404);
            }

            return $response;
        }
        catch (PDOException $e) {
            $this->exit_with_message($debug === "-@" ? "PDO Error : ".$e->getMessage() : "Something went wrong (SelectDB)", 500);
        }

        return false;
    }

    # -------------------------------------------------------------- #

    /**
     * Exécute une requête SELECT avec une jointure sur la base de données.
     *
     * @param string $table Le nom de la table principale sur laquelle effectuer la requête.
     * @param string $columns Les colonnes à sélectionner, séparées par des virgules.
     * @param string $join La clause de jointure SQL pour combiner des tables.
     * @param mixed $condition La condition WHERE de la requête. -1 par défaut pour aucune condition.
     * @param string|null $debug Mode de débogage. Renvoie False si rien dans la base de donnée, sinon renvoie les données. NULL par défaut
     * @return array|false Retourne un tableau de résultats ou false en cas d'erreur.
     */
    public function selectJoinDB($table, $columns, $join, $condition = -1, $debug = null) {
        $this->checkData($table, $columns, -10, $condition);
        if(!$join){
            $this->exit_with_message("The join condition need to be filled (SelectJoinDB)");
        }

        $dbRequest = 'SELECT ' . $columns . ' FROM ' . $table . ' ' . $join;
        if ($condition !== -1) {
            $dbRequest .= ' WHERE ' . $condition;
        }

        if ($debug === "-@") {
            var_dump($dbRequest);
        }

        try {
            $result = $this->db->prepare($dbRequest);
            $result->execute();
            $response = $result->fetchAll();

            if (!$response) {
                if ($debug === "bool") {
                    return false;
                }
                $this->exit_with_message("Nothing to show", 404);
            }
            return $response;
        } catch (PDOException $e) {
            $this->exit_with_message($debug === "-@" ? "PDO Error : ".$e->getMessage() : "Something went wrong (SelectJoinDB)", 500);
        }

        return false;
    }

    # -------------------------------------------------------------- #

    /**
     * @param $table
     * @param $columnArray
     * @param $columnData
     * @param $debug
     * @param $conditionToReturnData
     * @return array|bool
     */
    public function insertDB($table, $columnArray, $columnData, $debug = "bool", $conditionToReturnData = null)
    {
        $this->checkData($table, $columnArray, $columnData, -10);

        $columns = implode(", ", $columnArray);
        $data = array_map(function($value) {
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            } elseif (is_int($value) || $value === "NULL") {
                return $value;
            }
            return "'" . $value . "'";
        }, $columnData);
        $data = implode(", ", $data);

        $dbRequest = "INSERT INTO {$table} ({$columns}) VALUES ({$data})";

        if ($debug === "-@") {
            var_dump($dbRequest);
        }

        try {
            $result = $this->db->prepare($dbRequest);
            $result->execute();

            if($conditionToReturnData){
                if (strpos($conditionToReturnData, "MAX") !== false) {
                    return $this->selectDB($table, $conditionToReturnData);
                }
                return $this->selectDB($table, '*', $conditionToReturnData);
            }

            if ($debug === null || $debug === "-@" || $debug === "bool") {
                return true;
            }

        } catch (PDOException $e) {
            if ($debug === "-@") {
                $this->exit_with_message("PDO error: " . $e->getMessage());
            }
            if ($debug === "bool") {
                return false;
            }
            $this->exit_with_message("Something went wrong (InsertDB), you should use 'bool' debug parameter for normal use (do not overwrite the 'conditionToReturnData')");
        }

        return false;
    }

    # -------------------------------------------------------------- #

    /**
     * Insère des données dans une table de la base de données.
     *
     * @param string $table Le nom de la table dans laquelle insérer les données.
     * @param array $columnArray Un tableau contenant les noms des colonnes.
     * @param array $columnData Un tableau contenant les valeurs à insérer.
     * @param string $debug Mode de débogage. "bool" par défaut.
     * @param string|null $conditionToReturnData Condition pour retourner des données après l'insertion.
     * @return array|bool Retourne true en cas de succès, false en cas d'échec, ou un tableau de données si $conditionToReturnData est spécifié.
     */
    public function updateDB($table, $columnArray, $columnData, $condition = null, $debug = "bool")
    {
        $this->checkData($table, $columnArray, $columnData, $condition);

        if (count($columnArray) != count($columnData)) {
            $this->exit_with_message('ERROR: Columns and data must have the same length');
        }

        $updatedData = implode(", ", array_map(function($column, $data) {
            if (is_bool($data)) {
                return "{$column}=" . ($data ? 'true' : 'false');
            } elseif (is_int($data) || $data === "NULL") {
                return "{$column}={$data}";
            }
            return "{$column}='{$data}'";
        }, $columnArray, $columnData));

        $dbRequest = "UPDATE {$table} SET {$updatedData}";
        if ($condition !== -1) {
            $dbRequest .= " WHERE {$condition}";
        }

        if ($debug === "-@") {
            var_dump($dbRequest);
        }

        try {
            $result = $this->db->prepare($dbRequest);
            $result->execute();
            return true;
        } catch (PDOException $e) {
            if ($debug === "-@") {
                $this->exit_with_message("PDO Error: " . $e->getMessage());
            }
            if ($debug === "bool") {
                return false;
            }
            $this->exit_with_message("Something went wrong (UpdateDB), you should use 'bool' debug parameter for normal use");
        }

        return false;
    }

    # -------------------------------------------------------------- #

    /**
     * Supprime des données d'une table de la base de données.
     *
     * @param string $table Le nom de la table dans laquelle effectuer la suppression.
     * @param string $condition La condition WHERE pour la suppression.
     * @param bool $debug Mode de débogage. "bool" par défaut.
     * @return bool Retourne true en cas de succès, false en cas d'échec.
     */
    public function deleteDB($table, $condition, $debug = "bool")
    {
        $this->checkData($table, -10, -10, $condition);

        if (!$this->selectDB($table, "*", $condition, "bool")) {
            if ($debug === "bool") {
                return false;
            }
            $this->exit_with_message("ERROR: The requested item doesn't exist (DeleteDB)");
        }

        $dbRequest = "DELETE FROM {$table}";
        if ($condition !== -1) {
            $dbRequest .= " WHERE {$condition}";
        }

        if ($debug === "-@") {
            var_dump($dbRequest);
        }

        try {
            $result = $this->db->prepare($dbRequest);
            $result->execute();
            return true;
        } catch (PDOException $e) {
            if ($debug === "-@") {
                $this->exit_with_message("PDO Error: " . $e->getMessage());
            }
            if ($debug === "bool") {
                return false;
            }
            $this->exit_with_message("Something went wrong (DeleteDB), you should use 'bool' debug parameter for normal use");
        }
        return false;
    }


}