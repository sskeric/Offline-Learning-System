<?php
session_start();

// Define original file path
$originalFile = "demo_template.xlsx";
$uploadDir = "uploads/";
$currentFile = $uploadDir . "current_data.xlsx";

// Create lecturer directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle Excel upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];

        // Validate Excel file
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($ext !== 'xlsx') {
            echo "<script>alert('❌ Only .xlsx Excel files are allowed!'); window.location='lecturer_page.php';</script>";
            exit;
        }

        // Check if user is trying to upload the original file
        if (strtolower($file['name']) === strtolower($originalFile)) {
            echo "<script>alert('❌ Please rename the file before uploading. You cannot upload \"$originalFile\" directly.'); window.location='lecturer_page.php';</script>";
            exit;
        }

        // Move uploaded file
        $target = $currentFile;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Store the uploaded filename in session
            $_SESSION['uploaded_filename'] = $file['name'];
            $_SESSION['is_original'] = false;
            
            echo "<script>alert('✅ Excel file uploaded successfully!'); window.location='lecturer_page.php';</script>";
            exit;
        } else {
            echo "<script>alert('❌ Failed to upload file.'); window.location='lecturer_page.php';</script>";
            exit;
        }
    }

    // Handle file deletion
    if (isset($_POST['delete'])) {
        if (file_exists($currentFile)) {
            unlink($currentFile);
            if (isset($_SESSION['uploaded_filename'])) {
                unset($_SESSION['uploaded_filename']);
            }
            if (isset($_SESSION['is_original'])) {
                unset($_SESSION['is_original']);
            }
            echo "<script>alert('🗑️ File deleted successfully!'); window.location='lecturer_page.php';</script>";
        } else {
            echo "<script>alert('⚠️ No file found to delete.'); window.location='lecturer_page.php';</script>";
        }
        exit;
    }

    // Handle restore to original
    if (isset($_POST['restore'])) {
        if (file_exists($originalFile)) {
            // Copy original file to current file location
            if (copy($originalFile, $currentFile)) {
                $_SESSION['uploaded_filename'] = $originalFile;
                $_SESSION['is_original'] = true;
                echo "<script>alert('🔄 Restored to original Excel file!'); window.location='lecturer_page.php';</script>";
            } else {
                echo "<script>alert('❌ Failed to restore original file, File is missing.'); window.location='lecturer_page.php';</script>";
            }
        } else {
            echo "<script>alert('⚠️ Original file not found: $originalFile. Please make sure the file exists in the same directory as this script.'); window.location='lecturer_page.php';</script>";
        }
        exit;
    }
}

// [Keep all the existing functions: parseExcelFile, parseSharedStrings, parseSheetData]
// Function to parse Excel file using SimpleXML
function parseExcelFile($filePath) {
    if (!file_exists($filePath)) {
        return ['error' => 'File not found'];
    }

    $zip = new ZipArchive;
    $result = $zip->open($filePath);
    
    if ($result !== TRUE) {
        return ['error' => 'Failed to open Excel file. Error code: ' . $result];
    }

    $sharedStrings = [];
    $rows = [];

    try {
        // Read shared strings
        if (($sharedStringXML = $zip->getFromName('xl/sharedStrings.xml')) !== FALSE) {
            $sharedStrings = parseSharedStrings($sharedStringXML);
        }

        // Read the first worksheet
        $sheetXML = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXML === FALSE) {
            $zip->close();
            return ['error' => 'Could not read sheet1.xml from Excel file'];
        }

        $rows = parseSheetData($sheetXML, $sharedStrings);
        
    } catch (Exception $e) {
        $zip->close();
        return ['error' => 'Error parsing Excel file: ' . $e->getMessage()];
    }

    $zip->close();
    return $rows;
}

// Parse shared strings
function parseSharedStrings($xmlString) {
    $sharedStrings = [];
    libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($xmlString);
    if ($xml === FALSE) {
        return $sharedStrings;
    }

    foreach ($xml->si as $item) {
        $text = '';
        if (isset($item->t)) {
            $text = (string)$item->t;
        } elseif (isset($item->r)) {
            // Handle rich text format
            foreach ($item->r as $richText) {
                if (isset($richText->t)) {
                    $text .= (string)$richText->t;
                }
            }
        }
        $sharedStrings[] = $text;
    }
    
    return $sharedStrings;
}

