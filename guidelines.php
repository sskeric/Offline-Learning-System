<?php
session_start();

$config = [
    "host" => "localhost",
    "user" => "YOUR_DB_USER",
    "pass" => "YOUR_DB_PASSWORD",
    "name" => "YOUR_DB_NAME"
];

$conn = new mysqli(
    $config["host"],
    $config["user"],
    $config["pass"],
    $config["name"]
);

if ($conn->connect_error) {
    exit("Database unavailable");
}

$conn->set_charset("utf8mb4");

// Handle upload
if (isset($_POST['upload'])) {
    $file = $_FILES['pdf_file'];
    $type = $_POST['file_type'];

    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $_SESSION['msg'] = "❌ Only PDF files are allowed.";
        } else {
            $fileName = basename($file['name']);
            $target = "guideline/" . $fileName;

            // Create guideline folder if not exists
            if (!file_exists('guideline')) {
                mkdir('guideline', 0777, true);
            }

            if (move_uploaded_file($file['tmp_name'], $target)) {
                $stmt = $conn->prepare("INSERT INTO guidelines (file_name, file_type, file_path) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $fileName, $type, $target);
                $stmt->execute();
                $_SESSION['msg'] = "✅ File uploaded successfully.";
            } else {
                $_SESSION['msg'] = "❌ Failed to upload file.";
            }
        }
    }
    header("Location: guidelines.php");
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $res = $conn->query("SELECT * FROM guidelines WHERE id=$id");
    if ($res->num_rows) {
        $file = $res->fetch_assoc();
        unlink($file['file_path']);
        $conn->query("DELETE FROM guidelines WHERE id=$id");
        
        // Reorder IDs sequentially
        $conn->query("SET @count = 0;");
        $conn->query("UPDATE guidelines SET id = @count:=@count+1;");
        $conn->query("ALTER TABLE guidelines AUTO_INCREMENT = 1;");
    }
    $_SESSION['msg'] = "🗑️ File deleted successfully.";
    header("Location: guidelines.php");
    exit;
}

// Handle reupload
if (isset($_POST['reupload'])) {
    $id = $_POST['file_id'];
    $file = $_FILES['new_file'];

    if ($file['error'] === 0 && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf') {
        $fileName = basename($file['name']);
        $target = "guideline/" . $fileName;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $stmt = $conn->prepare("UPDATE guidelines SET file_name=?, file_path=? WHERE id=?");
            $stmt->bind_param("ssi", $fileName, $target, $id);
            $stmt->execute();
            $_SESSION['msg'] = "♻️ File reuploaded successfully.";
        }
    } else {
        $_SESSION['msg'] = "❌ Invalid file or upload error. Only PDF files allowed.";
    }
    header("Location: guidelines.php");
    exit;
}

// Handle search - Get search parameters
$type_search = isset($_GET['type_search']) ? $_GET['type_search'] : '';
$name_search = isset($_GET['name_search']) ? $_GET['name_search'] : '';

// Build query based on search parameters
$query = "SELECT * FROM guidelines WHERE 1=1";
$params = array();

if (!empty($type_search)) {
    $query .= " AND file_type = ?";
    $params[] = $type_search;
}

if (!empty($name_search)) {
    $query .= " AND file_name LIKE ?";
    $params[] = "%$name_search%";
}

$query .= " ORDER BY id ASC";

