<?php
/**
 * ravencoin-burn-address-generator.php: This script generates Ravencoin burn addresses with a custom address prefix.
 * The symbols at the end of the burning address are made for checksum verification.
 *
 * forked from @author Daniel Gockel
 * @website https://www.10xrecovery.org/
 */

require 'vendor/autoload.php';

use StephenHill\Base58;
use Illuminate\Support\Str;

// Database connection details
define('DB_HOST', 'your_db_host');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

function fetchUserProfileId($dbConnection, $userLogin) {
    $stmt = $dbConnection->prepare("SELECT profile_id FROM db1.users WHERE user_login = ?");
    $stmt->bind_param("s", $userLogin);
    $stmt->execute();
    $stmt->bind_result($profileId);
    $stmt->fetch();
    $stmt->close();
    return $profileId;
}

function fetchTokenBurnName($dbConnection, $userLogin) {
    $stmt = $dbConnection->prepare("SELECT burn_name FROM db2.tokens WHERE user_login = ?");
    $stmt->bind_param("s", $userLogin);
    $stmt->execute();
    $stmt->bind_result($burnName);
    $stmt->fetch();
    $stmt->close();
    return $burnName;
}

function generateRandomBase58($length) {
    $base58Characters = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
    return substr(str_shuffle(str_repeat($base58Characters, $length)), 0, $length);
}

function sha256($data) {
    return hash('sha256', $data, true);
}

function generateBurnAddress($dbConnection, $userLogin) {
    $userProfileId = fetchUserProfileId($dbConnection, $userLogin);
    $tokenBurnName = fetchTokenBurnName($dbConnection, $userLogin);

    if (!$userProfileId || !$tokenBurnName) {
        die("Failed to fetch user profile ID or token burn name.");
    }

    // Construct the prefix
    $prefix = "R" . $userProfileId . $tokenBurnName;

    $base58 = new Base58();

    foreach (str_split($prefix) as $char) {
        if (strpos($base58->getAlphabet(), $char) === false) {
            die("Character '" . $char . "' is not a valid base58 character.");
        }
    }

    // Ensure the length of the full address is 34 characters
    $addressLength = strlen($prefix);
    if ($addressLength < 34) {
        $randomPart = generateRandomBase58(34 - $addressLength);
        $ravencoinAddressPrefix = $prefix . $randomPart;
    } else {
        $ravencoinAddressPrefix = substr($prefix, 0, 34);
    }

    // Decode address
    $decodedAddress = $base58->decode($ravencoinAddressPrefix);
    $decodedAddress = substr($decodedAddress, 0, -4); // cut 4 bytes for checksum at the end
    $checksum = substr(sha256(sha256($decodedAddress)), 0, 4);
    return $base58->encode($decodedAddress . $checksum);
}

$dbConnection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($dbConnection->connect_error) {
    die("Connection failed: " . $dbConnection->connect_error);
}

$userLogin = "vincent-raven";
$burnAddress = generateBurnAddress($dbConnection, $userLogin);

echo "Your Ravencoin burning address is: " . $burnAddress;

$dbConnection->close();
