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
$h1 = trim($h1);

$h2 = hash_pbkdf2("sha256", $combo, $SUGAR, $SPICE, 32);

if ($h1 != $h2) {
  $httpHost = $_SERVER['HTTP_REFERER'];
  $hostSplit = explode("/", $httpHost);
  $changePWurl = "";
  for ($i = 0; $i < (sizeof($hostSplit) - 1); $i++) $changePWurl .= $hostSplit[$i] . "/";
  $changePWurl .= "changePW.php?id=" . $curator_id . "&pw=[your password]";
  $fMsg = "Authentication Failure!\n'$h1'\n'$h2'\nPlease check your curator id ($curator_id) and password (******)";
  $fMsg .= "<br>  To change your password, point your browser to $changePWurl";
  die($fMsg);
}

// initialize rule parameters
$rule_id = 0;
$focus = "";
$enzyme = "";
$other_residue = "";
$polymer = "";
$taxonomy = "";
$refs = "";
$comment = "";
$status = "proposed";

if (is_null($submittedData['data'])) {
	if (!is_null($submittedData['disputed_id'])) {
		echo "Disputing mapping " . $submittedData['disputed_id'];
		//   NOTE: 'curator_id' is associated with 'disputer_id'
		$query = "UPDATE enzyme_mappings SET disputer_id=?,status='disputed' WHERE instance=?"; 
		$stmt = $connection->prepare($query);
		$stmt->bind_param("si", $curator_id, $disputedID);

		$disputedID = $submittedData['disputed_id'];
		echo "\ndisputed enzyme mapping: '" . $disputedID . "'";
		echo "\ndisputer: '" . $curator_id . "'";

		if ($stmt->execute()) {
			echo "\n\nMapping " . $disputedID . " successfully disputed";
		} else {
			echo "\n\nUnable to dispute mapping";
		}
	} else if (!is_null($submittedData['withdrawn_id'])) {
		echo "Withdrawing mapping " . $submittedData['withdrawn_id'];
		// process 'withdraw assertion'
		//   NOTE: 'curator_id' must equal 'proposer_id' 
		//    only proposer can withdraw proposal 
		//    only assertions with status='proposed' can be withdrawn
		$query = "DELETE FROM enzyme_mappings WHERE instance=? AND status='proposed' AND proposer_id=?";
		$stmt = $connection->prepare($query);
		$stmt->bind_param("is", $withdrawnID, $curator_id);

		$withdrawnID = $submittedData['withdrawn_id'];
		echo "\nenzyme mapping id: '" . $withdrawnID . "'";
		echo "\nwithdrawn by: '" . $curator_id . "'";

		if (($stmt->execute()) && (mysqli_affected_rows($connection) > 0) ) {
			echo "\n\nMapping " . $withdrawnID . " successfully withdrawn";
		} else {
			echo "\n\nUnable to withdraw mapping";
		}		
	}
} else {
	$sData = $submittedData['data'];
	$geneName = $sData['gene_name'];
	$residueName = $sData['residue_name'];
	$residueID = $sData['residue_id'];
	$uniprot = $sData['uniprot'];
	$notes = $sData['notes'];

	echo "Processing new mapping of enzyme " . $geneName . " to residue " . $residueID;
	// process 'propose mapping' - data is in $submittedData['data']
	//   NOTE: 'curator_id' is associated with 'proposer_id'
	$query = "SELECT type FROM enzymes WHERE uniprot=?";
	$stmt = $connection->prepare($query);
	$stmt->bind_param("s", $uniprot);
	$stmt->execute();
	$result = $stmt->get_result();
	if ( ($result->num_rows) > 0) { 
		$row = $result->fetch_assoc();
		$type = $row['type'];
	}
	
	$query = "INSERT INTO enzyme_mappings (residue_name, residue_id, uniprot, notes, type, proposer_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
	$stmt = $connection->prepare($query);
	$stmt->bind_param("sssssss", $residueName, $residueID, $uniprot, $notes, $type, $curator_id, $status);

	
	echo "\nproposer: '" . $curator_id . "'";
	echo "\n  residue name: '" . $residueName . "'"; 
	echo "\n  residue ID: '" . $residueID . "'"; 
	echo "\n  uniprot: '" . $uniprot . "'"; 
	echo "\n  notes: '" . $notes . "'"; 
	echo "\n  type: '" . $type . "'";  
	echo "\n  status: '" . $status . "'"; 
	

	if ($stmt->execute()) {
		echo "\n\nNew record created successfully";
	} else {
		echo "\n\nUnable to create record";
	}
	
}

$connection->close();

?>
