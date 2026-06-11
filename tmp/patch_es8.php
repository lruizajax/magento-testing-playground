<?php
function patchFile($file, $search, $replace) {
    if (!file_exists($file)) { echo "  NOT FOUND: $file\n"; return; }
    $c = file_get_contents($file);
    if (strpos($c, $search) === false) {
        echo "  PATTERN NOT FOUND in $file\n";
        echo "  Searching: " . bin2hex(substr($search, 0, 80)) . "\n";
        // Show matching portion of file
        $short = substr($search, 0, 40);
        $pos = strpos($c, $short);
        if ($pos !== false) {
            echo "  Found near by: " . bin2hex(substr($c, $pos, 80)) . "\n";
        }
        return;
    }
    $c = str_replace($search, $replace, $c);
    file_put_contents($file, $c);
    echo "  PATCHED: $file\n";
}

$base = "/var/www/html/vendor/magento/module-elasticsearch";
$client7 = "/var/www/html/vendor/magento/module-elasticsearch-7";

echo "=== Fix Builder stored_fields (broken newline) ===\n";
$f = "$base/ElasticAdapter/SearchAdapter/Query/Builder.php";
$c = file_get_contents($f);
// Fix the broken line
$c = str_replace(
    "// ES8: stored_fields/docvalue_fields removed                'sort' =>",
    "// ES8: stored_fields/docvalue_fields removed\n                'sort' =>",
    $c
);
// Remove type
$c = str_replace(
    "            'type' => \$this->clientConfig->getEntityType(),",
    "            // ES8: type removed",
    $c
);
file_put_contents($f, $c);

echo "=== SearchAdapter Builder remove type ===\n";
$f = "$base/SearchAdapter/Query/Builder.php";
$c = file_get_contents($f);
$c = str_replace(
    "            'type' => \$this->clientConfig->getEntityType(),",
    "            // ES8: type removed",
    $c
);
file_put_contents($f, $c);

echo "=== Interval.php ===\n";
$f = "$base/ElasticAdapter/SearchAdapter/Aggregation/Interval.php";
$c = file_get_contents($f);
// Remove stored_fields from line 105
$c = str_replace(
    "['body' => ['stored_fields' => [\$this->fieldName], 'size' => \$limit]]",
    "['body' => ['size' => \$limit]]",
    $c
);
// Comment out stored_fields on line 226
$c = str_replace(
    "            'stored_fields' => [",
    "            // ES8: stored_fields removed\n            'stored_fields' => [",
    $c
);
// Remove type on line 224
$c = str_replace(
    "            'type' => \$this->clientConfig->getEntityType(),",
    "            // ES8: type removed",
    $c
);
file_put_contents($f, $c);

echo "=== DataProvider.php ===\n";
$f = "$base/SearchAdapter/Dynamic/DataProvider.php";
$c = file_get_contents($f);
$c = str_replace(
    "            'type' => \$this->clientConfig->getEntityType(),",
    "            // ES8: type removed",
    $c
);
file_put_contents($f, $c);

echo "=== Elasticsearch client - fix addFieldsMapping for ES8 ===\n";
$f = "$client7/Model/Client/Elasticsearch.php";
$c = file_get_contents($f);
// Remove type from deleteMapping (already done)
// Fix addFieldsMapping - remove include_type_name and entityType wrapper
$oldMapping = "        \$params = [
            'index' => \$index,
            // ES8: type removed
            'include_type_name' => true,
            'body' => [
                \$entityType => [
                    'properties' => [],
                    'dynamic_templates' => \$this->dynamicTemplatesProvider->getTemplates(),
                ],
            ],
        ];

        foreach (\$this->applyFieldsMappingPreprocessors(\$fields) as \$field => \$fieldInfo) {
            \$params['body'][\$entityType]['properties'][\$field] = \$fieldInfo;
        }";
$newMapping = "        \$params = [
            'index' => \$index,
            'body' => [
                'properties' => [],
                'dynamic_templates' => \$this->dynamicTemplatesProvider->getTemplates(),
            ],
        ];

        foreach (\$this->applyFieldsMappingPreprocessors(\$fields) as \$field => \$fieldInfo) {
            \$params['body']['properties'][\$field] = \$fieldInfo;
        }";
if (strpos($c, $oldMapping) !== false) {
    $c = str_replace($oldMapping, $newMapping, $c);
    echo "  Fixed addFieldsMapping\n";
} else {
    echo "  Pattern NOT found, checking content...\n";
    $pos = strpos($c, "addFieldsMapping");
    echo substr($c, $pos, 600) . "\n";
}
file_put_contents($f, $c);

echo "=== Elasticsearch Adapter - fix bulk index format for ES8 ===\n";
$f = "$base/Model/Adapter/Elasticsearch.php";
$c = file_get_contents($f);
// Remove 'type' from bulk array
$c = str_replace(
    "            'type' => \$this->clientConfig->getEntityType(),",
    "            // ES8: type removed",
    $c
);
// Remove '_type' from bulk action
$c = str_replace(
    "                        '_type' => \$this->clientConfig->getEntityType(),",
    "                        // ES8: _type removed",
    $c
);
file_put_contents($f, $c);
echo "  Patched bulk index format\n";

echo "=== All syntax checks ===\n";
$files = [
    "$base/ElasticAdapter/SearchAdapter/Query/Builder.php",
    "$base/SearchAdapter/Query/Builder.php",
    "$base/ElasticAdapter/SearchAdapter/Aggregation/Interval.php",
    "$base/SearchAdapter/Dynamic/DataProvider.php",
    "$base/Model/DataProvider/Base/Suggestions.php",
    "$client7/Model/Client/Elasticsearch.php",
    "$base/SearchAdapter/Query/Builder/Sort.php",
];
foreach ($files as $f) {
    $out = shell_exec("php -l " . escapeshellarg($f) . " 2>/dev/null");
    echo basename($f) . ": " . trim($out) . "\n";
}

echo "\n=== Grep for remaining type patterns ===\n";
foreach ($files as $f) {
    $result = shell_exec("grep -n \"'type' =>\" " . escapeshellarg($f) . " 2>/dev/null | head -3");
    if ($result) echo basename($f) . ":\n$result\n";
}

echo "DONE\n";
