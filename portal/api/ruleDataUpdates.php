<?php
include '../config.php';
include 'glycanCommon.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');


// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("Connection failed: " . $connection->connect_error);
}

$query = "SELECT * FROM `rule_data` WHERE `status`='proposed'";
$stmt = $connection->prepare($query);
$stmt->execute(); 
$result = $stmt->get_result();
echo "instance	rule_id	focus	agent	factor_1	factor_2	taxonomy	curator_id	refs	comment	status	administrator";
if ( ($result->num_rows) > 0) { 
	while ($row = $result->fetch_assoc()) {
		$instance = $row['instance'];
		$rule_id = $row['rule_id'];
		$focus = $row['focus'];
		$agent = $row['agent'];
		$factor_1 = $row['factor_1'];
		$factor_2 = $row['factor_2'];
		$taxonomy = $row['taxonomy'];
		$curator_id = $row['curator_id'];
		$refs = $row['refs'];
		$comment = $row['comment'];
		$status = $row['status'];
		$adminisrator = "unspecified";
		echo "\n$instance\t$rule_id\t$focus\t$agent\t$factor_1\t$factor_2\t$taxonomy\t$curator_id\t$refs\t$comment\t$status\t$adminisrator";
	}
}
?>