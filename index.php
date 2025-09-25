<?php
/**
 * xsukax Shared Timers
 * Single-file PHP application with local timezone support
 * All dates/times display in user's browser timezone
 */

// Configuration
$db_file = 'timers.db';
$app_title = 'xsukax Shared Timers';

// Error reporting for development (comment out in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database functions
function initializeDatabase($db_file) {
    try {
        // Check if PDO SQLite is available
        if (!class_exists('PDO')) {
            throw new Exception("PDO extension not available");
        }
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            throw new Exception("SQLite PDO driver not available");
        }
        
        // Create database file if it doesn't exist
        if (!file_exists($db_file)) {
            touch($db_file);
        }
        
        $pdo = new PDO("sqlite:$db_file");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Create table with proper schema
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS timers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                start_timestamp INTEGER NOT NULL,
                duration_seconds INTEGER NOT NULL,
                creator_ip VARCHAR(45) NOT NULL,
                created_at INTEGER NOT NULL,
                title VARCHAR(255) DEFAULT 'Timer'
            )
        ");
        
        // Create indexes
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_creator_ip ON timers(creator_ip)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_start_timestamp ON timers(start_timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_created_at ON timers(created_at)");
        
        return $pdo;
        
    } catch (Exception $e) {
        $error_msg = "Database Error: " . $e->getMessage() . "\n\n";
        $error_msg .= "Installation Help:\n";
        $error_msg .= "Ubuntu/Debian: sudo apt-get install php-sqlite3 php-pdo-sqlite\n";
        $error_msg .= "CentOS/RHEL: sudo yum install php-pdo php-sqlite3\n";
        $error_msg .= "Then restart your web server.\n";
        die($error_msg);
    }
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

function createTimer($pdo, $days, $hours, $minutes, $seconds, $title = 'Timer') {
    $total_seconds = ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
    
    if ($total_seconds <= 0) {
        return false;
    }
    
    $now = time();
    $stmt = $pdo->prepare("
        INSERT INTO timers (start_timestamp, duration_seconds, creator_ip, title, created_at) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$now, $total_seconds, getClientIP(), $title, $now])) {
        return $pdo->lastInsertId();
    }
    
    return false;
}

function getTimer($pdo, $timer_id) {
    $stmt = $pdo->prepare("SELECT * FROM timers WHERE id = ?");
    $stmt->execute([$timer_id]);
    return $stmt->fetch();
}

