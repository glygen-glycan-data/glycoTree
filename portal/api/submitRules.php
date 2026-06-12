<?php
include '../config.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');
$SUGAR = getenv('SUGAR');
$SPICE = 1 * getenv('SPICE'); 

$jsonData = $_GET['json_data'];

$submittedData = json_decode($jsonData, true);

$curator_id = $submittedData['curator'];
$curator_pw = $submittedData['curator_pw']; 
// id and pw ar both case-sensitive
$combo = "$curator_pw/$curator_id";
// Create connection
$connection = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($connection->connect_error) {
	die("<br>Connection failed: " . $connection->connect_error);
}


// authentication
$h1 = "none";
$query = "SELECT * FROM curators WHERE id=?";
$stmt = $connection->prepare($query);
$stmt->bind_param("s", $curator_id);
$stmt->execute(); 
$result = $stmt->get_result();
if ($result->num_rows == 1) { // exactly one row (index = 0) per id
	$h1 = $result->fetch_assoc()['auth'];
}

$h2 = hash_pbkdf2("sha256", $combo, $SUGAR, $SPICE, 32);

if ($h1 != $h2) {
  $httpHost = $_SERVER['HTTP_REFERER'];
  $hostSplit = explode("/", $httpHost);
  $changePWurl = "";
  for ($i = 0; $i < (sizeof($hostSplit) - 1); $i++) $changePWurl .= $hostSplit[$i] . "/";
  $changePWurl .= "changePW.php?id=" . $curator_id . "&pw=[your password]";
  $fMsg = "Authentication Failure!\n\nPlease check your curator id ($curator_id) and password (******)";
  $fMsg .= "<br>  To change your password, point your browser to $changePWurl";
  die($fMsg);
}

// initialize rule parameters
$rule_id = 0;
$focus = "";
$enzyme = "";
$enzyme_id = "";
$other_residue = "";
$polymer = "";
$taxonomy = "";
$refs = "";
$comment = "";
$status = "proposed";

if (is_null($submittedData['data'])) {
	if (!is_null($submittedData['disputed_id'])) {
		echo "Disputing assertion " . $submittedData['disputed_id'];
		// process 'dispute assertion'
		//   NOTE: 'curator_id' is associated with 'disputer_id'
		$query = "UPDATE rule_data SET disputer_id=?,status='disputed' WHERE instance=?"; 
		$stmt = $connection->prepare($query);
		$stmt->bind_param("si", $curator_id, $disputedID);

		$disputedID = $submittedData['disputed_id'];
		echo "\ndisputed assertion id: '" . $disputedID . "'";
		echo "\ndisputer: '" . $curator_id . "'";

		if ($stmt->execute()) {
			echo "\n\nAssertion " . $disputedID . " successfully disputed";
		} else {
			echo "\n\nUnable to dispute assertion";
		}
	} else if (!is_null($submittedData['withdrawn_id'])) {
		echo "Withdrawing assertion " . $submittedData['withdrawn_id'];
		// process 'withdraw assertion'
		//   NOTE: 'curator_id' must equal 'proposer_id' 
		//    only proposer can withdraw proposal 
		//    only assertions with status='proposed' can be withdrawn
		$query = "DELETE FROM rule_data WHERE instance=? AND status='proposed' AND proposer_id=?";
		$stmt = $connection->prepare($query);
		$stmt->bind_param("is", $withdrawnID, $curator_id);

		$withdrawnID = $submittedData['withdrawn_id'];
		echo "\nassertion id: '" . $withdrawnID . "'";
		echo "\nwithdrawn by: '" . $curator_id . "'";

		if (($stmt->execute()) && (mysqli_affected_rows($connection) > 0) ) {
			echo "\n\nAssertion " . $withdrawnID . " successfully withdrawn";
		} else {
			echo "\n\nUnable to withdraw assertion";
		}		
	}
} else {
	$sData = $submittedData['data'];
	echo "Proposing new assertion of rule " . $sData['rule_id']. "<br>\n";;
	// process 'propose assertion' - data is in $submittedData['data']
	//   NOTE: 'curator_id' is associated with 'proposer_id'
	
	$query = "INSERT INTO rule_data (rule_id, focus, enzyme_id, other_residue, polymer, taxonomy, proposer_id, refs, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $connection->prepare($query);
	$stmt->bind_param("isisssssss", $rule_id, $focus, $enzyme_id, $other_residue, $polymer, $taxonomy, $curator_id, $refs, $comment, $status);

	$enzymes = explode(',',$sData['enzyme']);
	$taxas = explode(',',$sData['taxonomy']);
    $residues = explode(',',$sData['other_residue']);
	for ($i = 0; $i < sizeof($enzymes); $i++) {

	$enzyme = $enzymes[$i];
	$query1 = "SELECT enzyme_id FROM enzymes WHERE uniprot=?";
	$stmt1 = $connection->prepare($query1);
	$stmt1->bind_param("s", $enzyme);
	$stmt1->execute();
	$result1 = $stmt1->get_result();
	if ( ($result1->num_rows) > 0) { 
		$row1 = $result1->fetch_assoc();
		$enzyme_id = $row1['enzyme_id'];
	}

	for ($j = 0; $j < sizeof($residues); $j++) {
	
	echo "\nproposer: '" . $curator_id . "'";
	$rule_id = 1 * $sData['rule_id'];
	echo "\n  proposed assertion id: " . $rule_id; 
	$focus = $sData['focus'];
	echo "\n  focus: '" . $focus . "'"; 
	echo "\n  enzyme_id: '" . $enzyme_id . "'"; 
	echo "\n  enzyme: '" . $enzyme . "'"; 
	$other_residue = trim($residues[$j]); // $sData['other_residue'];
	echo "\n  other_residue: '" . $other_residue . "'"; 
	$polymer = $sData['polymer'];
	echo "\n  polymer: '" . $polymer . "'"; 
	$taxonomy = $taxas[$i];
	echo "\n  taxonomy: '" . $taxonomy . "'"; 
	$refs = $sData['refs'];
	echo "\n  refs: '" . $refs . "'"; 
	$comment = $sData['comment'];
	echo "\n  comment: '" . $comment . "'"; 
	echo "\n  status: '" . $status . "'"; 

	$stmt->bind_param("isisssssss", $rule_id, $focus, $enzyme_id, $other_residue, $polymer, $taxonomy, $curator_id, $refs, $comment, $status);
	if ($stmt->execute()) {
		echo "\n\nNew record created successfully";
	} else {
		echo "\n\nUnable to create record";
	} } }
	
}

$connection->close();

?>