// Parse sheet data
function parseSheetData($xmlString, $sharedStrings) {
    $rows = [];
    libxml_use_internal_errors(true);
    
    $xml = simplexml_load_string($xmlString);
    if ($xml === FALSE) {
        return $rows;
    }

    // Get all rows
    foreach ($xml->sheetData->row as $row) {
        $rowData = [];
        $rowIndex = (int)$row['r'];
        
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            $cellType = isset($cell['t']) ? (string)$cell['t'] : '';
            $cellValue = '';
            
            if (isset($cell->v)) {
                $value = (string)$cell->v;
                
                if ($cellType === 's') {
                    // Shared string
                    $index = intval($value);
                    $cellValue = $sharedStrings[$index] ?? $value;
                } else if ($cellType === 'str') {
                    // Formula string
                    $cellValue = $value;
                } else {
                    // Direct value
                    $cellValue = $value;
                }
            }
            
            $rowData[] = $cellValue;
        }
        
        $rows[] = $rowData;
    }
    
    return $rows;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lecturer List Upload</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #f9f9f9;
    margin: 0;
    padding: 0;
  }

  h2 {
    text-align: center;
    color: #007bff;
    margin-top: 30px;
  }

  p {
    text-align: center;
    font-size: 16px;
    color: #444;
  }

  .upload-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 30px;
  }

  .file-input-container {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
  }

  input[type="file"] {
    border: 1px solid #007bff;
    border-radius: 6px;
    padding: 8px;
    font-size: 16px;
    cursor: pointer;
    background: white;
    width: 300px;
  }

  button {
    padding: 10px 22px;
    border: none;
    border-radius: 8px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
  }

  button:hover {
    transform: scale(1.05);
  }

  .upload-btn {
    background: #ffbb00;
	color: black;
  }

  .upload-btn:hover {
    background: #ffd000;
  }

  .restore-btn {
    background: #28a745;
  }

  .restore-btn:hover {
    background: #218838;
  }

  .restore-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
  }

  .delete-btn-small {
    background: #dc3545;
    padding: 5px 10px;
    border-radius: 50%;
    font-size: 14px;
    margin-left: 10px;
  }

  .delete-btn-small:hover {
    background: #c82333;
  }

  .current-file-display {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e7f3ff;
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #b3d9ff;
    margin: 15px 0;
  }

  .file-name {
    font-weight: bold;
    margin-right: 10px;
  }

  .original-badge {
    background: #28a745;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-right: 10px;
  }

  .uploaded-badge {
    background: #ffc107;
    color: black;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-right: 10px;
  }

  table {
    border-collapse: collapse;
    width: 95%;
    margin: 40px auto;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
  }

  th, td {
    padding: 12px;
    border: 1px solid #ddd;
    text-align: left;
  }

  th {
    background: #007bff;
    color: white;
    font-weight: bold;
    text-align: center;
  }

  tr:hover {
    background-color: #f5f5f5;
  }

  .no-data {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px;
    background: white;
    margin: 20px auto;
    width: 80%;
    border-radius: 8px;
  }

  .error {
    text-align: center;
    color: #dc3545;
    background: #f8d7da;
    padding: 15px;
    margin: 20px auto;
    width: 80%;
    border-radius: 8px;
    border: 1px solid #f5c6cb;
  }

  .button-container {
    display: flex;
    gap: 10px;
    margin-top: 10px;
  }

  .debug-info {
    background: #f8f9fa;
    padding: 15px;
    margin: 20px auto;
    width: 80%;
    border-radius: 8px;
    font-size: 14px;
    color: #666;
  }

</style>
</head>
<body>

<h2>Table Lists</h2>
<p>📘 Upload your Excel file below (only <strong>.xlsx</strong> format accepted).</p>  
<p>If the file is corrupted or the format doesn't match, an error will appear.</p>
<p>You can't upload excel file name with demo_template because it is an original file</p>

