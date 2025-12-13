<?php
/**
 * PairDrop Rooms - The GitHub fork with additional features
 * <https://github.com/devtimi/PairDrop-Rooms>
 *
 * Copyright (c) 2025, PairDrop Rooms <https://pairdrop.org>
 * Copyright (c) 2025, Tim Parnell <https://timi.me>
 * 
 * # SETUP
 *   1. Upload this file to any folder, for example /drop/index.php
 *   2. Open https://yourdomain.com/drop/
 * 
 * # LICENSE
 * <https://github.com/devtimi/PairDrop-Rooms/raw/refs/heads/main/LICENSE>
 */

// ═══════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════
define('BASE_DIR', __DIR__ . '/rooms/'); // auto-creates if missing
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // defined in bytes

// Set to 0 to disable auto-delete function
// 168 hours = 1 week
define('AUTO_DELETE_HOURS', 168);

define('ALLOW_CREATE_ROOMS', true);
define('MIN_ROOM_LENGTH', 4);
define('MAX_ROOM_LENGTH', 32);
// ═══════════════════════════════════════════════════════════

// Error reporting for debugging (disable in production)
// error_reporting(E_ALL); ini_set('display_errors', 1);

// Theme is automatically detected by CSS

// Light Mode (Ahrefs style)
$lightThemeVars = [
    'primary' => '#0069B5',
    'primary-hover' => '#005291',
    'cta-color' => '#FF6347',
    'cta-hover' => '#E55B40',
    'danger' => '#ef4444',
    'bg' => '#f8f9fa',
    'card' => '#ffffff',
    'border' => '#e0e0e0',
    'text-color' => '#1c1c1c',
    'sub-text' => '#6c757d',
    'modal-bg-shade' => '#f5f5f5', // Used for modal accents
];

// Dark Mode
$darkThemeVars = [
    'primary' => '#0096FF', 
    'primary-hover' => '#007ACC',
    'cta-color' => '#FF7F50', 
    'cta-hover' => '#E56D45',
    'danger' => '#ff5555',
    'bg' => '#1c1c1c',
    'card' => '#252525',
    'border' => '#3a3a3a',
    'text-color' => '#f0f0f0',
    'sub-text' => '#b0b0b0',
    'modal-bg-shade' => '#2a2a2a', // Used for modal accents
];

// Prepare the CSS strings, we need both so that Light / Dark
// can be determined by the browser. It's 2025.
$cssLightVars = '';
foreach ($lightThemeVars as $name => $value) {
    $cssLightVars .= "--$name:$value;";
}
$cssDarkVars = '';
foreach ($darkThemeVars as $name => $value) {
    $cssDarkVars .= "--$name:$value;";
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create base directory with protection
if (!is_dir(BASE_DIR)) {
    @mkdir(BASE_DIR, 0755, true);
    @file_put_contents(BASE_DIR . 'index.html', '');
}

// Get room from URL parameter first, then session
$room = null;
if (!empty($_GET['room'])) {
    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['room']);
} elseif (!empty($_SESSION['pairdrop_room'])) {
    $room = $_SESSION['pairdrop_room'];
}

// Validate room
if ($room && (strlen($room) < MIN_ROOM_LENGTH || strlen($room) > MAX_ROOM_LENGTH)) {
    $room = null;
}

// Leave room
if (isset($_GET['leave'])) {
    unset($_SESSION['pairdrop_room']);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// API: Join/Create room (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_room'])) {
    header('Content-Type: application/json');
    
    $newRoom = trim($_POST['join_room']);
    $newRoom = preg_replace('/[^a-zA-Z0-9_-]/', '', $newRoom);
    
    if (strlen($newRoom) < MIN_ROOM_LENGTH) {
        echo json_encode(['error' => 'Room code too short (min ' . MIN_ROOM_LENGTH . ' characters)']);
        exit;
    }
    if (strlen($newRoom) > MAX_ROOM_LENGTH) {
        echo json_encode(['error' => 'Room code too long (max ' . MAX_ROOM_LENGTH . ' characters)']);
        exit;
    }
    
    // Get room directory
    $newRoomDir = BASE_DIR . $newRoom . '/';
    
    // Check if directory exists
    if (!is_dir($newRoomDir)) {
    	if (!ALLOW_CREATE_ROOMS) {
    		// Creation not allowed here
    		echo json_encode(['error' => 'Room code invalid.']);
        	exit;
    	}
    	
    	// Creation allowed, but room is empty
        @mkdir($newRoomDir, 0755, true);
        
    }
    
    // Save to session only after room is validated/created
    $_SESSION['pairdrop_room'] = $newRoom;
    
    echo json_encode(['success' => true, 'room' => $newRoom]);
    exit;
    
}