// Prepare and execute the query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📘 File Upload Management</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f7fa; 
            color: #333;
        }
		
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
		
        h1, h2 { 
            color: #007bff; 
            margin-top: 0;
        }
		
        .upload-section { 
            margin-bottom: 20px; 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 10px; 
            box-shadow: 0 0 5px rgba(0,0,0,0.1); 
        }
		
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            background: white; 
        }
		
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px; 
            text-align: center; 
        }
		
        th { 
            background: #007bff; 
            color: white; 
        }
		
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
		
        button, select, input[type=file] {
            padding: 8px 12px; 
            margin: 5px; 
            border-radius: 6px; 
            border: none; 
            cursor: pointer;
            font-size: 14px;
        }
		
        button:hover { 
            opacity: 0.8; 
        }
		
        .delete-btn { 
            background: #dc3545; 
            color: white; 
        }
		
        .download-btn { 
            background: #28a745; 
            color: white; 
        }
		
        .reupload-btn { 
            background: #ffc107; 
            color: black; 
        }
		
        .upload-btn {
            background: #ffbb00; 
            color: black;
        }
		
        .search-section {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
		
        .search-section input {
            flex: 1;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }
		
        .search-section button {
            background: #6c757d;
            color: white;
        }
		
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
        }
		
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
		
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
		
        .action-form {
            display: inline;
        }
		
        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                border: 1px solid #ccc;
                margin-bottom: 10px;
            }
            td {
                border: none;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 50%;
                text-align: left;
            }
            td:before {
                position: absolute;
                top: 12px;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                font-weight: bold;
            }
            td:nth-of-type(1):before { content: "ID"; }
            td:nth-of-type(2):before { content: "File Name"; }
            td:nth-of-type(3):before { content: "Type"; }
            td:nth-of-type(4):before { content: "Actions"; }
        }
		
		.search-container {
			margin-bottom: 20px; 
			background: #f8f9fa; 
			padding: 20px; 
			border-radius: 10px; 
			box-shadow: 0 0 5px rgba(0,0,0,0.1);
			border: 1px solid #e9ecef;
		}

		.search-container h3 {
			color: #007bff;
			margin-top: 0;
			margin-bottom: 15px;
			font-size: 1.25rem;
		}

		.search-filters {
			display: flex;
			flex-direction: column;
			gap: 15px;
		}

		.filter-row {
			display: flex;
			align-items: center;
			gap: 15px;
		}

		.filter-group {
			display: flex;
			align-items: center;
			gap: 15px;
			flex: 1;
		}

		.filter-label {
			font-weight: bold;
			color: #495057;
			min-width: 150px;
			text-align: right;
		}

		.filter-select {
			padding: 8px 12px;
			border-radius: 6px;
			border: 2px solid #495057; /* Darker border */
			background: white;
			font-size: 14px;
			flex: 1;
			max-width: 300px;
		}

		.filter-input {
			padding: 8px 12px;
			border-radius: 6px;
			border: 1px solid #ddd;
			background: white;
			font-size: 14px;
			flex: 1;
		}

		.search-actions {
			display: flex;
			gap: 10px;
			margin-top: 20px;
			padding-top: 15px;
			border-top: 1px solid #dee2e6;
		}

		.search-btn {
			background: #6c757d;
			color: white;
			padding: 8px 20px;
			border-radius: 6px;
			border: none;
			cursor: pointer;
			font-size: 14px;
		}

		.clear-btn {
			background: #17a2b8;
			color: white;
			padding: 10px 10px;
			border-radius: 6px;
			border: none;
			cursor: pointer;
			font-size: 14px;
			display: inline-block;
		}

		.search-btn:hover, .clear-btn:hover {
			opacity: 0.8;
		}

		/* Responsive Design */
		@media (max-width: 768px) {
			.filter-row {
				flex-direction: column;
				align-items: stretch;
			}
    
			.filter-group {
				flex-direction: column;
				align-items: stretch;
				gap: 8px;
			}
    
			.filter-label {
				text-align: left;
				min-width: auto;
			}
    
			.filter-select, .filter-input {
				max-width: none;
				width: 100%;
			}
		}
    </style>
</head>

