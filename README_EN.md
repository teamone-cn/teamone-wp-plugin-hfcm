## TeamOneHFCM

TeamOneHFCM is a plugin developed by TeamOneHFCM based on the WordPress framework.

### INTRODUCE

***

TeamOneHFCM header and footer code manager can add code snippets to fixed positions or optional articles in the WordPress framework, and you can put the code in the specified position at will. Switching and changing the theme will not lose the code snippets. It has out-of-the-box, Can be turned off at any time.

For the WordPress framework, the query data volume is large, and there are many redundant data, etc., the integration increases the RDS cache link layer, which greatly reduces the pressure on the database.

### Module

- Basic module

- Data batch processing tool

- Redis cache link settings
- Log Control Settings

### BENEFITS

- Never have to worry about inadvertently breaking your site by adding code
- Avoid inadvertently placing snippets in the wrong place
- Eliminate the need for a dozen or more silly plugins just to add a small code snippet – Less plugins is always better!
- Never lose your code snippets when switching or changing themes
- Know exactly which snippets are loading on your site, where they display, and who added them
- Use shortcodes to manually place the code anywhere
- Label every snippet for easy reference
- Plugin logs which user added and last edited the snippet, and when
- This plugin integrates the setting and use of the RDS cache link layer, which greatly reduces the database pressure of the WordPress framework

### FEATURES

- Add an unlimited number of scripts and styles anywhere and on any post / page
- Manage which posts or pages the script loads
- Supports custom post types
- Supports ability to load only on a specific post or page, or latest posts
- Control exactly where scripts are loaded on the page
- Scripts can control loading on desktop or mobile, enabling or disabling one or the other

### Plugin Related Options

### Script Code Type

- HTML
- CSS
- Javascript

### Page Display Options

- Site wide on every post / page
- Specific Posts
- Specific Pages
- Specific Categories (Archive & Posts)
- Specific Post Types (Archive & Posts)
- Specific Tags (Archive & Posts)
- Home Page
- Search Page
- Archive Page
- Latest Posts
- Shortcode Only

### Category List

- Whether the script display requirements are met under the filtering and classification conditions

### Location Options

- Header
- Footer
- Before Content
- After Content

### Equipment Options

- Show on All Devices
- Only Desktop
- Only Mobile Devices

### Active State

- Active
- Inactive

### Test Case Redis Configuration

Note: The configuration file configuration data is read by default.

```
Add the following configuration to the wp-config.php file:
//team-one-hfcm redis configuration

define('HFCM_REDIS_CLIENT', 'pecl'); # Specify the client used to communicate with Redis, pecl is The PHP Extension Community Library

define('HFCM_REDIS_SCHEME', 'tcp'); # Specifies the protocol used to communicate with the Redis instance
define('HFCM_REDIS_HOST', '127.0.0.1'); # The IP or hostname of the Redis server

define('HFCM_REDIS_PORT', 'xxx'); # Redis port

define('HFCM_REDIS_DATABASE', '0'); # Accepts a numeric value for automatic selection of logical databases using the SELECT command

define('HFCM_REDIS_PASSWORD', 'xxx'); # Redis password

define('HFCM_CACHE_KEY_SALT', 'wp_'); # Set the prefix of all cache keys (used in Wordpress multisite mode)

define('HFCM_REDIS_MAXTTL', '86400');

define('HFCM_LOG', true);//team-one-hfcm Whether to open the log
```