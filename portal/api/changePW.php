<?php
include '../config.php';

$servername = getenv('MYSQL_SERVER_NAME');
$password = getenv('MYSQL_PASSWORD');
$uSugar = getenv('SUGAR');
$uSpice = 1 * getenv('SPICE'); 

if ( (empty($_GET['id'])) || (empty($_GET['pw'])) ) {
  echo "<h2>Usage: changePW.php?id=[your curator id]&pw=[your curator password]</h2>"; 
  die();
}

$userID = $_GET['id']; 
$userPW = $_GET['pw'];

$combo = "$userPW/$userID";
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
$stmt->bind_param("s", $userID);
$stmt->execute(); 
$result = $stmt->get_result();
if ($result->num_rows == 1) { // exactly one row (index = 0) per id
  $row = $result->fetch_assoc();
  $h1 = $row['auth'];
}
echo "H1: '$h1'<br/>";
echo "COMBO: $combo <br/>";
echo "SUGAR: $uSugar <br/>";
echo "SPICE: $uSpice <br/>";

$h2 = hash_pbkdf2("sha256", $combo, $uSugar, $uSpice, 32);
echo "H2: '$h2'<br/>";
$b = ($h1 == $h2)?"true":"false";
echo "B: $b<br/>";

echo "You provided ['$userID'] as your user ID, along with a password [********]<br>";
echo "The user ID and password are both CASE-SENSITIVE<br>";
if ($h1 == $h2) {
  echo "This ID and password combination is currently <b>valid</b> and should provide access to glycotree curation pages<br>";
} else {
  echo "This ID and password combination will <b>NOT</b> provide access to glycotree curation pages unless you set it up this way<br>";
  echo "To set up this combination as your credentials to login as a curator, write down the ID and  password you just provided, ";
  echo "then provide the glycotree administrator with the following information:<br>";
  echo "User ID: $userID<br>";
  echo "Public Key:  $h2<br>";
}

?>