// Set room directory if valid room
$roomDir = null;
if ($room) {
    $_SESSION['pairdrop_room'] = $room;
    $roomDir = BASE_DIR . $room . '/';
    if (!is_dir($roomDir)) {
        if (ALLOW_CREATE_ROOMS) {
            @mkdir($roomDir, 0755, true);
        } else {
            // Room doesn't exist and creation not allowed
            $room = null;
            $roomDir = null;
        }
    }
}

// Auto-delete old files
if (AUTO_DELETE_HOURS > 0 && is_dir(BASE_DIR)) {
    $expiry = time() - (AUTO_DELETE_HOURS * 3600);
    foreach (glob(BASE_DIR . '*/') as $dir) {
        foreach (glob($dir . '*') as $f) {
            if (is_file($f) && filemtime($f) < $expiry) @unlink($f);
        }
        if (ALLOW_CREATE_ROOMS && is_dir($dir) && count(glob($dir . '*')) === 0) @rmdir($dir);
    }
}

// Helper functions
function inRoom() {
    global $room, $roomDir;
    return $room && $roomDir;
}

function requireRoom() {
    if (!inRoom()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Invalid room']);
        exit;
    }
}

// API: List files
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api']) && $_GET['api'] === 'list') {
    requireRoom();
    header('Content-Type: application/json');
    
    $files = [];
    if (is_dir($roomDir)) {
        foreach (glob($roomDir . '*') as $file) {
            if (is_file($file) && basename($file)[0] !== '.') {
                $name = basename($file);
                $files[] = [
                    'id' => md5($name),
                    'name' => preg_replace('/^\d+_/', '', $name),
                    'realname' => $name,
                    'size' => filesize($file),
                    'time' => filemtime($file)
                ];
            }
        }
    }
    usort($files, fn($a, $b) => $b['time'] - $a['time']);
    echo json_encode($files);
    exit;
}

// API: Upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    requireRoom();
    header('Content-Type: application/json');
    
    // Ensure room directory exists
    if (!is_dir($roomDir)) {
        if (!@mkdir($roomDir, 0755, true)) {
            echo json_encode(['error' => 'Could not create room folder. Check permissions.']);
            exit;
        }
    }
    
    // Check if directory is writable
    if (!is_writable($roomDir)) {
        echo json_encode(['error' => 'Room folder is not writable.']);
        exit;
    }
    
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            1 => 'File too large (server limit)',
            2 => 'File too large',
            3 => 'Partial upload',
            4 => 'No file selected',
            6 => 'Missing temp folder',
            7 => 'Failed to write to disk'
        ];
        echo json_encode(['error' => $errors[$file['error']] ?? 'Upload error ' . $file['error']]);
        exit;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        echo json_encode(['error' => 'File too large (max ' . round(MAX_FILE_SIZE/1024/1024) . 'MB)']);
        exit;
    }
    
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $safeName = preg_replace('/_+/', '_', $safeName);
    $safeName = substr($safeName, 0, 200);
    $dest = $roomDir . time() . '_' . $safeName;
    
    // Double-check directory exists right before move
    if (!is_dir($roomDir)) {
        @mkdir($roomDir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        @chmod($dest, 0644);
        echo json_encode(['success' => true, 'name' => basename($dest)]);
    } else {
        // Debug info
        $debug = [
            'error' => 'Could not save file.',
            'dir_exists' => is_dir($roomDir),
            'dir_writable' => is_writable($roomDir),
            'dest' => $dest
        ];
        echo json_encode($debug);
    }
    exit;
}

