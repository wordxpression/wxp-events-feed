# Browser test of the settings page of WXP Events Feed (Windows + Local by Flywheel + Edge).
#
# Needs WXP Local Events (0.14+) on the same site: the site reads its own feed.
# 1. seed         temporary administrator and cookies; sharing switched on; a temporary mu-plugin allows requests to this site
# 2. settings.mjs headless Edge presses the buttons; screenshots go to the output folder
# 3. clean-up     user deleted, options and mu-plugin put back (also when a step fails)
#
#   powershell -File run-settings.ps1 [-Shots <folder>]

param(
	[string]$SiteId = 'vqf3qqQBF',
	[int]$DbPort = 10167,
	[string]$Shots = (Join-Path $env:TEMP 'wxp-ef-settings-shots')
)

$ErrorActionPreference = 'Stop'
$site    = 'C:\Users\wilko\Local Sites\restaurant-plugin\app\public'
$plugin  = Join-Path $site 'wp-content\plugins\wxp-events-feed'
$php     = "$env:APPDATA\Local\lightning-services\php-8.4.16+1\bin\win64\php.exe"
$ini     = "$env:APPDATA\Local\run\$SiteId\conf\php\php.ini"
$wp      = 'C:\Program Files (x86)\Local\resources\extraResources\bin\wp-cli\wp-cli.phar'
$mu      = Join-Path $site 'wp-content\mu-plugins\zz-wxp-ef-test-local.php'
$log     = Join-Path $env:TEMP 'wxp-ef-step.txt'
$session = Join-Path $env:TEMP 'wxp-ef-session.json'
$backup  = Join-Path $env:TEMP 'wxp-ef-backup.json'
$backupPhp = $backup -replace '\\', '/'

function Invoke-Wp([string]$File) {
	Push-Location $site
	try { cmd /c "`"$php`" -c `"$ini`" -d mysqli.default_port=$DbPort -d error_reporting=0 `"$wp`" eval-file `"$File`" > `"$log`" 2>&1" } finally { Pop-Location }
}

$seed = Join-Path $env:TEMP 'wxp-ef-seed.php'
@'
<?php
use WXP\EventsFeed\Settings\Settings;
use WXP\LocalEvents\Settings\Settings as EventSettings;
$backup = array( 'mine' => get_option( Settings::OPTION, null ), 'theirs' => get_option( EventSettings::OPTION, null ) );
file_put_contents( 'BACKUP_PATH', wp_json_encode( $backup ) );
delete_option( Settings::OPTION );
$them = is_array( $backup['theirs'] ) ? $backup['theirs'] : array();
$them['feed_enabled'] = 1;
update_option( EventSettings::OPTION, $them );
$suffix  = bin2hex( random_bytes( 4 ) );
$user_id = wp_insert_user( array( 'user_login' => 'ef-ui-' . $suffix, 'user_pass' => wp_generate_password( 24 ), 'user_email' => 'ef-ui-' . $suffix . '@example.invalid', 'role' => 'administrator' ) );
$expiry  = time() + 2 * HOUR_IN_SECONDS;
$token   = WP_Session_Tokens::get_instance( $user_id )->create( $expiry );
echo 'COOKIES:' . wp_json_encode( array(
	'home'      => home_url(),
	'auth'      => array( 'name' => 'wordpress_' . COOKIEHASH, 'value' => wp_generate_auth_cookie( $user_id, $expiry, 'auth', $token ) ),
	'logged_in' => array( 'name' => 'wordpress_logged_in_' . COOKIEHASH, 'value' => wp_generate_auth_cookie( $user_id, $expiry, 'logged_in', $token ) ),
) ) . "\n";
'@.Replace('BACKUP_PATH', $backupPhp) | Set-Content -Path $seed -Encoding ascii

$cleanup = Join-Path $env:TEMP 'wxp-ef-cleanup.php'
@'
<?php
use WXP\EventsFeed\Settings\Settings;
use WXP\LocalEvents\Settings\Settings as EventSettings;
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->users} WHERE user_login LIKE 'ef-ui-%'" ) as $id ) { wp_delete_user( (int) $id ); }
$backup = json_decode( (string) file_get_contents( 'BACKUP_PATH' ), true );
if ( is_array( $backup ) ) {
	null === $backup['mine'] ? delete_option( Settings::OPTION ) : update_option( Settings::OPTION, $backup['mine'] );
	null === $backup['theirs'] ? delete_option( EventSettings::OPTION ) : update_option( EventSettings::OPTION, $backup['theirs'] );
}
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wxp\_ef\_%' OR option_name LIKE '\_transient\_timeout\_wxp\_ef\_%'" );
echo "cleaned\n";
'@.Replace('BACKUP_PATH', $backupPhp) | Set-Content -Path $cleanup -Encoding ascii

$exit = 1
try {
	Write-Host 'Seeding'
	Invoke-Wp $seed
	$line = Get-Content $log | Where-Object { $_ -like 'COOKIES:*' } | Select-Object -First 1
	if (-not $line) { Get-Content $log; throw 'seeding failed' }
	$line.Substring(8) | Set-Content -Path $session -Encoding ascii

	New-Item -ItemType Directory -Force (Split-Path $mu) | Out-Null
	"<?php // TEMPORARY test helper, removed by run-settings.ps1.`nadd_filter( 'http_request_host_is_external', '__return_true' );" | Set-Content -Path $mu -Encoding ascii

	Write-Host 'Driving the browser'
	Push-Location $plugin
	try { node tests\Browser\settings.mjs $session $Shots; $exit = $LASTEXITCODE } finally { Pop-Location }
} finally {
	Write-Host 'Cleaning up'
	Remove-Item $mu -ErrorAction SilentlyContinue
	Invoke-Wp $cleanup
	Get-Content $log | Select-Object -Last 1
	Remove-Item $session, $backup, $seed, $cleanup -ErrorAction SilentlyContinue
}
exit $exit