function getUserTimers($pdo, $limit = 10) {
    $stmt = $pdo->prepare("
        SELECT * FROM timers 
        WHERE creator_ip = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([getClientIP(), $limit]);
    return $stmt->fetchAll();
}

// Initialize application
$db = initializeDatabase($db_file);
$message = '';
$current_timer = null;
$error = false;

// Handle timer creation
if ($_POST && isset($_POST['create_timer'])) {
    $days = max(0, intval($_POST['days'] ?? 0));
    $hours = max(0, intval($_POST['hours'] ?? 0));
    $minutes = max(0, intval($_POST['minutes'] ?? 0));
    $seconds = max(0, intval($_POST['seconds'] ?? 0));
    $title = trim($_POST['title'] ?? '');
    
    if (empty($title)) {
        $title = 'Timer';
    }
    
    $timer_id = createTimer($db, $days, $hours, $minutes, $seconds, $title);
    
    if ($timer_id) {
        header("Location: ?t=$timer_id");
        exit;
    } else {
        $message = 'Timer duration must be greater than 0 seconds.';
        $error = true;
    }
}

// Handle timer viewing
if (isset($_GET['t'])) {
    $timer_id = intval($_GET['t']);
    $current_timer = getTimer($db, $timer_id);
    
    if (!$current_timer) {
        $message = 'Timer #' . $timer_id . ' not found.';
        $error = true;
    }
}

// Get user's recent timers
$user_timers = getUserTimers($db, 15);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($app_title) ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0a0a;color:#00ff00;font-family:'Courier New',monospace;font-size:14px;line-height:1.4;min-height:100vh;}
        .container{max-width:900px;margin:0 auto;padding:20px;}
        .header{text-align:center;margin-bottom:30px;border-bottom:1px solid #333;padding-bottom:20px;}
        .header h1{color:#00ff00;font-size:28px;margin-bottom:5px;text-shadow:0 0 10px rgba(0,255,0,0.3);}
        .header p{color:#666;font-size:12px;}
        .terminal-box{background:#111;border:1px solid #333;border-radius:4px;padding:25px;margin:20px 0;box-shadow:0 0 20px rgba(0,255,0,0.1);}
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;color:#00ff00;margin-bottom:8px;font-size:12px;text-transform:uppercase;letter-spacing:1px;}
        .form-row{display:flex;gap:15px;flex-wrap:wrap;}
        .form-row .form-group{flex:1;min-width:80px;}
        input[type="number"],input[type="text"]{background:#000;border:1px solid #333;color:#00ff00;padding:12px;font-family:inherit;font-size:14px;width:100%;border-radius:3px;transition:all 0.3s;}
        input[type="number"]:focus,input[type="text"]:focus{outline:none;border-color:#00ff00;box-shadow:0 0 10px rgba(0,255,0,0.2);}
        .btn{background:#003300;border:1px solid #00ff00;color:#00ff00;padding:12px 24px;font-family:inherit;font-size:14px;cursor:pointer;margin:5px;transition:all 0.3s;border-radius:3px;text-decoration:none;display:inline-block;}
        .btn:hover{background:#00ff00;color:#000;transform:translateY(-1px);box-shadow:0 4px 15px rgba(0,255,0,0.3);}
        .btn:active{transform:translateY(0);}
        .timer-display{text-align:center;font-size:64px;color:#00ff00;margin:40px 0;font-weight:bold;letter-spacing:4px;text-shadow:0 0 20px rgba(0,255,0,0.5);font-variant-numeric:tabular-nums;}
        .timer-info{color:#888;font-size:13px;text-align:center;margin-bottom:30px;line-height:1.6;}
        .timer-info strong{color:#00ff00;}
        .timer-expired{color:#ff0000;animation:pulse 1.5s infinite;}
        @keyframes pulse{0%,100%{opacity:1;}50%{opacity:0.4;}}
        .recent-timers{margin-top:40px;}
        .recent-timers h3{color:#00ff00;margin-bottom:20px;font-size:18px;text-transform:uppercase;letter-spacing:1px;}
        .timer-item{background:#111;border:1px solid #333;padding:15px;margin-bottom:15px;display:flex;justify-content:space-between;align-items:center;border-radius:3px;transition:all 0.3s;}
        .timer-item:hover{border-color:#00ff00;box-shadow:0 0 10px rgba(0,255,0,0.1);}
        .timer-item a{color:#00ff00;text-decoration:none;font-weight:bold;}
        .timer-item a:hover{text-decoration:underline;}
        .timer-status{font-size:12px;color:#666;margin-top:5px;}
        .message{padding:15px;margin:20px 0;text-align:center;border-radius:3px;}
        .message.error{background:#330000;border:1px solid #ff0000;color:#ff0000;}
        .message.success{background:#003300;border:1px solid #00ff00;color:#00ff00;}
        .modal{display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);}
        .modal-content{background:#111;margin:10% auto;padding:30px;border:1px solid #00ff00;width:400px;border-radius:5px;text-align:center;box-shadow:0 0 30px rgba(0,255,0,0.3);}
        .modal-content h3{color:#00ff00;margin-bottom:20px;font-size:20px;}
        .modal-content p{color:#ccc;margin-bottom:25px;line-height:1.5;}
        .loading{color:#666;}
        @media (max-width:768px){.form-row{flex-direction:column;}.timer-display{font-size:48px;}.container{padding:15px;}.modal-content{width:90%;margin:20% auto;}}
        @media (max-width:480px){.timer-display{font-size:36px;}.header h1{font-size:24px;}.timer-item{flex-direction:column;align-items:flex-start;}}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= htmlspecialchars($app_title) ?></h1>
            <p>Create persistent timers with unique shareable URLs • All times displayed in your local timezone</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?= $error ? 'error' : 'success' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($current_timer): ?>
            <div class="terminal-box">
                <div class="timer-info">
                    <strong>Timer #<?= $current_timer['id'] ?></strong> - <?= htmlspecialchars($current_timer['title']) ?>
                    <br><span class="loading local-time" data-timestamp="<?= $current_timer['start_timestamp'] ?>">Started: Loading...</span>
                    <br>Duration: <span class="loading format-duration" data-seconds="<?= $current_timer['duration_seconds'] ?>">Loading...</span>
                    <br>Share this timer: <code><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></code>
                </div>
                
                <div id="timer-display" class="timer-display" 
                     data-start="<?= $current_timer['start_timestamp'] ?>" 
                     data-duration="<?= $current_timer['duration_seconds'] ?>">
                    --:--:--
                </div>
                
                <div style="text-align: center;">
                    <a href="?" class="btn">← Create New Timer</a>
                    <button class="btn" onclick="copyURL()">📋 Copy URL</button>
                    <button class="btn" onclick="toggleFullscreen()">⛶ Fullscreen</button>
                </div>
            </div>
        <?php else: ?>
            <div class="terminal-box">
                <h2 style="color: #00ff00; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 1px;">Create New Timer</h2>
                
                <form method="POST" id="timerForm">
                    <div class="form-group">
                        <label for="title">Timer Title</label>
                        <input type="text" id="title" name="title" placeholder="My Timer" maxlength="100" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="days">Days</label>
                            <input type="number" id="days" name="days" min="0" max="365" value="<?= intval($_POST['days'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label for="hours">Hours</label>
                            <input type="number" id="hours" name="hours" min="0" max="23" value="<?= intval($_POST['hours'] ?? 0) ?>">
                        </div>
                        <div class="form-group">
                            <label for="minutes">Minutes</label>
                            <input type="number" id="minutes" name="minutes" min="0" max="59" value="<?= intval($_POST['minutes'] ?? ($_POST ? 0 : 5)) ?>">
                        </div>
                        <div class="form-group">
                            <label for="seconds">Seconds</label>
                            <input type="number" id="seconds" name="seconds" min="0" max="59" value="<?= intval($_POST['seconds'] ?? 0) ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="create_timer" class="btn" style="width: 100%; margin-top: 25px; padding: 15px;">
                        🚀 Start Timer
                    </button>
                </form>
                
                <div style="margin-top: 20px; text-align: center; color: #666; font-size: 12px;">
                    Tip: Timer will start immediately and run continuously. You'll get a unique URL to share.
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($user_timers)): ?>
            <div class="recent-timers">
                <h3>Your Recent Timers</h3>
                <?php foreach ($user_timers as $timer): ?>
                    <div class="timer-item">
                        <div>
                            <a href="?t=<?= $timer['id'] ?>">
                                Timer #<?= $timer['id'] ?> - <?= htmlspecialchars($timer['title']) ?>
                            </a>
                            <div class="timer-status">
                                <span class="loading local-time" data-timestamp="<?= $timer['created_at'] ?>" data-format="short">Created: Loading...</span>
                                | Duration: <span class="loading format-duration" data-seconds="<?= $timer['duration_seconds'] ?>">Loading...</span>
                                | Status: <span class="timer-live-status" data-start="<?= $timer['start_timestamp'] ?>" data-duration="<?= $timer['duration_seconds'] ?>">Checking...</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Notification</h3>
            <p id="modal-message">Message</p>
            <button class="btn" onclick="closeModal()">Close</button>
        </div>
    </div>

    <script>
        // Timezone and formatting utilities
        function formatLocalTime(timestamp, format = 'full') {
            const date = new Date(timestamp * 1000);
            
            if (format === 'short') {
                return date.toLocaleDateString([], {
                    month: 'short', 
                    day: 'numeric', 
                    year: 'numeric'
                }) + ' ' + date.toLocaleTimeString([], {
                    hour: '2-digit', 
                    minute: '2-digit'
                });
            }
            
            return date.toLocaleDateString([], {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) + ' ' + date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        
        function formatDuration(totalSeconds) {
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            let parts = [];
            if (days > 0) parts.push(days + 'd');
            if (hours > 0) parts.push(hours + 'h');
            if (minutes > 0) parts.push(minutes + 'm');
            if (seconds > 0 && days === 0) parts.push(seconds + 's');
            
            return parts.length > 0 ? parts.join(' ') : '0s';
        }
        
        function formatCountdown(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            
            return String(h).padStart(2, '0') + ':' +
                   String(m).padStart(2, '0') + ':' +
                   String(s).padStart(2, '0');
        }
        
        function updateTimerStatus(element, startTime, duration) {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - startTime;
            const remaining = Math.max(0, duration - elapsed);
            
            if (remaining <= 0) {
                element.textContent = 'Completed';
                element.style.color = '#ff6666';
            } else {
                element.textContent = 'Running (' + formatCountdown(remaining) + ' left)';
                element.style.color = '#00ff00';
            }
        }
        
        // Main timer countdown
        function updateMainTimer() {
            const display = document.getElementById('timer-display');
            if (!display) return;
            
            const startTime = parseInt(display.dataset.start);
            const duration = parseInt(display.dataset.duration);
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - startTime;
            const remaining = Math.max(0, duration - elapsed);
            
            if (remaining <= 0) {
                display.textContent = '00:00:00';
                display.classList.add('timer-expired');
                if (!display.hasAttribute('data-notified')) {
                    showModal('🎉 Timer Completed!', 'Your timer has finished running.');
                    display.setAttribute('data-notified', 'true');
                }
                return;
            }
            
            display.textContent = formatCountdown(remaining);
        }
        
        // Initialize all time displays
        function initializeDisplays() {
            // Convert timestamps to local time
            document.querySelectorAll('.local-time').forEach(el => {
                const timestamp = parseInt(el.dataset.timestamp);
                const format = el.dataset.format || 'full';
                const prefix = el.textContent.split(':')[0] + ': ';
                el.textContent = prefix + formatLocalTime(timestamp, format);
                el.classList.remove('loading');
            });
            
            // Format durations
            document.querySelectorAll('.format-duration').forEach(el => {
                const seconds = parseInt(el.dataset.seconds);
                el.textContent = formatDuration(seconds);
                el.classList.remove('loading');
            });
            
            // Update live timer statuses
            document.querySelectorAll('.timer-live-status').forEach(el => {
                const startTime = parseInt(el.dataset.start);
                const duration = parseInt(el.dataset.duration);
                updateTimerStatus(el, startTime, duration);
            });
        }
        
        // Modal functions
        function showModal(title, message) {
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-message').textContent = message;
            document.getElementById('modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        // Utility functions
        function copyURL() {
            const url = window.location.href;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showModal('📋 URL Copied!', 'Timer URL has been copied to clipboard.');
                }).catch(() => {
                    fallbackCopy(url);
                });
            } else {
                fallbackCopy(url);
            }
        }
        
        function fallbackCopy(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            
            try {
                document.execCommand('copy');
                showModal('📋 URL Copied!', 'Timer URL has been copied to clipboard.');
            } catch (err) {
                showModal('📋 Copy Failed', 'Unable to copy URL. Please copy manually: ' + text);
            }
            
            document.body.removeChild(textArea);
        }
        
        function toggleFullscreen() {
            const timerDisplay = document.getElementById('timer-display');
            if (!timerDisplay) return;
            
            if (!document.fullscreenElement) {
                timerDisplay.requestFullscreen().catch(err => {
                    showModal('⛶ Fullscreen', 'Fullscreen not supported or blocked');
                });
            } else {
                document.exitFullscreen();
            }
        }
        
        // Form validation
        function validateForm(e) {
            const days = parseInt(document.getElementById('days').value) || 0;
            const hours = parseInt(document.getElementById('hours').value) || 0;
            const minutes = parseInt(document.getElementById('minutes').value) || 0;
            const seconds = parseInt(document.getElementById('seconds').value) || 0;
            
            const total = days + hours + minutes + seconds;
            
            if (total === 0) {
                e.preventDefault();
                showModal('❌ Invalid Duration', 'Timer duration must be greater than 0. Please enter at least 1 second.');
                return false;
            }
            
            const totalSeconds = (days * 86400) + (hours * 3600) + (minutes * 60) + seconds;
            const maxSeconds = 365 * 24 * 3600; // 1 year max
            
            if (totalSeconds > maxSeconds) {
                e.preventDefault();
                showModal('❌ Duration Too Long', 'Maximum timer duration is 365 days.');
                return false;
            }
            
            return true;
        }
        
        // Event listeners and initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all displays
            initializeDisplays();
            
            // Start main timer if present
            const mainTimer = document.getElementById('timer-display');
            if (mainTimer) {
                updateMainTimer();
                setInterval(updateMainTimer, 1000);
            }
            
            // Update live statuses every 30 seconds
            setInterval(() => {
                document.querySelectorAll('.timer-live-status').forEach(el => {
                    const startTime = parseInt(el.dataset.start);
                    const duration = parseInt(el.dataset.duration);
                    updateTimerStatus(el, startTime, duration);
                });
            }, 30000);
            
            // Form validation
            const form = document.getElementById('timerForm');
            if (form) {
                form.addEventListener('submit', validateForm);
            }
            
            // Modal close on outside click
            document.getElementById('modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                } else if (e.key === 'c' && e.ctrlKey && mainTimer) {
                    e.preventDefault();
                    copyURL();
                } else if (e.key === 'F11' && mainTimer) {
                    e.preventDefault();
                    toggleFullscreen();
                }
            });
        });
    </script>
</body>
</html>