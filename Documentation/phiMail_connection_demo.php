<?php

/**
 * phiMail Server Address Connection Demo
 * 
 * This demonstration script shows how OpenEMR processes phiMail server addresses
 * and establishes connections to the Direct Messaging service. This script is for
 * educational purposes only and does not attempt actual connections.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @copyright Copyright (c) 2024 OpenEMR Community
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Disable execution in web context for security
if (php_sapi_name() !== 'cli') {
    exit("This demo script can only be run from the command line.\n");
}

echo "=== phiMail Server Address Connection Demo ===\n\n";

/**
 * Demo function that mimics phiMail server address parsing and connection setup
 */
function demo_phimail_connection($server_address, $test_mode = false)
{
    echo "Server Address: $server_address\n";
    echo "Test Mode: " . ($test_mode ? "YES" : "NO") . "\n";
    
    // Parse the URL (same logic as in direct_message_check.inc.php)
    $phimail_server = @parse_url($server_address);
    
    if (!$phimail_server) {
        echo "❌ ERROR: Invalid server address format\n";
        echo "📋 Expected format: scheme://hostname:port\n\n";
        return false;
    }
    
    echo "✅ URL Parsing Successful:\n";
    echo "  - Scheme: " . ($phimail_server['scheme'] ?? 'not specified') . "\n";
    echo "  - Host: " . ($phimail_server['host'] ?? 'not specified') . "\n";
    echo "  - Port: " . ($phimail_server['port'] ?? 'not specified') . "\n";
    
    // Determine certificate file (test vs production)
    $cert_file = $test_mode 
        ? '/public/certs/phimail/EMRDirectTestCA.pem'
        : '/public/certs/phimail/phimail_server.pem';
    
    echo "🔐 Certificate: $cert_file\n";
    
    // Connection setup logic (mimics direct_message_check.inc.php)
    $phimail_secure = true;
    $server = '';
    
    $scheme = $phimail_server['scheme'] ?? '';
    switch ($scheme) {
        case "tcp":
        case "http":
            $server = "tcp://" . $phimail_server['host'];
            $phimail_secure = false;
            echo "⚠️  INSECURE CONNECTION: Using unencrypted {$scheme}\n";
            echo "⚠️  This should ONLY be used for testing!\n";
            break;
            
        case "https":
            $port = $phimail_server['port'] ?? '';
            $server = "ssl://" . $phimail_server['host'] . ':' . $port;
            echo "🔒 SECURE CONNECTION: HTTPS with SSL\n";
            break;
            
        case "ssl":
        case "sslv3":
        case "tls":
            $server = $server_address;
            echo "🔒 SECURE CONNECTION: Direct SSL/TLS\n";
            break;
            
        default:
            echo "❌ ERROR: Unsupported scheme '{$scheme}'\n";
            echo "📋 Supported schemes: https, ssl, tls, tcp (test only), http (test only)\n";
            return false;
    }
    
    echo "🌐 Connection String: $server\n";
    
    if ($phimail_secure) {
        echo "🔑 Security Features:\n";
        echo "  - SSL/TLS Encryption: ✅\n";
        echo "  - Certificate Verification: ✅\n";
        echo "  - Stream Context: Required\n";
        echo "  - Connection Method: stream_socket_client()\n";
    } else {
        echo "⚠️  Security Features:\n";
        echo "  - SSL/TLS Encryption: ❌\n";
        echo "  - Certificate Verification: ❌\n";
        echo "  - Connection Method: fsockopen()\n";
    }
    
    echo "\n";
    return true;
}

/**
 * Demo the connection workflow
 */
function demo_connection_workflow()
{
    echo "=== Connection Workflow Demo ===\n\n";
    
    $steps = [
        "1. Parse server address URL",
        "2. Determine connection protocol (SSL vs TCP)",
        "3. Select appropriate certificate file",
        "4. Create SSL context (if secure)",
        "5. Establish socket connection",
        "6. Send version handshake: 'INFO VER OEMR [version] 1.3.2 [PHP_version]'",
        "7. Expect 'OK' response",
        "8. Send authentication: 'AUTH [username] [password]'",
        "9. Expect 'OK' response",
        "10. Ready for message operations"
    ];
    
    foreach ($steps as $step) {
        echo "📋 $step\n";
    }
    echo "\n";
}

/**
 * Demo common error scenarios
 */
function demo_error_scenarios()
{
    echo "=== Common Error Scenarios ===\n\n";
    
    $errors = [
        'C1' => 'phiMail disabled in configuration',
        'C2' => 'Invalid server URL/scheme',
        'C3' => 'SSL context creation failed (certificate issues)',
        'C4' => 'Network connection failed',
        'C5' => 'Version handshake failed',
        'EC4' => 'Authentication failure',
        'EC5' => 'Message too large (>5MB)',
        'EC6' => 'Network instability during transmission'
    ];
    
    foreach ($errors as $code => $description) {
        echo "❌ $code: $description\n";
    }
    echo "\n";
}

// Run demonstrations
echo "This demo shows how OpenEMR processes phiMail server addresses.\n\n";

// Test various server address formats
$test_addresses = [
    // Production examples
    'https://phimail.emrdirect.com:32541',
    'ssl://secure.phimail.com:32541',
    'tls://tls.phimail.com:32541',
    
    // Test/development examples  
    'tcp://test.phimail.com:32541',
    'http://dev.phimail.com:32541',
    
    // Invalid examples
    'phimail.com:32541',  // Missing scheme
    'ftp://phimail.com:32541',  // Unsupported scheme
    'https://phimail.com',  // Missing port
];

foreach ($test_addresses as $address) {
    demo_phimail_connection($address, false);
    echo str_repeat("-", 60) . "\n";
}

demo_connection_workflow();
demo_error_scenarios();

echo "=== Configuration Tips ===\n\n";
echo "✅ PRODUCTION:\n";
echo "  - Use https:// or ssl:// schemes\n";
echo "  - Install proper CA certificate\n";
echo "  - Verify network connectivity\n";
echo "  - Monitor connection logs\n\n";

echo "🧪 TESTING:\n";
echo "  - Use EMRDirectTestCA.pem certificate\n";
echo "  - Test addresses provided by EMR Direct\n";
echo "  - tcp:// schemes acceptable for development\n";
echo "  - Never use unencrypted connections in production\n\n";

echo "📖 For more information, see Documentation/phiMail_Server_Address_Guide.md\n";
echo "🌐 EMR Direct: https://www.emrdirect.com\n";
echo "📧 Support: support@emrdirect.com\n\n";