#!/usr/bin/env php
<?php
$magentoDir = '/data';
chdir($magentoDir);

$dbHost = getenv('DB_HOST') ?: 'mariadb';
$dbName = getenv('DB_NAME') ?: 'bitnami_magento';
$dbUser = getenv('DB_USER') ?: 'bn_magento';
$dbPass = getenv('DB_PASSWORD') ?: 'bitnami123';
$esHost = getenv('ES_HOST') ?: 'elasticsearch';
$esPort = getenv('ES_PORT') ?: '9200';
$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = getenv('REDIS_PORT') ?: '6379';
$redisPass = getenv('REDIS_PASSWORD') ?: 'redis123';
$baseUrl = getenv('BASE_URL') ?: 'http://localhost:8080';
$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPass = getenv('ADMIN_PASS') ?: 'Admin12345';
$adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@example.com';

// Disable classmap-authoritative mode so the Magento generation autoloader
// can create missing Proxy/Factory/Interceptor classes on-the-fly.
// The image was built with generated code; the classmap has stale entries
// for files that setup:install deletes from generated/code/.
// If env.php exists, Magento was previously installed. Clean up for fresh install.
if (file_exists('/data/app/etc/env.php')) {
    echo "Previous installation detected. Dropping database for fresh install...\n";
    try {
        $cleanPdo = new PDO("mysql:host=$dbHost;port=3306", 'root', 'root123');
        $cleanPdo->exec("DROP DATABASE IF EXISTS `$dbName`");
        $cleanPdo->exec("CREATE DATABASE `$dbName`");
        $cleanPdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'%'");
        echo "  Database dropped and recreated.\n";
    } catch (\PDOException $e) {
        echo "  Warning: Could not drop database: " . $e->getMessage() . "\n";
    }
    unlink('/data/app/etc/env.php');
}

echo "Patching Composer autoloader to disable authoritative mode...\n";
$autoloadReal = '/data/vendor/composer/autoload_real.php';
$content = file_get_contents($autoloadReal);
$content = str_replace(
    '$loader->setClassMapAuthoritative(true)',
    '$loader->setClassMapAuthoritative(false)',
    $content
);
file_put_contents($autoloadReal, $content);

echo "Waiting for database...\n";
$connected = false;
for ($i = 0; $i < 60; $i++) {
    try {
        new PDO("mysql:host=$dbHost;port=3306", $dbUser, $dbPass);
        $connected = true;
        break;
    } catch (\PDOException $e) {
        sleep(2);
    }
}
if (!$connected) {
    echo "Error: Could not connect to database\n";
    exit(1);
}
echo "Database connected!\n";

echo "Waiting for Elasticsearch...\n";
for ($i = 0; $i < 30; $i++) {
    $ch = curl_init("http://$esHost:$esPort");
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode == 200) {
        break;
    }
    sleep(2);
}

echo "Installing Magento...\n";
$cmd = sprintf(
    'php bin/magento setup:install' .
    ' --base-url=%s' .
    ' --db-host=%s' .
    ' --db-name=%s' .
    ' --db-user=%s' .
    ' --db-password=%s' .
    ' --search-engine=elasticsearch7' .
    ' --elasticsearch-host=%s' .
    ' --elasticsearch-port=%s' .
    ' --cache-backend=redis' .
    ' --cache-backend-redis-server=%s' .
    ' --cache-backend-redis-port=%s' .
    ' --cache-backend-redis-password=%s' .
    ' --session-save=redis' .
    ' --session-save-redis-host=%s' .
    ' --session-save-redis-port=%s' .
    ' --session-save-redis-password=%s' .
    ' --admin-firstname=Admin' .
    ' --admin-lastname=User' .
    ' --admin-email=%s' .
    ' --admin-user=%s' .
    ' --admin-password=%s' .
    ' --backend-frontname=admin' .
    ' --no-interaction 2>&1',
    $baseUrl,
    $dbHost, $dbName, $dbUser, $dbPass,
    $esHost, $esPort,
    $redisHost, $redisPort, $redisPass,
    $redisHost, $redisPort, $redisPass,
    $adminEmail, $adminUser, $adminPass
);

echo "Running: bin/magento setup:install\n";
passthru($cmd, $exitCode);

if ($exitCode !== 0) {
    echo "Warning: setup:install returned code $exitCode. Magento might already be installed.\n";
}

// setup:install typically clears generated/code/ via cache flush.
// Restore it from the image so subsequent commands can load proxies.
echo "Restoring generated code from image...\n";
@mkdir('/data/generated/code', 0777, true);
passthru('cp -a /var/www/html/generated/code/. /data/generated/code/ 2>&1', $rc);
echo "  Restore exit code: $rc\n";

echo "Setting developer mode...\n";
passthru('php bin/magento deploy:mode:set developer 2>&1', $exitCode);

