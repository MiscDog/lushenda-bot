<?php

use Discord\Discord;
use Discord\Parts\Channel\Message;

require __DIR__ . '/vendor/autoload.php';
include_once(__DIR__ . '/env.php');

const LOCAL_HEX_PATH = 'db';
const LOCAL_HEX_DICT = LOCAL_HEX_PATH . '/local_hex_dict.csv';

$discord = new Discord([
	'token' => $discordToken
]);

$discord->on('message', static function (Message $message, Discord $discord) {
	$acceptedChannelIds = [
		"912506665079828491", // adhoc-uploads - DQX Tools (ENG)
		"856955528944681021" // bot-test2 - MiscDog
	];

	if(in_array($message->channel_id, $acceptedChannelIds, true) && is_array($message->attachments) && count($message->attachments) > 0) {
		try {
			// Check if uploaded a zip - only check 1st attachment
			if(validZipFile($message->attachments[0])) {
				// Store file for simplicity
				$file = $message->attachments[0];

				// Build temp file path
				$filePath = $file->url;
				$fileName = $file->id;

				// Extract Zip to temp path
				$zipTempPath = extractZip($filePath, $fileName);

				// Prepare data
				$uploadedData = prepareUserUploadedData($zipTempPath);
				$typeCount = [
					'master' => [],
					'local' => []
				];
				$newData = compareAgainstExisting($uploadedData, $typeCount);

				// Add to local database and inform user
				addToLocalDatabase($newData, $zipTempPath);
				$msg = updateUser($typeCount);
				$message->reply($msg);

				// Clean up uploaded zip
				unlink($zipTempPath . ".zip");
				recursiveRmdir($zipTempPath);

				$message->delete();
			}
		} catch(\Error $e) {
			$message->reply($e->getMessage());
		}
	}

});

/**
 * Check if this zip file is valid
 * @param $file
 * @return bool
 */
function validZipFile($file): bool {
	if($file->content_type === "application/zip") {
		return true;
	}

	throw new \Error("uploaded file does not appear to be a zip.");
}

/**
 * Extract the zip to a temp folder
 * @param $filePath
 * @param $fileName
 * @return string
 */
function extractZip($filePath, $fileName): string {
	$directory = 'tmp';
	$zipTempPath = $directory . '/' . $fileName;

	// Copy zip locally
	if (!copy($filePath, $zipTempPath . '.zip')) {
		throw new \Error('cannot copy zip for extraction.');
	}

	// Unzip
	$zip = new ZipArchive;
	if ($zip->open($zipTempPath . '.zip') === TRUE) {
		$zip->extractTo($zipTempPath);
		$zip->close();
	} else {
		throw new \Error('zip opening failed :crying_cat_face:');
	}

	return $zipTempPath;
}

/**
 * Prepare the data for comparing
 * @param $zipTempPath
 * @return array
 */
function prepareUserUploadedData($zipTempPath): array {
	// Empty Data
	$uploadedData = [];

	// Target directly file
	$expectedSubFolder = 'new_adhoc_dumps';
	$filename = 'new_hex_dict.csv';
	$tempPathHexDict = $zipTempPath . '/' . $expectedSubFolder . '/' . $filename;

	if(!is_file($tempPathHexDict)) {
		throw new \Error("couldn't find `new_hex_dict.csv` in uploaded zip. Please zip the entire `new_adhoc_dumps` folder.");
	}

	// Open and Read individual CSV file
	if (($handle = fopen($tempPathHexDict, 'r')) !== false) {
		// Skip header
		fgetcsv($handle, 1000);
		while (($dataValue = fgetcsv($handle, 1000)) !== false) {
			$uploadedData[] = $dataValue;
		}
	}

	return $uploadedData;
}

/**
 * Compare data against hex dict and local dict
 * @param $uploadedData
 * @param $typeCount
 * @return array
 */
