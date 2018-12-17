<?php

namespace NPRWords\provider;


class CMSProvider
{
    private $conn;

    public function __construct()
    {
        $this->conn = new \PDO('mysql:host=dev-jgrosman.npr.org;dbname=xxx', 'xxx', 'xxx');
    }


    public function getStoriesWithTranscripts($year, $month)
    {


        $sql =<<<EOT
    
    SELECT thing_id, date(thing_publish_date) as thing_publish_date, thing_alt_page_url FROM thing
    JOIN resource_assign
    ON thing_id = res_assign_thing_id AND res_assign_res_type_id = :transcriptType AND res_assign_res_field_num = :fieldNum
    WHERE thing_active = :active AND thing_publish_date is NOT NULL 
    AND thing_access_level = :accessLevel AND thing_org_id = :orgId
    AND thing_type_id = :thingTypeId
    AND YEAR(thing_publish_date) = :year
    AND MONTH (thing_publish_date) = :month
    ORDER BY thing_publish_date desc
EOT;

        $statement = $this->bindAndQuery($sql, [
            ':transcriptType' => 36,
            ':active' => 1,
            ':accessLevel' => 2,
            ':fieldNum' => 0,
            ':orgId' => 1,
            ':thingTypeId' => 1,
            ':year' => $year,
            ':month' => $month,
        ]);
        return $statement->fetchAll();

    }

    public function getTranscript($storyId)
    {
        $sql =<<<EOT
        select text_data from
    thing 
    join  resource_assign
    ON thing_id = res_assign_thing_id AND res_assign_res_type_id = :transcriptType
    join resource_text
    on text_res_id = res_assign_res_id
    where thing_id = :storyId
    order by res_assign_res_field_num;
EOT;

        $statement = $this->bindAndQuery($sql, [
            ':transcriptType' => 36,
            ':storyId' => $storyId,
        ]);
        return $statement->fetchAll(\PDO::FETCH_COLUMN, 0);

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
