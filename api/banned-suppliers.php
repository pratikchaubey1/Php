<?php
header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ================= DB CONFIG =================
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'test_db';

$conn = null;
$use_mysqli = false;

ini_set('html_errors', 0);

// ================= CONNECT =================
try {
    $conn = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    $conn = null;
}

if (!$conn && class_exists('mysqli')) {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        echo json_encode(["success" => false, "message" => "DB Connection Failed: " . $conn->connect_error]);
        exit;
    }
    $use_mysqli = true;
}

if (!$conn) {
    echo json_encode(["success" => false, "message" => "Database connection failed (PDO and MySQLi both failed). Check credentials."]);
    exit;
}

// ================= AUTO DELETE =================
try {
    // Auto-delete expired records.
    // Logic: If period is a number AND (BannedDate + Period) < Today -> Delete.
    // 'Until further orders' will NOT be deleted.
    $sqlAuto = "DELETE FROM banned_suppliers 
                WHERE banningPeriod REGEXP '^[0-9]+$' 
                AND DATE_ADD(bannedDate, INTERVAL banningPeriod YEAR) < CURDATE()";

    $use_mysqli ? $conn->query($sqlAuto) : $conn->exec($sqlAuto);
} catch (Exception $e) {
    // Ignore auto-delete errors
}

// ================= GET =================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // ---- OPEN PDF ----
    if (isset($_GET['id'], $_GET['pdf'])) {

        $id = intval($_GET['id']);

        if ($use_mysqli) {
            $res = $conn->query("SELECT pdfPath FROM banned_suppliers WHERE id=$id");
            $row = $res->fetch_assoc();
        } else {
            $stmt = $conn->prepare("SELECT pdfPath FROM banned_suppliers WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
        }

        if (!$row || empty($row['pdfPath'])) {
            http_response_code(404);
            exit;
        }

        $file = __DIR__ . "/../uploads/pdfs/" . $row['pdfPath'];
        if (!file_exists($file)) {
            http_response_code(404);
            exit;
        }

        header("Content-Type: application/pdf");
        header("Content-Length: " . filesize($file));
        header("Content-Disposition: inline; filename=\"" . basename($file) . "\"");
        header("Accept-Ranges: bytes");
        readfile($file);
        exit;
    }

    // ---- LIST ----
    header("Content-Type: application/json");

    if ($use_mysqli) {
        $res = $conn->query("SELECT * FROM banned_suppliers ORDER BY bannedDate ASC");
        $data = [];
        while ($r = $res->fetch_assoc()) $data[] = $r;
    } else {
        $stmt = $conn->query("SELECT * FROM banned_suppliers ORDER BY bannedDate ASC");
        $data = $stmt->fetchAll();
    }

    echo json_encode(["data" => $data]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header("Content-Type: application/json");

    // Check for POST limit exceeded
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max = ini_get('post_max_size');
        echo json_encode(["success" => false, "message" => "Upload failed: POST size exceeded (Limit: $max)."]);
        exit;
    }

    $name    = trim($_POST['supplierName'] ?? '');
    $address = trim($_POST['supplierAddress'] ?? '');
    $by      = trim($_POST['bannedBy'] ?? '');
    // Allow string input for "Until further orders"
    $period  = trim($_POST['banningPeriod'] ?? ''); 
    $code    = trim($_POST['fileCode'] ?? "");
    $date    = !empty($_POST['bannedDate']) ? $_POST['bannedDate'] : date('Y-m-d');

    $pdfPath  = null;
    $fileSize = 0.0;

    
    if (isset($_FILES['pdfFile']) && !empty($_FILES['pdfFile']['name'])) {

        // PHP upload error check
        if ($_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
            $msg = "Upload error: ";
            switch ($_FILES['pdfFile']['error']) {
                case UPLOAD_ERR_INI_SIZE:   $msg .= "File exceeds upload_max_filesize"; break;
                case UPLOAD_ERR_FORM_SIZE:  $msg .= "File exceeds MAX_FILE_SIZE HTML directive"; break;
                case UPLOAD_ERR_PARTIAL:    $msg .= "File was only partially uploaded"; break;
                case UPLOAD_ERR_NO_FILE:    $msg .= "No file was uploaded"; break;
                case UPLOAD_ERR_NO_TMP_DIR: $msg .= "Missing a temporary folder"; break;
                case UPLOAD_ERR_CANT_WRITE: $msg .= "Failed to write file to disk"; break;
                case UPLOAD_ERR_EXTENSION:  $msg .= "File upload stopped by extension"; break;
                default:                    $msg .= "Unknown error code " . $_FILES['pdfFile']['error'];
            }
            echo json_encode([
                "success" => false,
                "message" => $msg
            ]);
            exit;
        }

        // Extension check (old logic preserved)
        if (strtolower(pathinfo($_FILES['pdfFile']['name'], PATHINFO_EXTENSION)) !== "pdf") {
            echo json_encode(["success" => false, "message" => "Only PDF allowed"]);
            exit;
        }



        $dir = __DIR__ . "/../uploads/pdfs/";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdfPath  = uniqid("pdf_") . ".pdf";
        $fileSize = round($_FILES['pdfFile']['size'] / 1024, 2);

        if (!move_uploaded_file($_FILES['pdfFile']['tmp_name'], $dir . $pdfPath)) {
            echo json_encode(["success" => false, "message" => "File move failed"]);
            exit;
        }
    }

    
    try {
        if ($use_mysqli) {
            $stmt = $conn->prepare("
                INSERT INTO banned_suppliers
                (supplierName, supplierAddress, bannedBy, banningPeriod, bannedDate, fileCode, fileSize, pdfPath)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . ($conn->error ?? "Unknown error"));
            }
            // Changed banningPeriod param type from i (int) to s (string) -> "ssssssds"
            $stmt->bind_param(
                "ssssssds",
                $name, $address, $by, $period, $date, $code, $fileSize, $pdfPath
            );
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . ($stmt->error ?? "Unknown error"));
            }
            $ok = true;
        } else {
            $stmt = $conn->prepare("
                INSERT INTO banned_suppliers
                (supplierName, supplierAddress, bannedBy, banningPeriod, bannedDate, fileCode, fileSize, pdfPath)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ok = $stmt->execute([
                $name, $address, $by, $period, $date, $code, $fileSize, $pdfPath
            ]);
        }
    } catch (Exception $e) {
        // Return JSON error instead of crashing
        echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
        exit;
    }

    echo json_encode(["success" => $ok]);
    exit;
}
