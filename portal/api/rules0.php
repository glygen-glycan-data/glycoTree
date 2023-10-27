<?php
include '../config.php';
include 'glycanCommon2.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');

$focus = $_GET['focus'];

// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("<br>Connection failed: " . $connection->connect_error);
}

$finalResult = [];
$finalResult['residue_id'] = $focus;

$query = "SELECT * FROM canonical_residues WHERE residue_id=?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $focus);
$stmt->execute(); 
$result = $stmt->get_result();
if ( ($result->num_rows) > 0) {
	$row = $result->fetch_assoc();
	$anomer = str_replace(array("a","b"), array("&alpha;","&beta;"), $row['anomer']);
	$finalResult['structure'] = $anomer . "-" . $row['absolute'] . "-" . $row['form_name'];
	$finalResult['snfg_name'] = $row['name'];
}

$finalResult['rule_instances'] = [];
$query = "SELECT rule_data.*,rules.* FROM rule_data JOIN rules on (rule_data.rule_id=rules.rule_id) WHERE rule_data.focus=?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $focus);
$stmt->execute(); 
$result = $stmt->get_result();
if ( ($result->num_rows) > 0) {
	while ($row = $result->fetch_assoc()) {
		$enzyme = $row['enzyme'];;
		$other_residue = $row['other_residue'];;
		$polymer = $row['polymer'];;
		$taxonomy = $row['taxonomy'];;
		$logic = $row['logic'];

		$keys = array("[focus]", "[enzyme]", "[other_residue]", "[polymer]", "[taxonomy]");
		$vals = array($focus, $enzyme, $other_residue, $polymer, $taxonomy);
		$inference = str_replace($keys,$vals,$logic);
		$row['inference'] = $inference;
		array_push($finalResult['rule_instances'],$row);
	}
} else {
	$message = "No rules have been applied to " . $focus;
	$finalResult['message'] = $message;
}

$finalResult['rules'] = [];

$query = "SELECT * FROM rules";
$stmt = $connection->prepare($query);
$stmt->execute(); 
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
	array_push($finalResult['rules'],$row);
}

header("Content-Type: application/json");
echo json_encode($finalResult, JSON_PRETTY_PRINT);

?>
