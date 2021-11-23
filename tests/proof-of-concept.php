<?php
// NOTE : File needs to be in root folder to run properly - anyways this is a proof of concept and can be mostly ignored

// Todo: Bot could save filename sent to it to allow overlapping requests
$fileName = 'new_adhoc_dumps';
$directory = 'tmp';

$zipTempPath = $directory . '/' . $fileName . time() . mt_rand(100000, 999999);

// Unzip
$zip = new ZipArchive;
if ($zip->open($fileName . '.zip') === TRUE) {
	$zip->extractTo($zipTempPath);
	$zip->close();
} else {
	echo 'Zip opening failed, exiting' . PHP_EOL;
	exit;
}

// Empty Data
$uploadedData = [];

// Target directly file
// Todo : replace with uploaded file in bot
$filename = 'new_hex_dict.csv';
$tempPathHexDict = $zipTempPath . '/' . $filename;
// Open and Read individual CSV file
if (($handle = fopen($tempPathHexDict, 'r')) !== false) {
	// Skip header
	fgetcsv($handle, 1000);
	while (($dataValue = fgetcsv($handle, 1000)) !== false) {
		$uploadedData[] = $dataValue;
	}
}

// Build CSV for comparing
$hexDict = 'https://raw.githubusercontent.com/jmctune/dqxclarity/weblate/app/hex_dict.csv';
$existingHexValues = buildArrayFromHexDictCsv($hexDict);
$valuesNotFound = compareUploadedHexesAgainstExisting($uploadedData, $existingHexValues, "the master hex database");

// Loop through local saved values
$localHexPath = 'db';
$localHexDict = $localHexPath . '/local_hex_dict.csv';

// Creates a file if it doesn't exist
if(!file_exists($localHexDict)) {
	echo "Creating $localHexDict" . PHP_EOL;
	$dictHeader = "file,hex_string\r\n";
	file_put_contents($localHexDict, $dictHeader);
}

$localHexValues = buildArrayFromHexDictCsv($localHexDict);
$remainingNotFound = compareUploadedHexesAgainstExisting($valuesNotFound, $localHexValues, "the Discord bot local database");

// Maintain record of all CSVs + combine with the raw
if(!empty($remainingNotFound)) {
	// Add new line to CSV
	$csvHandle = fopen($localHexDict, 'ab');

	foreach($remainingNotFound as $newEntry) {
		fputcsv($csvHandle, $newEntry);

		// Copy to en and ja folders
		copy($zipTempPath . "/ja/" . $newEntry[0] . ".json", $localHexPath . "/ja/" . $newEntry[0] . ".json");
		copy($zipTempPath . "/en/" . $newEntry[0] . ".json", $localHexPath . "/en/" . $newEntry[0] . ".json");
	}


}

// Clean up uploaded zip
rrmdir($zipTempPath);

// Returns an array from a specific CSV format in which the 2nd column contains hex strings
function buildArrayFromHexDictCsv($filePath): array {
	$csvFull = array_map('str_getcsv', file($filePath));
	$csvTrim = array_map(function($v) {
		return $v[1];
	}, $csvFull);
	array_shift($csvTrim);
	return $csvTrim;
}

// Checks if we have this value or not
function compareUploadedHexesAgainstExisting($uploadedData, $existingHexValues, $type): array {
	// Loop through hex_dict to compare with zip
	$newHexFound = [];
	foreach($uploadedData as $row) {
		if(!in_array($row[1], $existingHexValues, true)) {
			$newHexFound[] = $row;
		}
	}

	echo "Found " . count($newHexFound) . " new hex" . (count($newHexFound) === 1 ? '' : 'es') . " in " . $type . PHP_EOL;
	return $newHexFound;
}

// Recursive rmdir for cleaning zip uploads
function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object !== "." && $object !== "..") {
				if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
					rrmdir($dir. DIRECTORY_SEPARATOR .$object);
				else
					unlink($dir. DIRECTORY_SEPARATOR .$object);
			}
		}
		rmdir($dir);
	}
}