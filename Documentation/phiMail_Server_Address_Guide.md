# phiMail Server Address: How it Works

## Overview

The phiMail Server Address is a critical configuration component in OpenEMR's Direct Messaging functionality. It defines the connection endpoint for secure healthcare messaging through the EMR Direct phiMail service, enabling HIPAA-compliant transmission of clinical documents like CCDs and CCRs.

## Configuration Location

The phiMail Server Address is configured in:
- **Path**: `Administration` > `Globals` > `Connectors` > `phiMail Server Address`
- **Configuration file**: `/library/globals.inc.php` (line ~3615)
- **Global variable**: `$GLOBALS['phimail_server_address']`

## Server Address Format

The server address follows a specific URL format that determines the connection protocol:

### Format: `scheme://hostname:port`

**Examples:**
- Production: `https://phimail.example.com:32541`
- SSL/TLS: `ssl://server.emrdirect.com:32541`
- Test (insecure): `tcp://test.server.com:32541`

### Supported Schemes

| Scheme | Protocol | Security | Usage |
|--------|----------|----------|--------|
| `https` | SSL/TLS over HTTP | Encrypted | Production (recommended) |
| `ssl` | Raw SSL/TLS | Encrypted | Production |
| `sslv3` | SSL v3 | Encrypted | Legacy |
| `tls` | TLS | Encrypted | Production |
| `tcp` | Plain TCP | Unencrypted | Testing only |
| `http` | Plain HTTP | Unencrypted | Testing only |

## Connection Processing Logic

The phiMail connection logic in `/library/direct_message_check.inc.php` processes the server address as follows:

### 1. URL Parsing
```php
$phimail_server = @parse_url($GLOBALS['phimail_server_address']);
```

### 2. Protocol Handler Selection
```php
switch ($phimail_server['scheme']) {
    case "tcp":
    case "http":
        $server = "tcp://" . $phimail_server['host'];
        $phimail_secure = false;
        break;
    case "https":
        $server = "ssl://" . $phimail_server['host'] . ':' . $phimail_server['port'];
        break;
    case "ssl":
    case "sslv3":
    case "tls":
        $server = $GLOBALS['phimail_server_address'];
        break;
}
```

### 3. Connection Establishment

**For Secure Connections (https, ssl, tls):**
- Uses `stream_socket_client()` with SSL context
- Implements certificate verification
- Supports retry logic (up to 3 attempts)
- Uses appropriate CA certificate for validation

**For Insecure Connections (tcp, http):**
- Uses `fsockopen()` directly
- No encryption or certificate validation
- Intended for testing environments only

## Certificate Handling

### Certificate Files Location: `/public/certs/phimail/`

**Production Mode:**
- Certificate: `phimail_server.pem`
- Source: Production phiMail CA certificate
- Required for SSL certificate validation

**Test Mode:**
- Certificate: `EMRDirectTestCA.pem` 
- Source: EMR Direct test environment CA
- Used when `phimail_testmode_disabled` is not set to '1'

### Certificate Validation Process

1. **Context Creation**: Creates SSL stream context
2. **Peer Verification**: Sets `verify_peer` to `true`
3. **CA File**: Sets `cafile` to appropriate certificate path
4. **Validation**: Server certificate must be signed by the specified CA

## Connection Workflow

### 1. Initial Connection
```
Client → phiMail Server: TCP/SSL connection
```

### 2. Version Exchange
```
Client → Server: "INFO VER OEMR [version] 1.3.2 [PHP_version]"
Server → Client: "OK" (if successful)
```

### 3. Authentication
```
Client → Server: "AUTH [username] [password]"
Server → Client: "OK" (if successful)
```

### 4. Message Operations
- `CHECK` - Check for incoming messages
- `TO [recipient]` - Set message recipient  
- `TEXT [length]` - Send message content
- `SEND` - Transmit the message

## Error Codes

### Connection Errors (C-series)
- **C1**: phiMail disabled in configuration
- **C2**: Invalid server URL/scheme
- **C3**: SSL context creation failed
- **C4**: Network connection failed (with system error details)
- **C5**: Version handshake failed

### Example Error Messages
```
"C4 111 (Connection refused)" - Server offline/unreachable
"C4 110 (Connection timed out)" - Network timeout
"C2" - Invalid URL format (e.g., missing scheme or port)
```

## Configuration Best Practices

### Production Environment
1. **Use HTTPS/SSL**: Always use encrypted connections
2. **Install Certificates**: Deploy proper CA certificates
3. **Verify Connectivity**: Test connection before going live
4. **Monitor Logs**: Check background service logs regularly

### Test Environment  
1. **Use Test Certificates**: Deploy EMRDirectTestCA.pem
2. **Enable Test Mode**: Ensure test mode is properly configured
3. **Sandbox Addresses**: Use only approved test Direct addresses

## Troubleshooting

### Common Issues

**"Could not connect to server C2"**
- Check server address format
- Ensure scheme (https/ssl) is specified
- Verify port number is included

**"Could not connect to server C3"**
- Certificate file missing or incorrect format
- Wrong CA certificate for environment (test vs production)
- File permissions on certificate directory

**"Could not connect to server C4"**
- Network connectivity issues
- Firewall blocking outbound connections
- Server maintenance or downtime
- DNS resolution problems

### Diagnostic Steps

1. **Check Configuration**:
   ```php
   echo $GLOBALS['phimail_server_address'];
   ```

2. **Verify Certificate**:
   ```bash
   ls -la /path/to/openemr/public/certs/phimail/
   ```

3. **Test Network Connectivity**:
   ```bash
   telnet server.hostname.com 32541
   ```

4. **Review Logs**:
   - Go to `Administration` > `Other` > `Logs`
   - Select "direct-message" events
   - Look for connection error codes

## Security Considerations

### Certificate Validation
- Always verify server certificates in production
- Use proper CA certificates for validation
- Never disable SSL verification

### Network Security
- Use encrypted connections (https/ssl/tls)
- Ensure firewall rules allow necessary outbound connections
- Monitor connection logs for suspicious activity

### Access Control
- Restrict access to certificate files
- Use strong authentication credentials
- Implement proper user permissions

## Integration Points

### Files That Use phiMail Server Address

1. **`/library/direct_message_check.inc.php`**
   - Core connection logic
   - Background message checking
   - Certificate handling

2. **`/ccr/transmitCCD.php`**
   - CCD document transmission
   - Message formatting and sending

3. **`/interface/modules/zend_modules/module/Carecoordination/src/Carecoordination/Model/EncountermanagerTable.php`**
   - Care coordination messaging
   - Document transmission interface

### Global Variables
- `$GLOBALS['phimail_server_address']` - Server URL
- `$GLOBALS['phimail_username']` - Authentication username
- `$GLOBALS['phimail_password']` - Encrypted authentication password
- `$GLOBALS['phimail_enable']` - Service enable/disable flag
- `$GLOBALS['phimail_testmode_disabled']` - Production vs test mode

## Conclusion

The phiMail Server Address is a foundational component that enables secure Direct Messaging in OpenEMR. Proper configuration requires understanding the URL format, certificate requirements, and security implications. Following the guidelines in this document ensures reliable and secure healthcare messaging functionality.

For additional support, consult the EMR Direct documentation at https://www.emrdirect.com or contact support@emrdirect.com.