// API: Download file
if (isset($_GET['dl']) && inRoom()) {
    $filename = basename($_GET['dl']);
    $filepath = $roomDir . $filename;
    
    if (file_exists($filepath) && is_file($filepath)) {
        $displayName = preg_replace('/^\d+_/', '', $filename);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $displayName . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache');
        readfile($filepath);
        exit;
    }
    http_response_code(404);
    exit('File not found');
}

// API: Delete file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    requireRoom();
    header('Content-Type: application/json');
    
    $filename = basename($_POST['delete']);
    $filepath = $roomDir . $filename;
    
    if (file_exists($filepath) && @unlink($filepath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Could not delete file']);
    }
    exit;
}

// Generate share URL
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$scriptPath = strtok($_SERVER['REQUEST_URI'], '?');
$shareUrl = $room ? $baseUrl . $scriptPath . '?room=' . urlencode($room) : '';

$inRoom = inRoom();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $inRoom ? "Room: $room" : 'PairDrop' ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📤</text></svg>">
    <style>
        /* Default light mode */
        :root {
          <?= $cssLightVars ?>
        }
        
        /* Dark mode overrides */
        @media (prefers-color-scheme: dark) {
          :root {
            <?= $cssDarkVars ?>
          }
        }

        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:system-ui,-apple-system,sans-serif;background:var(--bg);min-height:100vh;color:var(--text-color)}
        .container{max-width:580px;margin:0 auto;padding:24px 16px}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--border)}
        .logo{font-size:1.4em;font-weight:600;display:flex;align-items:center;gap:8px;color:var(--primary)}
        .room-badge{font-size:.65em;background:var(--primary);color:#fff;padding:4px 10px;border-radius:20px;font-weight:500}
        .btn-ghost{color:var(--sub-text);background:none;border:1px solid transparent;font-size:.85em;padding:8px 12px;border-radius:6px;cursor:pointer;transition:.2s;text-decoration:none}
        .btn-ghost:hover{background:var(--border);color:var(--text-color);border-color:var(--border)}
        
        /* Box di Join/Input principale (Stile Card con Ombra) */
        .join-box{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:16px;
            padding:48px 32px;
            max-width:400px;
            margin:60px auto;
            text-align:center;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); /* Ombra leggera */
        }
        .join-box h2{font-size:1.5em;margin-bottom:8px}
        .join-box .subtitle{color:var(--sub-text);margin-bottom:32px;font-size:.95em}
        .input{
            width:100%;
            padding:18px 16px; /* Padding maggiore */
            border:2px solid var(--border);
            border-radius:10px;
            background:var(--card);
            color:var(--text-color);
            font-size:1.1em;
            margin-bottom:12px;
            text-align:center;
            letter-spacing:1px;
            transition: border-color .2s;
        }
        .input:focus{outline:none;border-color:var(--primary)}
        .input::placeholder{color:var(--sub-text);letter-spacing:normal}
        
        /* Bottone CTA principale (Arancione/Rosso) */
        .btn-primary{
            width:100%;
            padding:16px; 
            border:none;
            border-radius:10px;
            background:var(--cta-color); 
            color:#fff;
            font-size:1em;
            font-weight:600;
            cursor:pointer;
            transition:.2s;
        }
        .btn-primary:hover{background:var(--cta-hover)}
        .btn-primary:disabled{opacity:.5;cursor:not-allowed}
        .error{color:var(--danger);margin-top:12px;font-size:.9em;min-height:20px}
        .hint{color:var(--sub-text);font-size:.8em;margin-top:16px}
        
        /* Elementi in Room */
        .share-box{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:20px;box-shadow: 0 1px 3px rgba(0,0,0,0.05)}
        .share-label{font-size:.75em;color:var(--sub-text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
        .share-url{display:flex;gap:8px}
        .share-url input{flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text-color);font-size:.85em;font-family:monospace}
        .share-url input:focus{outline:none}
        .btn-copy{padding:10px 16px;border:none;border-radius:8px;background:var(--primary);color:#fff;font-size:.85em;cursor:pointer;transition:.2s;white-space:nowrap}
        .btn-copy:hover{background:var(--primary-hover)}
        .btn-copy.copied{background:#22c55e}
        
        .drop-zone{border:2px dashed var(--border);border-radius:16px;padding:44px 20px;text-align:center;cursor:pointer;transition:.2s;background:var(--card);margin-bottom:24px}
        .drop-zone:hover,.drop-zone.dragover{border-color:var(--primary);background:rgba(0,105,181,0.05)}
        .drop-zone svg{width:44px;height:44px;margin-bottom:12px;color:var(--primary)}
        .drop-zone p{color:var(--sub-text);font-size:.95em}
        .drop-zone .size-hint{font-size:.8em;margin-top:6px;color:var(--sub-text)}
        
        .progress{display:none;margin-bottom:20px;background:var(--card);border:1px solid var(--border);border-radius:12px;padding:16px;box-shadow: 0 1px 3px rgba(0,0,0,0.05)}
        .progress-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden}
        .progress-fill{height:100%;background:var(--primary);width:0%;transition:width .2s}
        .progress-text{font-size:.85em;color:var(--sub-text);margin-top:10px}
        
        .section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .section-title{font-size:.8em;color:var(--sub-text);text-transform:uppercase;letter-spacing:.5px}
        .file-count{font-size:.8em;color:var(--sub-text)}
        .file-list{list-style:none}
        
        /* Elementi File List */
        .file-item{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;transition:.15s;box-shadow: 0 1px 2px rgba(0,0,0,0.04)}
        .file-item:hover{border-color:var(--primary)}
        .file-icon{font-size:1.6em;flex-shrink:0}
        .file-info{flex:1;min-width:0}
        .file-name{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.9em}
        .file-meta{font-size:.75em;color:var(--sub-text);margin-top:3px}
        .file-actions{display:flex;gap:6px}
        .btn{padding:8px 14px;border:none;border-radius:8px;cursor:pointer;font-size:.8em;font-weight:500;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
        .btn-dl{background:var(--primary);color:#fff}
        .btn-dl:hover{background:var(--primary-hover)}
        .btn-del{background:rgba(239,68,68,0.15);color:#f87171}
        .btn-del:hover{background:var(--danger);color:#fff}
        
        .empty{text-align:center;color:var(--sub-text);padding:48px 20px;background:var(--card);border:1px solid var(--border);border-radius:12px}
        .empty-icon{font-size:2.5em;margin-bottom:12px;opacity:.5}
        .info-bar{text-align:center;color:var(--sub-text);font-size:.75em;margin-top:24px}
        input[type="file"]{display:none}
        @media(max-width:480px){.file-item{flex-wrap:wrap}.file-actions{width:100%;margin-top:10px}.btn{flex:1;justify-content:center}.share-url{flex-direction:column}}
        
        
        /* Footer e Modal (Adattati al tema chiaro) */
        .footer-terms { text-align: center; margin-top: 30px; font-size: 0.75em; color: var(--sub-text); }
        .footer-terms a { color: var(--sub-text); text-decoration: none; border-bottom: 1px dotted var(--sub-text); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); }
        .modal-content { background-color: var(--card); margin: 5% auto; padding: 25px; border: 1px solid var(--border); width: 90%; max-width: 800px; border-radius: 12px; color: var(--text-color); font-size: 0.9em; line-height: 1.6; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .close { color: var(--sub-text); font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: var(--text-color); }
        .modal-body > div:first-of-type {
            background: var(--modal-bg-shade); /* Usa la variabile per la sfumatura */
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid var(--primary);
            color: var(--text-color);
        }
        .modal-body hr { border: 0; border-top: 1px solid var(--border); margin: 20px 0; }
        .modal-body > div:last-of-type {
            border: 1px solid var(--border);
            background: var(--bg); /* Usa bg per un contrasto sottile */
            color: var(--sub-text);
        }
        .modal-body > div > p { margin-bottom: 0.5em; }
        
        
    </style>
</head>
<body>
<?php if (!$inRoom): ?>
    <div class="container">
        <div class="join-box">
            <h2>📤 PairDrop</h2>
            <p class="subtitle">Enter a room code to share files securely</p>
            <form id="joinForm" method="post" action="">
                <input type="text" class="input" id="roomCode" name="room_input" 
                       placeholder="Enter room code" 
                       minlength="<?= MIN_ROOM_LENGTH ?>" 
                       maxlength="<?= MAX_ROOM_LENGTH ?>" 
                       pattern="[a-zA-Z0-9_-]+" 
                       autocomplete="off" 
                       autofocus
                       required>
                <button type="submit" class="btn-primary" id="joinBtn">Enter Room</button>
            </form>
            <p class="error" id="joinError"></p>
            <p class="hint">
				<?php if (ALLOW_CREATE_ROOMS) echo "Create any code you like, or enter an existing one.<br>"; ?>
				Only people with the code can access the room.
            </p>
        </div>
    </div>
    <script>
        document.getElementById('joinForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('joinBtn');
            const err = document.getElementById('joinError');
            const codeInput = document.getElementById('roomCode');
            const code = codeInput.value.trim();
            
            if (!code) {
                err.textContent = 'Please enter a room code';
                return;
            }
            
            if (code.length < <?= MIN_ROOM_LENGTH ?>) {
                err.textContent = 'Room code too short (min <?= MIN_ROOM_LENGTH ?> characters)';
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Joining...';
            err.textContent = '';
            
            try {
                const formData = new FormData();
                formData.append('join_room', code);
                
                const res = await fetch(window.location.href.split('?')[0], {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success) {
                    // Redirect to room
                    window.location.href = '?room=' + encodeURIComponent(data.room);
                } else {
                    err.textContent = data.error || 'Unknown error';
                    btn.disabled = false;
                    btn.textContent = 'Enter Room';
                }
            } catch (error) {
                err.textContent = 'Connection error. Please try again.';
                btn.disabled = false;
                btn.textContent = 'Enter Room';
                console.error('Error:', error);
            }
        });
    </script>
<?php else: ?>
    <div class="container">
        <div class="header">
            <div class="logo">📤 PairDrop <span class="room-badge"><?= htmlspecialchars($room) ?></span></div>
            <a href="?leave" class="btn-ghost">Leave Room</a>
        </div>
        
        <div class="share-box">
            <div class="share-label">Share this link</div>
            <div class="share-url">
                <input type="text" id="shareUrl" value="<?= htmlspecialchars($shareUrl) ?>" readonly onclick="this.select()">
                <button class="btn-copy" id="copyBtn" onclick="copyUrl()">Copy</button>
            </div>
        </div>
        
        <div class="drop-zone" id="dropZone">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            <p>Drop files here or click to select</p>
            <p class="size-hint">Max <?= round(MAX_FILE_SIZE/1024/1024) ?>MB per file</p>
            <input type="file" id="fileInput" multiple>
        </div>
        
        <div class="progress" id="progress">
            <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
            <p class="progress-text" id="progressText">Uploading...</p>
        </div>
        
        <div class="section-header">
            <span class="section-title">Files in this room</span>
            <span class="file-count" id="fileCount"></span>
        </div>
        <ul class="file-list" id="fileList"><li class="empty"><div class="empty-icon">📂</div>Loading...</li></ul>
        
        <?php if (AUTO_DELETE_HOURS > 0): ?>
			<div class="info-bar">
				Files are automatically deleted after <?= AUTO_DELETE_HOURS ?> hours
			</div>
        <?php endif; ?>
    </div>

    <script>
        const $ = id => document.getElementById(id);
        const dropZone = $('dropZone'), fileInput = $('fileInput'), fileList = $('fileList');
        const progress = $('progress'), progressFill = $('progressFill'), progressText = $('progressText');

        function copyUrl() {
            const input = $('shareUrl');
            const btn = $('copyBtn');
            input.select();
            input.setSelectionRange(0, 99999);
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value);
            } else {
                document.execCommand('copy');
            }
            
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            setTimeout(() => { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
        }

        dropZone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', e => uploadFiles(e.target.files));
        
        ['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropZone.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(ev => dropZone.addEventListener(ev, e => { e.preventDefault(); e.stopPropagation(); dropZone.classList.remove('dragover'); }));
        dropZone.addEventListener('drop', e => uploadFiles(e.dataTransfer.files));

        async function uploadFiles(files) {
            for (const file of files) {
                progress.style.display = 'block';
                progressFill.style.width = '0%';
                progressText.textContent = 'Uploading: ' + file.name;

                const fd = new FormData();
                fd.append('file', file);

                try {
                    const xhr = new XMLHttpRequest();
                    xhr.upload.onprogress = e => {
                        if (e.lengthComputable) {
                            const pct = (e.loaded / e.total) * 100;
                            progressFill.style.width = pct + '%';
                            progressText.textContent = file.name + ' — ' + Math.round(pct) + '%';
                        }
                    };
                    xhr.onload = () => { 
                        progress.style.display = 'none';
                        if (xhr.status === 401) {
                            window.location.href = '?leave';
                            return;
                        } else if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    loadFiles(); 
                                    fileInput.value = '';
                                } else {
                                    alert(response.error || 'Upload failed');
                                }
                            } catch(e) {
                                alert('Invalid response from server');
                            }
                        } else {
                            alert('Upload failed (HTTP ' + xhr.status + ')');
                        }
                    };
                    xhr.onerror = () => { 
                        alert('Upload failed'); 
                        progress.style.display = 'none'; 
                    };
                    xhr.open('POST', window.location.href);
                    xhr.send(fd);
                } catch(e) {
                    alert('Upload error');
                    progress.style.display = 'none';
                }
            }
        }

        async function loadFiles() {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('api', 'list');
                
                const res = await fetch(url.toString());
                if (res.status === 401) {
                    window.location.href = '?leave';
                    return;
                }
                
                const files = await res.json();
                
                $('fileCount').textContent = files.length ? files.length + ' file' + (files.length > 1 ? 's' : '') : '';
                
                if (!files.length) {
                    fileList.innerHTML = '<li class="empty"><div class="empty-icon">📂</div>No files yet. Drop some files to share!</li>';
                    return;
                }
                
                fileList.innerHTML = files.map(f => 
                    '<li class="file-item">' +
                        '<span class="file-icon">' + getIcon(f.name) + '</span>' +
                        '<div class="file-info">' +
                            '<div class="file-name">' + esc(f.name) + '</div>' +
                            '<div class="file-meta">' + fmtSize(f.size) + ' · ' + fmtTime(f.time) + '</div>' +
                        '</div>' +
                        '<div class="file-actions">' +
                            '<a href="?room=<?= urlencode($room) ?>&dl=' + encodeURIComponent(f.realname) + '" class="btn btn-dl">↓ Download</a>' +
                            '<button class="btn btn-del" onclick="del(\'' + esc(f.realname) + '\')">✕</button>' +
                        '</div>' +
                    '</li>'
                ).join('');
            } catch(e) { 
                console.error('Load error:', e);
                fileList.innerHTML = '<li class="empty"><div class="empty-icon">⚠️</div>Error loading files</li>'; 
            }
        }

        async function del(name) {
            if (!confirm('Delete this file?')) return;
            
            const fd = new FormData();
            fd.append('delete', name);
            
            await fetch(window.location.href, { 
                method: 'POST', 
                body: fd
            });
            loadFiles();
        }

        function getIcon(n) {
            const ext = (n.split('.').pop() || '').toLowerCase();
            const m = {pdf:'📄',doc:'📝',docx:'📝',xls:'📊',xlsx:'📊',txt:'📃',csv:'📊',
                jpg:'🖼️',jpeg:'🖼️',png:'🖼️',gif:'🖼️',webp:'🖼️',svg:'🖼️',
                mp4:'🎬',mov:'🎬',avi:'🎬',mkv:'🎬',webm:'🎬',
                mp3:'🎵',wav:'🎵',flac:'🎵',
                zip:'📦',rar:'📦','7z':'📦',tar:'📦',gz:'📦'};
            return m[ext] || '📎';
        }
        
        function fmtSize(b) { 
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
            return (b/1048576).toFixed(1) + ' MB';
        }
        
        function fmtTime(t) { 
            const d = new Date(t * 1000);
            const diff = (Date.now() - d) / 1000;
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff/60) + 'm ago';
            if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
            return d.toLocaleDateString('en-US', {month:'short', day:'numeric'});
        }
        
        function esc(s) { 
            const div = document.createElement('div');
            div.textContent = s;
            return div.innerHTML;
        }

        // Load files on start
        loadFiles();
        // Auto-refresh every 8 seconds
        setInterval(loadFiles, 8000);
    </script>
<?php endif; ?>


<div class="footer-terms">
    Powered by <a href="https://github.com/devtimi/PairDrop-Rooms" target="_blank">PairDrop Rooms</a>. By using this website, you agree to the <a href="#" id="openTerms">Terms of Service</a>.
</div>

<div id="termsModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
        <h2>Terms of Service</h2>
        <span class="close">&times;</span>
    </div>
    <div class="modal-body">
        <div style="background: var(--modal-bg-shade); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 3px solid var(--primary); color: var(--text-color);">
            <p><strong>1. Temporary Service:</strong> Files are automatically deleted after <?= AUTO_DELETE_HOURS ?> hours.</p>
            <p><strong>2. Security:</strong> You are responsible for using a strong Room Code.</p>
            <p><strong>3. Liability:</strong> Use at your own risk. We are not responsible for data loss or leaks.</p>
            <p><strong>4. Content:</strong> Illegal, malicious, or copyrighted content is strictly prohibited.</p>
            <p><strong>5. Cookies:</strong> This site does not use cookies involved in GDPR / Cookie Law.</p>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border); margin: 20px 0;">

        <h3>Legal Agreements</h3>
        <div style="height: 250px; overflow-y: auto; padding-right: 10px; border: 1px solid var(--border); padding: 10px; border-radius: 6px; background: var(--bg); font-size: 0.85em; color: var(--sub-text);">
            <p><strong>1. Nature of Service:</strong> You acknowledge that this Service is designed exclusively for the temporary transfer of files. It is not a cloud storage or backup service. Files are automatically deleted. We guarantee no long-term retention.</p>
            <p><strong>2. User Content:</strong> You strictly agree NOT to upload content that infringes on copyright, contains malware, is illegal, or promotes illegal acts. We reserve the right to remove any content without notice.</p>
            <p><strong>3. Room Security:</strong> Access is controlled via "Room Codes" chosen by the User. Anyone with the code can access the files. It is your sole responsibility to choose a complex code. The Operator is not responsible for data breaches resulting from weak or shared codes.</p>
            <p><strong>4. Indemnification:</strong> You agree to indemnify and hold the Operator harmless from any liabilities, losses, damages, or costs (including legal fees) arising from your Content, your use of the Service, or any violation of these terms.</p>
            <p><strong>5. Limitation of Liability:</strong> In no event will the Operator be liable for any indirect, incidental, or consequential damages (including lost profits or data loss). The Service is provided "AS IS" without any warranties.</p>
            <p><strong>6. Acceptance:</strong> By accessing and using the Website, you acknowledge that you have read and agree to be bound by this Agreement.</p>
            <p><strong>7. Privacy & Cookies:</strong> This service uses a single technical session cookie solely to maintain your connection to the room. No personal data, IP addresses, or tracking cookies are stored for analytics or advertising purposes. This cookie is strictly necessary for the service to function.</p>
        </div>
    </div>
  </div>
</div>

<script>
    const modal = document.getElementById("termsModal");
    const btn = document.getElementById("openTerms");
    const span = document.getElementsByClassName("close")[0];

    btn.onclick = function(e) { e.preventDefault(); modal.style.display = "block"; }
    span.onclick = function() { modal.style.display = "none"; }
    window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }
</script>

</body>
</html>