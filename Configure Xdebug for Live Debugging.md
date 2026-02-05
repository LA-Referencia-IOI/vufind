# Implementation Plan - Configure Xdebug for Live Debugging

This plan outlines the steps to install and configure Xdebug for the VuFind project running on Apache2, enabling live debugging in VS Code.

## User Review Required

> [!IMPORTANT]
> This plan requires installing a new package (`php-xdebug`) and modifying system configuration files (`php.ini`). You will need to approve the installation command.

## Proposed Changes

### System Configuration

#### [MODIFY] [php.ini](file:///etc/php/8.4/apache2/php.ini)
Add the following Xdebug configuration to the end of the file:
```ini
[xdebug]
zend_extension=xdebug.so
xdebug.mode=debug
xdebug.client_port=9003
xdebug.start_with_request=yes
xdebug.log=/tmp/xdebug.log
```

### IDE Configuration

#### [NEW] [launch.json](file:///home/jesielviana/Dev/ioi/vufind/.vscode/launch.json)
Create a VS Code launch configuration to listen for Xdebug connections.
```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/home/jesielviana/Dev/ioi/vufind": "${workspaceRoot}"
            }
        }
    ]
}
```

## Verification Plan

### Automated Tests
- Run `php -m | grep xdebug` to verify the module is loaded (CLI).
- Check `phpinfo()` or similar output from Apache to verify Xdebug is active for the web server.

### Manual Verification
1. Install `php-xdebug` using `sudo apt install php-xdebug`.
2. Apply the `php.ini` changes.
3. Restart Apache: `sudo systemctl restart apache2`.
4. Start the "Listen for Xdebug" configuration in VS Code.
5. Set a breakpoint in [public/index.php](file:///home/jesielviana/Dev/ioi/vufind/public/index.php).
6. Refresh `localhost/vufind` in the browser and verify the breakpoint is hit.
