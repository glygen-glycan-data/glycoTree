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
	// echo  $accession;
	// Create connection
	$connection = new mysqli($servername, $username, $password, $dbname);

	// Check connection
	if ($connection->connect_error) {
		die("<br>Connection failed: " . $connection->connect_error);
	}	

	// get canonical residue info from compositions
	$comp_result = queryComposition($accession, $connection);
	// $compArray is a standard array of associative arrays, not a mysqli object,
	//    as required by the function integrateData() - below
	$compArray = $comp_result->fetch_all(MYSQLI_ASSOC);

	$integratedData = integrateData($connection, $compArray, $accession);
	echo json_encode($integratedData, JSON_PRETTY_PRINT);
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
