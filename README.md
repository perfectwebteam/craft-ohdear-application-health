<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Oh Dear Application Health checker icon"></p>

<h1 align="center">Oh Dear Application Health checker for Craft CMS</h1>

This plugin provides an [Oh Dear](https://ohdear.app) Application Health checker for [Craft CMS](https://craftcms.com/).

## 🚦 Health Checks Overview

This plugin performs the following health checks and provides a JSON feed at `/application-health.json` for Oh Dear.

The response is cached for 5 minutes.

### ✅ Updates
Checks if updates are available for Craft CMS and installed plugins, including critical ones.

### ✅ Queue Status
Monitors the number of jobs in the queue and detects failed, reserved, or delayed jobs using configurable thresholds.

### ✅ Pending Migrations
Verifies if there are any unapplied database migrations.

### ✅ Error Logs
Counts recent errors in today’s log files (`web.log`, `queue.log`, `console.log`).

### ✅ Git Repository Status
Checks if a `.git` repository exists and whether there are uncommitted changes.

### ✅ Security Headers
Fetches site headers and verifies the presence of key security headers (e.g., CSP, HSTS, X-Frame-Options).

### ✅ Project Config Status
Confirms if the project configuration is fully synchronized.

### ✅ PHP Version
Reports the active PHP version running on the server and verifies it against a minimum required version.

### ✅ Admin Users
Lists all admin users and flags users who haven’t logged in for a configurable period.

### ✅ Disk Usage
Monitors disk space usage and flags if usage exceeds a configurable threshold.  
*(Disabled by default to avoid noise on shared servers.)*

## Requirements

This plugin requires Craft CMS 4.0.0+ or 5.0.0+.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Oh Dear Application Health”. Then click on the “Install” button in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# Go to the project directory
cd /path/to/my-project.test

# Tell Composer to load the plugin
composer require perfectwebteam/craft-ohdear-application-health

# Tell Craft to install the plugin
./craft plugin/install ohdear-application-health
```

## Setup

Once Oh Dear Application Health is installed:

1. Go to your site in **Oh Dear** and activate the **Application health** check.
2. Set the **Health Report URL** to `https://www.yourdomain.com/application-health.json`.
3. Copy the **Health Report Secret** value and set it as value for `OH_DEAR_HEALTH_REPORT_SECRET=` in your `.env` file.
4. Optionally add a `config/ohdear-application-health.php` configuration file in which you can override the default settings.

Example configuration:

```php
<?php

return [
    'checks' => [
        'addUpdateCheck' => true,
        'addQueueCheck' => true,
        'addPendingMigrationsCheck' => true,
        'addProjectConfigCheck' => true,
        'addErrorLogCheck' => true,
        'addGitChangesCheck' => true,
        'addSecurityHeadersCheck' => true,
        'addPhpVersionCheck' => true,
        'addAdminUsersCheck' => true,
        'addDiskUsageCheck' => false,
    ],
    'oldestUpdateWarningDays' => 30,
    'minimumPhpVersion' => '8.1.0',
    'requiredSecurityHeaders' => [
        'Content-Security-Policy',
        'X-Frame-Options',
        'Strict-Transport-Security',
        'X-Content-Type-Options',
        'Referrer-Policy',
        'Permissions-Policy',
    ],
    'inactiveAdminThreshold' => '-1 year',
    'gitRepoPath' => '@root',
    'queueTotalThreshold' => 10,
    'queueFailedThreshold' => 2,
    'diskUsagePath' => '/',
	'diskUsageThreshold' => 90,
];
```

Brought to you by [Perfect Web Team](https://perfectwebteam.com)