<body>
    <div class="container">
        <h1 style="text-align:center;">📘 File View/Edit/Upload Management System (ODL - Guideline) </h1>
		<div style="margin-top: 15px; text-align: center;">
        <a href="index.php" style="text-decoration: none;">
            <button type="button" style="padding: 10px 22px; border: none; border-radius: 8px; background: #007bff; color: white; font-size: 16px; cursor: pointer; transition: 0.3s;">
                ← Back to Index
            </button>
        </a>
    </div>
	<br>
		<h2 style="text-align:center; color:#000000;">Some guideline is provided inside this file system </h2>
        
        <?php if (isset($_SESSION['msg'])): ?>
            <div class="message <?php echo strpos($_SESSION['msg'], '❌') !== false ? 'error' : 'success'; ?>">
                <?php 
                    echo $_SESSION['msg']; 
                    unset($_SESSION['msg']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="upload-section">
            <h2>📤 Upload New File (Only PDF Files)</h2>
            <form method="POST" enctype="multipart/form-data">
                <div>
                    <label><strong>File Type:</strong></label>
                    <select name="file_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Assignment">Assignment</option>
                        <option value="Timetable">Timetable</option>
                        <option value="Payment">Payment</option>
                        <option value="Email">Email</option>
                        <option value="Course">Course</option>
                        <option value="Result">Result</option>
                        <option value="Others">Others</option>
                    </select>
                </div>
                <div style="margin-top: 10px;">
                    <label><strong>Select PDF File:</strong></label>
                    <input type="file" name="pdf_file" accept=".pdf" required style="width: 1000px;">
                </div>
                <div style="margin-top: 15px;">
                    <button type="submit" name="upload" class="upload-btn">⬆️ Upload File</button>
                </div>
            </form>
        </div>
        
		 <div class="search-container">
    <h2>🔍 Search Files</h2>
    <div class="search-section">
        <form method="GET">
            <div class="search-filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="type_search" class="filter-label">Filter by Type:</label>
                        <select name="type_search" id="type_search" class="filter-select">
                            <option value="">All Types</option>
                            <option value="Assignment" <?= $type_search == 'Assignment' ? 'selected' : '' ?>>Assignment</option>
                            <option value="Timetable" <?= $type_search == 'Timetable' ? 'selected' : '' ?>>Timetable</option>
                            <option value="Payment" <?= $type_search == 'Payment' ? 'selected' : '' ?>>Payment</option>
                            <option value="Email" <?= $type_search == 'Email' ? 'selected' : '' ?>>Email</option>
                            <option value="Course" <?= $type_search == 'Course' ? 'selected' : '' ?>>Course</option>
                            <option value="Result" <?= $type_search == 'Result' ? 'selected' : '' ?>>Result</option>
                            <option value="Others" <?= $type_search == 'Others' ? 'selected' : '' ?>>Others</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="name_search" class="filter-label">Filter by File Name:</label>
                        <input type="text" name="name_search" id="name_search" 
                               placeholder="Enter file name..." value="<?= htmlspecialchars($name_search) ?>" class="filter-input">
                    </div>
                </div>
            </div>
            
            <div class="search-actions">
                <button type="submit" class="search-btn">Search</button>
                <?php if (!empty($type_search) || !empty($name_search)): ?>
                    <a href="guidelines.php" class="clear-btn">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
        <h2>📂 Uploaded Files</h2>
        <?php if ($result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>File Name</th>
                    <th>Type</th>
                    <th>Actions</th>
                </tr>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['file_name']) ?></td>
                    <td><?= htmlspecialchars($row['file_type']) ?></td>
                    <td>
                        <a href="<?= $row['file_path'] ?>" download>
                            <button class="download-btn">⬇️ Download</button>
                        </a>
                        <form method="POST" enctype="multipart/form-data" class="action-form">
                            <input type="hidden" name="file_id" value="<?= $row['id'] ?>">
                            <input type="file" name="new_file" accept=".pdf" required>
                            <button type="submit" name="reupload" class="reupload-btn">♻️ Reupload</button>
                        </form>
                        <a href="?delete=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this file?')">
                            <button class="delete-btn">🗑️ Delete</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 20px; background: #f8f9fa; border-radius: 5px;">
				<?= (empty($type_search) && empty($name_search)) ? 'No files uploaded yet.' : 'No files found matching your search.' ?>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>