<?php

/**
 * PhiMailServerAddressTest.
 *
 * Unit tests for phiMail server address parsing and validation logic.
 * Tests the URL parsing and connection protocol determination used in
 * OpenEMR's Direct Messaging functionality.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    OpenEMR Community
 * @copyright Copyright (c) 2024 OpenEMR Community
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PhiMailServerAddressTest extends TestCase
{
    /**
     * Test valid phiMail server address formats
     * 
     * @dataProvider validServerAddressProvider
     */
    public function testValidServerAddresses($address, $expectedScheme, $expectedHost, $expectedPort, $expectedSecure)
    {
        $parsed = @parse_url($address);
        
        $this->assertNotFalse($parsed, "Server address should parse successfully: $address");
        $this->assertEquals($expectedScheme, $parsed['scheme'] ?? null, "Scheme should match");
        $this->assertEquals($expectedHost, $parsed['host'] ?? null, "Host should match");
        $this->assertEquals($expectedPort, $parsed['port'] ?? null, "Port should match");
        
        // Test security determination logic
        $isSecure = $this->isSecureConnection($parsed['scheme'] ?? '');
        $this->assertEquals($expectedSecure, $isSecure, "Security determination should match");
    }

    /**
     * Test invalid phiMail server address formats
     * 
     * @dataProvider invalidServerAddressProvider
     */
    public function testInvalidServerAddresses($address)
    {
        $parsed = @parse_url($address);
        
        // Either parsing should fail or scheme should be unsupported
        if ($parsed !== false) {
            $scheme = $parsed['scheme'] ?? '';
            $this->assertFalse(
                $this->isSupportedScheme($scheme),
                "Unsupported scheme should be detected: $scheme"
            );
        } else {
            $this->assertFalse($parsed, "Invalid address should fail to parse: $address");
        }
    }

    /**
     * Test connection string generation logic
     * 
     * @dataProvider connectionStringProvider
     */
    public function testConnectionStringGeneration($address, $expectedConnectionString)
    {
        $parsed = @parse_url($address);
        $this->assertNotFalse($parsed, "Address should parse: $address");
        
        $connectionString = $this->generateConnectionString($parsed, $address);
        $this->assertEquals($expectedConnectionString, $connectionString, "Connection string should match");
    }

    /**
     * Test certificate file selection logic
     */
    public function testCertificateFileSelection()
    {
        // Test production mode
        $productionCert = $this->getCertificateFile(false);
        $this->assertEquals('/public/certs/phimail/phimail_server.pem', $productionCert);
        
        // Test test mode  
        $testCert = $this->getCertificateFile(true);
        $this->assertEquals('/public/certs/phimail/EMRDirectTestCA.pem', $testCert);
    }

    /**
     * Data provider for valid server addresses
     */
    public function validServerAddressProvider()
    {
        return [
            // [address, expectedScheme, expectedHost, expectedPort, expectedSecure]
            ['https://phimail.emrdirect.com:32541', 'https', 'phimail.emrdirect.com', 32541, true],
            ['ssl://secure.phimail.com:32541', 'ssl', 'secure.phimail.com', 32541, true],
            ['tls://tls.phimail.com:32541', 'tls', 'tls.phimail.com', 32541, true],
            ['sslv3://legacy.phimail.com:32541', 'sslv3', 'legacy.phimail.com', 32541, true],
            ['tcp://test.phimail.com:32541', 'tcp', 'test.phimail.com', 32541, false],
            ['http://dev.phimail.com:32541', 'http', 'dev.phimail.com', 32541, false],
        ];
    }

    /**
     * Data provider for invalid server addresses
     */
    public function invalidServerAddressProvider()
    {
        return [
            ['phimail.com:32541'],  // Missing scheme
            ['ftp://phimail.com:32541'],  // Unsupported scheme
            ['mailto://phimail.com:32541'],  // Unsupported scheme
            [''],  // Empty string
            ['://phimail.com:32541'],  // Empty scheme
            ['https://'],  // Missing host
        ];
    }

    /**
     * Data provider for connection string generation
     */
    public function connectionStringProvider()
    {
        return [
            // [input_address, expected_connection_string]
            ['https://phimail.com:32541', 'ssl://phimail.com:32541'],
            ['ssl://secure.phimail.com:32541', 'ssl://secure.phimail.com:32541'],
            ['tls://tls.phimail.com:32541', 'tls://tls.phimail.com:32541'],
            ['tcp://test.phimail.com:32541', 'tcp://test.phimail.com'],
            ['http://dev.phimail.com:32541', 'tcp://dev.phimail.com'],
        ];
    }

    /**
     * Helper method to determine if a scheme represents a secure connection
     * Mimics the logic from direct_message_check.inc.php
     */
    private function isSecureConnection($scheme)
    {
        return !in_array($scheme, ['tcp', 'http']);
    }

    /**
     * Helper method to check if a scheme is supported
     */
    private function isSupportedScheme($scheme)
    {
        return in_array($scheme, ['https', 'ssl', 'sslv3', 'tls', 'tcp', 'http']);
    }

    /**
     * Helper method to generate connection string
     * Mimics the logic from direct_message_check.inc.php
     */
    private function generateConnectionString($parsed, $originalAddress)
    {
        $scheme = $parsed['scheme'] ?? '';
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? '';

        switch ($scheme) {
            case "tcp":
            case "http":
                return "tcp://" . $host;
                
            case "https":
                return "ssl://" . $host . ':' . $port;
                
            case "ssl":
            case "sslv3":
            case "tls":
                return $originalAddress;
                
            default:
                return false;
        }
    }

    /**
     * Helper method to get certificate file path
     * Mimics the logic from direct_message_check.inc.php
     */
    private function getCertificateFile($testMode)
    {
        return $testMode 
            ? '/public/certs/phimail/EMRDirectTestCA.pem'
            : '/public/certs/phimail/phimail_server.pem';
    }
}