// deploy:mode:set also clears generated/code/
echo "Restoring generated code from image...\n";
@mkdir('/data/generated/code', 0777, true);
passthru('cp -a /var/www/html/generated/code/. /data/generated/code/ 2>&1', $rc2);
echo "  Restore exit code: $rc2\n";

echo "Disabling 2FA modules...\n";
passthru('php bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth Magento_TwoFactorAuth 2>&1', $exitCode);

echo "Clearing cache...\n";
passthru('php bin/magento cache:clean 2>&1');

// Restore generated code after cache:clean (which also clears generated/code/).
echo "Restoring generated code from image...\n";
@mkdir('/data/generated/code', 0777, true);
passthru('cp -a /var/www/html/generated/code/. /data/generated/code/ 2>&1', $rc3);
echo "  Restore exit code: $rc3\n";

// Fix permissions so PHP-FPM workers (www-data) can write to pub/static/
// and generated/ for on-the-fly code generation and static content deployment.
echo "Fixing filesystem permissions for FPM workers...\n";
passthru('chown -R :www-data /data/pub/static /data/generated /data/var 2>&1', $rcPerm);
passthru('chmod -R g+w /data/pub/static /data/generated /data/var 2>&1', $rcPerm2);
echo "  Permissions fixed (exit $rcPerm / $rcPerm2).\n";

// Delete stale Interceptor files so the generation autoloader creates fresh ones.
// The image's generated interceptors have constructor signatures that don't match
// the current configuration (ES host/port, Redis settings, etc.).
echo "Removing stale Interceptor files...\n";
if (is_dir('/data/generated/code')) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('/data/generated/code', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $removed = 0;
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === 'Interceptor.php') {
            unlink($file->getRealPath());
            $removed++;
        }
    }
    echo "  Removed $removed stale Interceptor files.\n";
} else {
    echo "  /data/generated/code does not exist, skipping.\n";
}

// Remove stale classmap entries for generated/code/ files so Composer's
// autoloader doesn't try include() on non-existent files (Interceptor files
// were deleted above). Without this, the include warning -> ErrorException
// chain prevents the GenerationAutoloader from creating fresh classes.
echo "Cleaning stale classmap entries for generated/code/...\n";
$staticFile = '/data/vendor/composer/autoload_static.php';
$staticContent = file_get_contents($staticFile);
$lines = explode("\n", $staticContent);
$cleanedLines = [];
$inClassMap = false;
$classMapOpen = 0;
$removed = 0;
foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === 'public static $classMap = array (') {
        $inClassMap = true;
        $classMapOpen = 1;
        $cleanedLines[] = $line;
        continue;
    }
    if ($inClassMap) {
        // Remove lines that reference generated/code/ in the classmap value
        if (strpos($trimmed, 'generated/code') !== false) {
            $removed++;
            continue;
        }
        $cleanedLines[] = $line;
        // Count braces to detect end of classMap array
        $classMapOpen += substr_count($trimmed, '(') - substr_count($trimmed, ')');
        $classMapOpen += substr_count($trimmed, '[') - substr_count($trimmed, ']');
        if ($classMapOpen <= 0 && preg_match('/\);/', $trimmed)) {
            $inClassMap = false;
        }
    } else {
        $cleanedLines[] = $line;
    }
}
file_put_contents($staticFile, implode("\n", $cleanedLines));
echo "  Removed $removed stale classmap entries.\n";

// Apply Elasticsearch 8 compatibility patches to vendor modules.
// ES 8 disables fielddata access on _id by default. Remove stored_fields
// and docvalue_fields from the search query initQuery().
echo "Patching Elasticsearch Builder to remove stored_fields/docvalue_fields for ES 8...\n";
$builderFiles = [
    '/data/vendor/magento/module-elasticsearch/ElasticAdapter/SearchAdapter/Query/Builder.php',
    '/data/vendor/magento/module-elasticsearch/SearchAdapter/Query/Builder.php',
];
foreach ($builderFiles as $bf) {
    if (!file_exists($bf)) { continue; }
    $bc = file_get_contents($bf);
    $bc = str_replace(
        "\$searchQuery['body']['stored_fields'] = '_none_';",
        '// ES8: stored_fields removed',
        $bc
    );
    $bc = str_replace(
        "\$searchQuery['body']['docvalue_fields'] = ['_id', '_score'];",
        '// ES8: docvalue_fields removed',
        $bc
    );
    $bc = str_replace(
        "\$searchQuery['body']['docvalue_fields'] = ['_id'];",
        '// ES8: docvalue_fields removed',
        $bc
    );
    file_put_contents($bf, $bc);
}
echo "  Builder.php files patched.\n";