function compareAgainstExisting($uploadedData, &$typeCount): array {
	// Build CSV for comparing
	$hexDict = 'https://raw.githubusercontent.com/jmctune/dqxclarity/weblate/app/hex_dict.csv';
	$existingHexValues = buildArrayFromHexDictCsv($hexDict);
	$valuesNotFound = compareUploadedHexesAgainstExisting($uploadedData, $existingHexValues, $typeCount['master']);
	echo "Found " . count($valuesNotFound) . " new hex" . (count($valuesNotFound) === 1 ? '' : 'es') . " in " . "the master hex database" . PHP_EOL;

	// Creates a file if it doesn't exist
	if(!file_exists(LOCAL_HEX_DICT)) {
		$dictHeader = "file,hex_string\r\n";
		file_put_contents(LOCAL_HEX_DICT, $dictHeader);
	}

	$localHexValues = buildArrayFromHexDictCsv(LOCAL_HEX_DICT);
	$valuesNotFound = compareUploadedHexesAgainstExisting($valuesNotFound, $localHexValues, $typeCount['local']);
	echo "Found " . count($valuesNotFound) . " new hex" . (count($valuesNotFound) === 1 ? '' : 'es') . " in " . "the Discord bot local database" . PHP_EOL;

	return $valuesNotFound;
}


/**
 * @param $typeCount
 * @return string
 */
function updateUser($typeCount): string {
	if($typeCount['master'] > 0 && $typeCount['local'] === $typeCount['master']) {
		return "whoa :heart_eyes:! there's " . $typeCount['master'] . " entirely new hex" . ($typeCount['master'] === 1 ? '' : 'ex') . " in that zip you uploaded. Rawr~ :white_heart:";
	} else if ($typeCount['master'] > 0) {
		return "ouh, you seem to have " . $typeCount['master'] . " hex" . ($typeCount['master'] === 1 ? '' : 'ex') . " that " . ($typeCount['master'] === 1 ? "hasn't" : "haven't") . " been added to the master hex list yet. I'm sure Serany will get right on that :blush:!";
	}

	return "oh. We already have all these hexes. Thanks anyways babe :kissing_heart: ";
}

/**
 * Checks if we have this value or not
 * @param $uploadedData
 * @param $existingHexValues
 * @param $typeCount
 * @return array
 */
function compareUploadedHexesAgainstExisting($uploadedData, $existingHexValues, &$typeCount): array {
	// Loop through hex_dict to compare with zip
	$newHexFound = [];
	foreach($uploadedData as $row) {
		if(!in_array($row[1], $existingHexValues, true)) {
			$newHexFound[] = $row;
		}
	}

	$typeCount = count($newHexFound);
	return $newHexFound;
}


/**
 * Maintain record of all CSVs + combine with the raw
 * @param $remainingNotFound
 * @param $zipTempPath
 */
function addToLocalDatabase($remainingNotFound, $zipTempPath) {
	if(!empty($remainingNotFound)) {
		// Add new line to CSV
		$csvHandle = fopen(LOCAL_HEX_DICT, 'ab');

		foreach($remainingNotFound as $newEntry) {
			fputcsv($csvHandle, $newEntry);

			// Copy to en and ja folders
			copy($zipTempPath . "/new_adhoc_dumps/ja/" . $newEntry[0] . ".json", LOCAL_HEX_PATH . "/ja/" . $newEntry[0] . ".json");
			copy($zipTempPath . "/new_adhoc_dumps/en/" . $newEntry[0] . ".json", LOCAL_HEX_PATH . "/en/" . $newEntry[0] . ".json");
		}
	}
}

/**
 * Returns an array from a specific CSV format in which the 2nd column contains hex strings
 * @param $dictPath
 * @return array
 */
function buildArrayFromHexDictCsv($dictPath): array {
	$csvFull = array_map('str_getcsv', file($dictPath));
	$csvTrim = array_map(static function($v) {
		return $v[1];
	}, $csvFull);
	array_shift($csvTrim);
	return $csvTrim;
}

/**
 * Recursive rmdir for cleaning zip uploads
 * @param $dir
 */
function recursiveRmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object !== "." && $object !== "..") {
				if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) {
					recursiveRmdir($dir. DIRECTORY_SEPARATOR .$object);
				} else {
					unlink($dir . DIRECTORY_SEPARATOR . $object);
				}
			}
		}
		rmdir($dir);
	}
}

$discord->run();