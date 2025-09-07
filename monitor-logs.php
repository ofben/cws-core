<?php
/**
 * Development Log Monitor
 * Monitors CWS Core logs from both Etch and Kadence sites
 * 
 * Usage: php monitor-logs.php
 * 
 * @package CWS_Core
 */

// Define your site paths
$sites = [
    'etch' => '/Users/benest/Local Sites/etchjob/logs/php/error.log',
    'kadence' => '/Users/benest/Local Sites/kadencejobs/logs/php/error.log'
];

// Function to get recent CWS Core logs
function get_cws_logs($log_file, $lines = 50) {
    if (!file_exists($log_file)) {
        return "❌ Log file not found: $log_file\n";
    }
    
    $output = shell_exec("tail -n $lines '$log_file' | grep -i 'cws core'");
    return $output ?: "ℹ️  No CWS Core logs found in last $lines lines\n";
}

// Function to get all recent logs (not just CWS Core)
function get_recent_logs($log_file, $lines = 20) {
    if (!file_exists($log_file)) {
        return "❌ Log file not found: $log_file\n";
    }
    
    $output = shell_exec("tail -n $lines '$log_file'");
    return $output ?: "ℹ️  No recent logs found\n";
}

// Function to monitor logs in real-time
function monitor_logs($log_file, $site_name) {
    if (!file_exists($log_file)) {
        echo "❌ Log file not found: $log_file\n";
        return;
    }
    
    echo "🔍 Monitoring $site_name logs (Press Ctrl+C to stop)...\n";
    echo "📁 Log file: $log_file\n";
    echo str_repeat("-", 80) . "\n";
    
    // Use tail -f to follow the log file
    $handle = popen("tail -f '$log_file' | grep -i 'cws core'", 'r');
    
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line) {
            echo "[" . date('H:i:s') . "] $site_name: " . trim($line) . "\n";
        }
    }
    
    pclose($handle);
}

// Main execution
echo "=== CWS Core Log Monitor ===\n";
echo "📅 " . date('Y-m-d H:i:s') . "\n\n";

// Check command line arguments
$args = $argv ?? [];

if (in_array('--monitor', $args) || in_array('-m', $args)) {
    // Real-time monitoring mode
    $site = $args[2] ?? 'both';
    
    if ($site === 'etch' || $site === 'both') {
        monitor_logs($sites['etch'], 'ETCH');
    }
    
    if ($site === 'kadence' || $site === 'both') {
        monitor_logs($sites['kadence'], 'KADENCE');
    }
} else {
    // Show recent logs mode
    foreach ($sites as $site_name => $log_path) {
        echo "--- 📊 $site_name Site Logs ---\n";
        echo get_cws_logs($log_path);
        echo "\n";
    }
    
    echo "💡 Usage:\n";
    echo "  php monitor-logs.php                    # Show recent logs\n";
    echo "  php monitor-logs.php --monitor etch     # Monitor Etch logs in real-time\n";
    echo "  php monitor-logs.php --monitor kadence  # Monitor Kadence logs in real-time\n";
    echo "  php monitor-logs.php --monitor both     # Monitor both sites\n";
}
?>