// Remove 'type' from search query builders (ES 8 doesn't support type in URL path)
echo "Patching search query builders to remove 'type' parameter for ES 8...\n";
$typeBuilderFiles = [
    '/data/vendor/magento/module-elasticsearch/ElasticAdapter/SearchAdapter/Query/Builder.php',
    '/data/vendor/magento/module-elasticsearch/SearchAdapter/Query/Builder.php',
];
foreach ($typeBuilderFiles as $bf) {
    if (!file_exists($bf)) { continue; }
    $bc = file_get_contents($bf);
    $bc = str_replace(
        "            'type' => \$this->clientConfig->getEntityType(),\n",
        "",
        $bc
    );
    file_put_contents($bf, $bc);
}
echo "  'type' parameter removed from query builders.\n";

// ES 8 rejects sort items with empty direction or field values.
// Skip any sort item that has no field or no direction.
echo "Patching Sort builder to skip empty sort items for ES 8...\n";
$sortFile = '/data/vendor/magento/module-elasticsearch/SearchAdapter/Query/Builder/Sort.php';
$sortContent = file_get_contents($sortFile);
$sortContent = str_replace(
    'foreach ($request->getSort() as $item) {',
    'foreach ($request->getSort() as $item) {' . "\n" . '            if (empty($item[\'field\']) || empty($item[\'direction\'])) { continue; }',
    $sortContent
);
file_put_contents($sortFile, $sortContent);
echo "  Sort.php patched.\n";

// Fix ES 8 incompatibility in the ES7 client addFieldsMapping
echo "Patching ES7 client addFieldsMapping for ES 8...\n";
$es7ClientFile = '/data/vendor/magento/module-elasticsearch-7/Model/Client/Elasticsearch.php';
$es7Content = file_get_contents($es7ClientFile);
$es7Content = str_replace(
    "            'index' => \$index,
            'type' => \$entityType,
            'include_type_name' => true,
            'body' => [
                \$entityType => [
                    'properties' => [],
                    'dynamic_templates' => \$this->dynamicTemplatesProvider->getTemplates(),
                ],
            ],",
    "            'index' => \$index,
            'body' => [
                'properties' => [],
                'dynamic_templates' => \$this->dynamicTemplatesProvider->getTemplates(),
            ],",
    $es7Content
);
$es7Content = str_replace(
    "\$params['body'][\$entityType]['properties'][\$field] = \$fieldInfo;",
    "\$params['body']['properties'][\$field] = \$fieldInfo;",
    $es7Content
);
file_put_contents($es7ClientFile, $es7Content);
echo "  ES7 client addFieldsMapping patched.\n";

// Fix bulk format to not include type/type for ES 8
echo "Patching bulk index format to remove type/type for ES 8...\n";
$adapterFile = '/data/vendor/magento/module-elasticsearch/Model/Adapter/Elasticsearch.php';
$adapterContent = file_get_contents($adapterFile);
$adapterContent = str_replace(
    "        \$bulkArray = [
            'index' => \$indexName,
            'type' => \$this->clientConfig->getEntityType(),
            'body' => [],
            'refresh' => true,
        ];",
    "        \$bulkArray = [
            'index' => \$indexName,
            'body' => [],
            'refresh' => true,
        ];",
    $adapterContent
);
$adapterContent = str_replace(
    "            } else {
                \$bulkArray['body'][] = [
                    \$action => [
                        '_id' => \$id,
                        '_type' => \$this->clientConfig->getEntityType(),
                        '_index' => \$indexName
                    ]
                ];
            }",
    "            } else {
                \$bulkArray['body'][] = [
                    \$action => [
                        '_id' => \$id,
                        '_index' => \$indexName
                    ]
                ];
            }",
    $adapterContent
);
$adapterContent = str_replace(
    "        if (\$this->helper->isClientOpenSearchV2()) {
            unset(\$bulkArray['type']);
        }",
    "",
    $adapterContent
);
file_put_contents($adapterFile, $adapterContent);
echo "  Bulk format patched.\n";

// Fix non-deterministic XSD content model in esconfig.xsd (PHP 8.1+ compatibility)
echo "Patching esconfig.xsd for PHP 8.1 XSD determinism...\n";
$xsdFile = '/data/vendor/magento/module-elasticsearch/etc/esconfig.xsd';
$xsdContent = file_get_contents($xsdFile);
$xsdContent = str_replace(
    '    <xs:choice maxOccurs="unbounded" minOccurs="1">
        <xs:element type="xs:string" name="default" minOccurs="1" maxOccurs="1" />
        <xs:any processContents="lax" minOccurs="0" maxOccurs="unbounded" />
    </xs:choice>
</xs:complexType>',
    '    <xs:sequence>
        <xs:any processContents="lax" minOccurs="0" maxOccurs="unbounded" />
    </xs:sequence>
</xs:complexType>',
    $xsdContent
);
file_put_contents($xsdFile, $xsdContent);
echo "  esconfig.xsd patched.\n";

// Disable Two-Factor Authentication modules for admin login convenience
echo "Disabling Two-Factor Authentication modules...\n";
$moduleDisableResult = shell_exec('php /data/bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth --clear-static-content 2>&1');
echo "  " . str_replace("\n", "\n  ", trim($moduleDisableResult)) . "\n";

echo "Magento setup complete!\n";
