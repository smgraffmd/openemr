<?php
/**
 * List all patients who do not have a photo in the specified category.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// comment this out when using this script
exit;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "Usage: php <site_id> [photo_category_name]\n";
    exit;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . '/../../interface/globals.php';

$categoryName = $argv[2] ?? $GLOBALS['patient_photo_category_name'];

$sql = "SELECT pd.pid, pd.fname, pd.lname
        FROM patient_data AS pd
        WHERE NOT EXISTS (
            SELECT 1
              FROM documents AS d
              JOIN categories_to_documents AS ctd ON d.id = ctd.document_id
              JOIN categories AS c ON c.id = ctd.category_id
             WHERE d.foreign_id = pd.pid
               AND c.name = ?
               AND d.deleted = 0
        )
        ORDER BY pd.lname, pd.fname";

$result = sqlStatement($sql, [$categoryName]);

echo "Patients without photo in category \"{$categoryName}\":\n";
while ($row = sqlFetchArray($result)) {
    echo $row['pid'] . ', ' . $row['lname'] . ', ' . $row['fname'] . PHP_EOL;
}
