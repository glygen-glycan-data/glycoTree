<?php

include '../config.php';
include 'glycanCommon2.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

try {
        if (empty($_GET)) {
          $request_uri = @parse_url($_SERVER['REQUEST_URI']);
          $path = explode("/",$request_uri['path']);
          // echo json_encode($path);
          $accession = end($path);
        } else {
          $accession = $_GET['ac']; 
        }
        if ($accession===null) {
          $accessions = $_GET['accs']; 
          $accessions = explode(",",$accessions);
          $returnlist = true;
        } else {
          $accessions = [ $accession ];
          $returnlist = false;
        }

	// Create connection
	$connection = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($connection->connect_error) {
		die("<br>Connection failed: " . $connection->connect_error);
	}	

        $results = [];

        foreach ($accessions as $accession) {

	// get canonical residue info from compositions
	$comp_result = queryComposition($accession, $connection);
	// $compArray is a standard array of associative arrays, not a mysqli object,
	//    as required by the function integrateData() - below
	$compArray = $comp_result->fetch_all(MYSQLI_ASSOC);

	$integratedData = integrateData($connection, $compArray, $accession);

        array_push($results, $integratedData);
        }
	header("Content-Type: application/json");
        if (!$returnlist) {
	    echo json_encode($results[0], JSON_PRETTY_PRINT);
        } else {
	    echo json_encode($results, JSON_PRETTY_PRINT);
        }

        //echo "<pre>";
        //echo json_encode($integratedData, JSON_PRETTY_PRINT);
        //echo "</pre>";	

} catch (mysqli_sql_exception $e) { 
	echo "MySQLi Error Code: " . $e->get Code() . "<br />";
	echo "Exception Msg: " . $e->getMessage();
	exit();
}

$connection->close();

?>
