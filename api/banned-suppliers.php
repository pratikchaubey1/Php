<?php
header("Content-Type: application/json");

// ================= DB CONFIG =================
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'test_db';

$conn = null;
$use_mysqli = false;

// ================= CONNECT DB =================

// PDO MySQL
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

// MySQLi fallback
if (!$conn && class_exists('mysqli')) {
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "DB connection failed"]);
        exit;
    }
    $use_mysqli = true;
}

// ================= AUTO DELETE EXPIRED =================
if ($use_mysqli) {
    $conn->query("
        DELETE FROM banned_suppliers
        WHERE DATE_ADD(bannedDate, INTERVAL banningPeriod YEAR) < CURDATE()
    ");
} else {
    $conn->exec("
        DELETE FROM banned_suppliers
        WHERE DATE_ADD(bannedDate, INTERVAL banningPeriod YEAR) < CURDATE()
    ");
}

// ================= GET =================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    // OPEN PDF
    if (isset($_GET['id'], $_GET['pdf'])) {
        $id = intval($_GET['id']);

        if ($use_mysqli) {
            $res = $conn->query("SELECT pdfPath FROM banned_suppliers WHERE id=$id");
            $row = $res->fetch_assoc();
        } else {
            $stmt = $conn->prepare("SELECT pdfPath FROM banned_suppliers WHERE id=:id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
        }

        if (!$row || !$row['pdfPath']) {
            http_response_code(404);
            exit;
        }

        header("Content-Type: application/pdf");
        header("Content-Disposition: inline");
        readfile(__DIR__ . "/../uploads/pdfs/" . $row['pdfPath']);
        exit;
    }

    // LIST DATA
    if ($use_mysqli) {
        $res = $conn->query("SELECT * FROM banned_suppliers ORDER BY id DESC");
        $data = [];
        while ($r = $res->fetch_assoc()) $data[] = $r;
    } else {
        $stmt = $conn->query("SELECT * FROM banned_suppliers ORDER BY id DESC");
        $data = $stmt->fetchAll();
    }

    echo json_encode(["data" => $data]);
    exit;
}

// ================= POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name    = trim($_POST['supplierName']);
    $address = trim($_POST['supplierAddress']);
    $by      = trim($_POST['bannedBy']);
    $period  = intval($_POST['banningPeriod']);
    $code    = trim($_POST['fileCode'] ?? "");
    $bannedDate = date('Y-m-d');

    $pdfPath = null;
    $fileSize = null;

    // PDF UPLOAD
    if (!empty($_FILES['pdfFile']['name'])) {
        $ext = strtolower(pathinfo($_FILES['pdfFile']['name'], PATHINFO_EXTENSION));
        if ($ext !== "pdf") {
            echo json_encode(["success" => false, "message" => "Only PDF allowed"]);
            exit;
        }

        $uploadDir = __DIR__ . "/../uploads/pdfs";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $pdfPath = uniqid() . ".pdf";
        $fileSize = round($_FILES['pdfFile']['size'] / 1024);

        move_uploaded_file($_FILES['pdfFile']['tmp_name'], "$uploadDir/$pdfPath");
    }

    // INSERT
    if ($use_mysqli) {
        $stmt = $conn->prepare("
            INSERT INTO banned_suppliers
            (supplierName, supplierAddress, bannedBy, banningPeriod, bannedDate, fileCode, fileSize, pdfPath)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssissis",
            $name,
            $address,
            $by,
            $period,
            $bannedDate,
            $code,
            $fileSize,
            $pdfPath
        );
        $ok = $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO banned_suppliers
            (supplierName, supplierAddress, bannedBy, banningPeriod, bannedDate, fileCode, fileSize, pdfPath)
            VALUES (:n, :a, :b, :p, :d, :c, :s, :f)
        ");
        $ok = $stmt->execute([
            ':n' => $name,
            ':a' => $address,
            ':b' => $by,
            ':p' => $period,
            ':d' => $bannedDate,
            ':c' => $code,
            ':s' => $fileSize,
            ':f' => $pdfPath
        ]);
    }

    echo json_encode(["success" => $ok]);
}