<div class="upload-container">
  <!-- SEPARATE FORM FOR FILE UPLOAD -->
  <form method="POST" enctype="multipart/form-data">
    <div class="file-input-container">
      <input type="file" name="file" accept=".xlsx" required style="width: 330px;">
    </div>
    
    <div class="button-container" style="display: flex; justify-content: center;">
      <button type="submit" class="upload-btn">⬆️ Upload Excel</button>
    </div>
  </form>

  <!-- SEPARATE FORM FOR RESTORE BUTTON -->
  <form method="POST" style="margin-top: 10px;">
    <div class="button-container">
      <?php 
      $originalFileExists = file_exists($originalFile);
      ?>
      <button type="submit" name="restore" value="1" class="restore-btn" 
              <?php echo !$originalFileExists ? 'disabled' : ''; ?>>
        🔄 Restore Original
      </button>
    </div>
  </form>
  
   <div style="margin-top: 15px;">
    <a href="index.php" style="text-decoration: none;">
      <button type="button" style="padding: 10px 22px; border: none; border-radius: 8px; background: #007bff; color: white; font-size: 16px; cursor: pointer; transition: 0.3s;">
        ← Back to Index
      </button>
    </a>
  </div>
</div>

<?php

// Display current file information
if (file_exists($currentFile)) {
    // Determine current file status
    $currentFilename = isset($_SESSION['uploaded_filename']) ? $_SESSION['uploaded_filename'] : $originalFile;
    $isOriginalFile = isset($_SESSION['is_original']) ? $_SESSION['is_original'] : true;
    
    echo "<div class='current-file-display'>";
    echo "<div class='file-name'>📁 " . htmlspecialchars($currentFilename) . "</div>";
    
    if ($isOriginalFile) {
        echo "<span class='original-badge'>ORIGINAL</span>";
    } else {
        echo "<span class='uploaded-badge'>UPLOADED</span>";
    }
    
    // Delete button (X symbol) - SEPARATE FORM
    echo "<form method='POST' style='display: inline; margin: 0;'>";
    echo "<button type='submit' name='delete' value='1' class='delete-btn-small' title='Delete file'>🗑️</button>";
    echo "</form>";
    
    echo "</div>";
    
    // File info
    echo "<div style='text-align: center; margin-bottom: 20px;'>";
    echo "<strong>📊 File Size:</strong> " . number_format(filesize($currentFile) / 1024, 2) . " KB";
    
    // Show restore hint if not original
    if (!$isOriginalFile && file_exists($originalFile)) {
        echo " • <span style='color: #666;'>Click 'Restore Original' to switch back to $originalFile</span>";
    }
    echo "</div>";
    
    // Parse and display Excel data
    $excelData = parseExcelFile($currentFile);
    
    if (isset($excelData['error'])) {
        echo '<div class="error">❌ Error: ' . htmlspecialchars($excelData['error']) . '</div>';
    } elseif (!empty($excelData)) {
        echo '<table>';
        
        // Display all rows
        foreach ($excelData as $rowIndex => $row) {
            echo '<tr>';
            foreach ($row as $cellIndex => $cell) {
                if ($rowIndex === 0) {
                    // First row as header
                    echo '<th>' . htmlspecialchars($cell) . '</th>';
                } else {
                    echo '<td>' . htmlspecialchars($cell) . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<div style="text-align: center; margin: 20px;">';
        echo '<strong>📈 Data Summary:</strong> ' . count($excelData) . ' rows, ' . (count($excelData) > 0 ? count($excelData[0]) : 0) . ' columns';
        echo '</div>';
    } else {
        echo '<div class="no-data">No data found in the Excel file.</div>';
    }
} else {
    echo '<div class="no-data">No Excel file uploaded yet. Please upload an .xlsx file to see the data.</div>';
    
    // Show original file info if available
    if (file_exists($originalFile)) {
        echo '<div style="text-align: center; margin: 20px;">';
        echo '<div class="current-file-display" style="background: #f8f9fa; border-color: #dee2e6;">';
        echo "<div class='file-name'>📁 " . htmlspecialchars($originalFile) . " (Available)</div>";
        echo "<span class='original-badge'>READY TO LOAD</span>";
        echo "</div>";
        echo '<p>Click "Restore Original" above to load the default Excel file.</p>';
        echo '</div>';
    }
}
?>

</body>
</html>