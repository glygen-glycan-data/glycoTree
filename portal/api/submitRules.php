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
	echo "Proposing new assertion of rule " . $sData['rule_id'];
	// process 'propose assertion' - data is in $submittedData['data']
	//   NOTE: 'curator_id' is associated with 'proposer_id'
	$query = "INSERT INTO rule_data (rule_id, focus, enzyme, enzyme_id, other_residue, polymer, taxonomy, proposer_id, refs, comment, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
	$stmt = $connection->prepare($query);
	$stmt->bind_param("ississsssss", $rule_id, $focus, $enzyme, $enz_id, $other_residue, $polymer, $taxonomy, $curator_id, $refs, $comment, $status);

        $enzymes = explode(',',$sData['enzyme']);
	// echo "\nenzymes: '" . $enzymes.join(",") . "'";
        
        $taxas = explode(',',$sData['taxonomy']);
	// echo "\ntaxas: '" . $taxas.join(",") . "'";
        $residues = explode(',',$sData['other_residue']);
        for ($i = 0; $i < sizeof($enzymes); $i++) {
          for ($j = 0; $j < sizeof($residues); $j++) {
	
	echo "\nproposer: '" . $curator_id . "'";
	$rule_id = 1 * $sData['rule_id'];
	echo "\n  proposed assertion id: " . $rule_id; 
	$focus = $sData['focus'];
	echo "\n  focus: '" . $focus . "'"; 
	$enzyme = $enzymes[$i]; // $sData['enzyme'];
	echo "\n  enzyme: '" . $enzyme . "'"; 
	$other_residue = trim($residues[$j]); // $sData['other_residue'];
	echo "\n  other_residue: '" . $other_residue . "'"; 
	$polymer = $sData['polymer'];
	echo "\n  polymer: '" . $polymer . "'"; 
	$taxonomy = $taxas[$i]; // $sData['taxonomy'];
	echo "\n  taxonomy: '" . $taxonomy . "'"; 
	$refs = $sData['refs'];
	echo "\n  refs: '" . $refs . "'"; 
	$comment = $sData['comment'];
	echo "\n  comment: '" . $comment . "'"; 
	echo "\n  status: '" . $status . "'"; 

	if ($stmt->execute()) {
		echo "\n\nNew record created successfully";
	} else {
		echo "\n\nUnable to create record";
	} } }
	
}

$connection->close();

?>
