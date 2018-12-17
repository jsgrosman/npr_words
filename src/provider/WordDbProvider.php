<?php
/**
 * Created by PhpStorm.
 * User: jgrosman
 * Date: 12/13/18
 * Time: 1:22 PM
 */

namespace NPRWords\provider;


class WordDbProvider
{
    private $conn;

    public function __construct()
    {
        $this->conn = new \PDO('mysql:host=dev-jgrosman.npr.org;dbname=xxx', 'xxx', 'xxx');
    }


    public function saveWords($words, $date, $storyId, $url)
    {
        $params = [];

        $sql =
        "INSERT INTO words (word, first_mentioned, seamus_id, url, word_count) VALUES";

        foreach ($words as $word) {
            $sql .= " (?, ?, ?, ?, 0),";
            array_push($params, $word);
            array_push($params, $date);
            array_push($params, $storyId);
            array_push($params, $url);
        }


        $sql  = rtrim($sql,','); // remove comma

        $sql .= " ON DUPLICATE KEY UPDATE
           url = CASE
                WHEN first_mentioned > VALUES(first_mentioned) THEN VALUES(url)
                ELSE url
                END,
           seamus_id = CASE
                WHEN first_mentioned > VALUES(first_mentioned) THEN VALUES(seamus_id)
                ELSE seamus_id
                END,
           first_mentioned = CASE
                WHEN first_mentioned > VALUES(first_mentioned) THEN VALUES(first_mentioned)
                ELSE first_mentioned
                END,
           word_count = word_count + 1
";

        $statement = $this->bindAndQuery($sql, $params);

        $statement->execute();

        return;
    }

    public function checkStory($storyId)
    {
        $sql = "SELECT seamus_id FROM parsed_stories WHERE seamus_id = :storyId";
        $statement = $this->bindAndQuery($sql, [':storyId' => $storyId]);

        $results = $statement->fetchAll();

        return !empty($results);
    }

    public function markStory($storyId)
    {
        $sql = "INSERT INTO parsed_stories (seamus_id) VALUES(:storyId)";
        $statement = $this->bindAndQuery($sql, [':storyId' => $storyId]);

        $results = $statement->execute();

        return !empty($results);
    }



    /**
     * bindAndQuery - bind parameters and execute the query.
     *
     * if a colon is present in the first key of parameters this function assumes named parameters were used
     *
     * @param string $sql
     * @param array  $parameters - can either be an array of namedParam => paramValue values or array of paramValues
     *
     * @throws \Exception
     *
     * @return \PDOStatement
     */
    private function bindAndQuery($sql, $parameters)
    {
        $statement = $this->conn->prepare($sql);

        // One day we should add this as a debug - useful for fingers_crossed
        // error_log("SQL: $sql, params:".print_r($parameters, true));

        // we need to determine if we've been passed named parameters
        $namedParamsUsed = false;
        $paramKeys = array_keys($parameters);
        if (isset($paramKeys[0]) && is_string($paramKeys[0])) {
            $namedParamsUsed = strpos($paramKeys[0], ':') !== false ? true : false;
        }

        $p = '';
        foreach ($parameters as $paramKey => $paramValue) {
            $p .= '['.$paramKey.'] = '.$paramValue.', ';
            if (!$namedParamsUsed && is_numeric($paramKey)) {
                // bindValue expects 1 based array, PHP's assoc arrarys are 0 based
                ++$paramKey;
            }

            if (is_int($paramValue)) { // we need to do this for LIMIT & offset
                $statement->bindValue($paramKey, $paramValue, \PDO::PARAM_INT);
            } elseif (is_null($paramValue)) {
                $statement->bindValue($paramKey, null, \PDO::PARAM_NULL);
            } else { // MySQL doesn't mind if everything is passed as string and will do the Right Thing
                $statement->bindValue($paramKey, $paramValue);
            }
        }

        if ($statement->execute() === false) {
            $info = $statement->errorInfo();
            ob_start();
            $statement->debugDumpParams(); //header("Content-Type: image/png"); in here
            $stmtParams = ob_get_contents();
            ob_end_clean();
            throw new \Exception('Error executing database statement: '.$statement->errorCode().' / '.$info[2].' ( '.$stmtParams.' )');
        }

        return $statement;
    }
}
