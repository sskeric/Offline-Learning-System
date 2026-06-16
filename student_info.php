<?php

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

$allowed_columns = [
    'name',
    'student_id',
    'phone',
    'email',
    'status',
    'discussion_day',
    'free_time',
    'communicate_tools',
    'form_group',
    'years',
    'major',
    'expertise'
];

// =========================
// HANDLE AJAX
// =========================
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'add') {

        $sql = "INSERT INTO students 
        (name, student_id, phone, email, status, discussion_day, free_time, communicate_tools, form_group, years, major, expertise)
        VALUES 
        ('Sample Student', 'iXXXXXXXX', '0000000000', 'student@example.com',
         'Employed', 'Monday', 'Morning', 'WhatsApp', 'Canvas', 1, 'General', 'Average')";

        $conn->query($sql);
        echo "added";
        exit;
    }


    if ($action == 'update') {

        $id = intval($_POST['id']);
        $column = $_POST['column'];
        $value = trim($_POST['value']);

        // whitelist column (VERY IMPORTANT)
        if (!in_array($column, $allowed_columns)) {
            exit("Invalid column");
        }

        // validation
        if ($column == 'name' && !preg_match("/^[a-zA-Z ]*$/", $value)) exit("Invalid Name");
		// Example student ID format: i followed by 8 digits (e.g., i00000000)
        if ($column == 'student_id' && !preg_match("/^i\d{8}$/", $value)) exit("Invalid ID");
        if ($column == 'phone' && !preg_match("/^\d{10,15}$/", $value)) exit("Invalid Phone");
        if ($column == 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) exit("Invalid Email");

        // SAFE UPDATE
        $stmt = $conn->prepare("UPDATE students SET $column = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        $stmt->execute();

        echo "updated";
        exit;
    }

    if ($action == 'delete') {

        $id = intval($_POST['id']);

        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        echo "deleted";
        exit;
    }
}

if (isset($_GET['export']) && $_GET['export'] == 'excel') {

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=student_data.xls");

    $result = $conn->query("SELECT * FROM students");

    echo "<table border='1'>";
    echo "<tr>
        <th>ID</th><th>Name</th><th>Student ID</th>
        <th>Phone</th><th>Email</th>
        <th>Status</th><th>Day</th><th>Time</th>
        <th>Tool</th><th>Group</th><th>Year</th>
        <th>Major</th><th>Expertise</th>
    </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$row['id']}</td>
            <td>{$row['name']}</td>
            <td>{$row['student_id']}</td>
            <td>HIDDEN</td>
            <td>HIDDEN</td>
            <td>{$row['status']}</td>
            <td>{$row['discussion_day']}</td>
            <td>{$row['free_time']}</td>
            <td>{$row['communicate_tools']}</td>
            <td>{$row['form_group']}</td>
            <td>{$row['years']}</td>
            <td>{$row['major']}</td>
            <td>{$row['expertise']}</td>
        </tr>";
    }

    echo "</table>";
    exit;
}

// FETCH
$result = $conn->query("SELECT * FROM students ORDER BY id DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Info | Offline ODL Canvas</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
        th { background: #007bff; color: white; }
        .add-btn { background: #28a745; color: white; border: none; padding: 10px 15px; font-size:23px; border-radius: 5px; margin-top: 15px; cursor: pointer; }
        .add-btn:hover { background: #218838; }
        .del-btn { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
		.del-btn:hover { background: #b02a37; }
    </style>
	<style>
		.group-btn {
			background: #007bff;
			color: white;
			border: none;
			padding: 10px 15px;
			font-size: 25px;
			margin-top: 15px;
			border-radius: 5px;
			text-decoration: none;
			cursor: pointer;
			transition: background 0.3s ease;
		}

		.group-btn:hover {
			background: #0056b3;
		}
	</style>
</head>
<body>

<h2 style="text-align:center;">Search Filter</h2>

<!-- 🔍 Search + Filter Controls (Clean Two-Row Layout) -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 25px; align-items: flex-end;">

  <!-- Search -->
  <div style="display: flex; flex-direction: column;">
    <label for="searchInput" style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Search (ID or Name)</label>
    <input type="text" id="searchInput" placeholder="Enter Student ID or Name"
           style="padding: 8px; width: 250px; border: 1px solid #aaa; border-radius: 6px;">
  </div>

  <!-- Status -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Status</label>
    <select id="filterStatus" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px; ">
      <option value="">All</option>
      <option>Employed</option>
      <option>Unemployment</option>
      <option>Freelancer</option>
    </select>
  </div>

  <!-- Discussion Day -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Discussion Day</label>
    <select id="filterDay" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px;">
      <option value="">All</option>
      <option>Monday</option>
      <option>Tuesday</option>
      <option>Wednesday</option>
      <option>Thursday</option>
      <option>Friday</option>
      <option>Weekend</option>
      <option>Most of the Time</option>
    </select>
  </div>

  <!-- Free Time -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Free Time</label>
    <select id="filterFreeTime" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px;">
      <option value="">All</option>
      <option>Morning</option>
      <option>Afternoon</option>
      <option>Night</option>
      <option>Others (working shift)</option>
	  <option>Whole Day</option>
    </select>
  </div>

  <!-- Communication Tools -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Communicate Tools</label>
    <select id="filterComm" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px;">
      <option value="">All</option>
      <option>WhatsApp</option>
      <option>Canvas</option>
      <option>Microsoft Teams</option>
      <option>Email</option>
      <option>Others</option>
    </select>
  </div>

  <!-- Form Group -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Form Group</label>
    <select id="filterGroup" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px; ">
      <option value="">All</option>
      <option>WhatsApp</option>
      <option>Canvas</option>
      <option>Microsoft Teams</option>
      <option>Others</option>
    </select>
  </div>

  <!-- Year -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Year</label>
    <select id="filterYear" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px;">
      <option value=>All</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
    </select>
  </div>

  <!-- Major -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Major</label>
    <select id="filterMajor" style="padding: 8px; width: auto; min-width: 120px; max-width: 100%; border: 1px solid #aaa; border-radius: 6px;">
      <option value="">All</option>
      <option>General</option>
      <option>Software Engineering</option>
      <option>Cloud Computing</option>
      <option>Mobile Computing</option>
      <option>Business Analytics</option>
      <option>Network and Security</option>
    </select>
  </div>

  <!-- Expertise -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px;  text-align: center;">Expertise</label>
    <select id="filterExpertise" style="padding: 8px; width: 220px; border: 1px solid #aaa; border-radius: 6px;">
      <option value="">All</option>
      <option>Coding / Programming</option>
      <option>Animation / Video Editing</option>
      <option>UML Diagram</option>
      <option>Documentation</option>
      <option>UI/UX Design</option>
      <option>Average</option>
    </select>
  </div>

  <!-- Clear Button -->
  <div style="display: flex; flex-direction: column;">
    <label style="font-weight: 600; margin-bottom: 5px; color: transparent;">.</label>
    <button onclick="clearFilters()" 
            style="padding: 9px 15px; border: none; background-color: #007bff; color: white; border-radius: 6px; cursor: pointer;">Clear
    </button>
  </div>
</div>

<br><br>

<div style="text-align:center;">
  <img src="student.jpg" alt="Logo" width="120">
</div>

<h2 style="text-align:center;" > Student Information </h2>
<h3 style="text-align:center;" >1. Double click on a field to edit. Click outside to field save</h3>
<h3 style="text-align:center;" >2. Everytime you (+ Add student), the new default value are: 'New Student', 'i00000000', '00000000000', 'example123@student.university.edu.my' </h3>
<h3 style="text-align:center;" >3. You cant (+ Add student) if there is a duplicate (name, student id, phone, email) or the new default value haven't be edit yet</h3>
<br>

<div style="display: flex; justify-content: center; align-items: center; gap: 60px;">
<button class="add-btn" onclick="addStudent()">➕ Add Student</button>
  <a href="group_management.php" class="group-btn">👥 Group</a>
</div>

<br><br><br>

<table id="studentTable">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Student ID</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Status</th>
        <th>Discussion Day</th>
        <th>Free Time</th>
        <th>Communicate Tools</th>
        <th>Form Group</th>
        <th>Years</th>
        <th>Major</th>
        <th>Expertise</th>
        <th>Action</th>
    </tr>

    <?php while($row = $result->fetch_assoc()): ?>
    <tr data-id="<?= $row['id'] ?>">
		<td><?= $row['id'] ?></td>
		<td ondblclick="editText(this, 'name')" data-column="name"><?= htmlspecialchars($row['name']) ?></td>
		<td ondblclick="editText(this, 'student_id')" data-column="student_id"><?= htmlspecialchars($row['student_id']) ?></td>
		<td ondblclick="editText(this, 'phone')" data-column="phone"><?= htmlspecialchars($row['phone']) ?></td>
		<td ondblclick="editText(this, 'email')" data-column="email"><?= htmlspecialchars($row['email']) ?></td>
		<td ondblclick="editSelect(this, ['Employed', 'Unemployment', 'Freelancer'], 'status')" data-column="status"><?= htmlspecialchars($row['status']) ?></td>
		<td ondblclick="editSelect(this, ['Monday','Tuesday','Wednesday','Thursday','Friday','Weekend','Most of the Time'], 'discussion_day')" data-column="discussion_day"><?= htmlspecialchars($row['discussion_day']) ?></td>
		<td ondblclick="editSelect(this, ['Morning','Afternoon','Night','Others (working shift)', 'Whole Day'], 'free_time')" data-column="free_time"><?= htmlspecialchars($row['free_time']) ?></td>
		<td ondblclick="editSelect(this, ['WhatsApp','Canvas','Microsoft Teams','Email','Others'], 'communicate_tools')" data-column="communicate_tools"><?= htmlspecialchars($row['communicate_tools']) ?></td>
		<td ondblclick="editSelect(this, ['WhatsApp','Canvas','Microsoft Teams','Others'], 'form_group')" data-column="form_group"><?= htmlspecialchars($row['form_group']) ?></td>
		<td ondblclick="editSelect(this, ['1','2','3'], 'years')" data-column="years"><?= htmlspecialchars($row['years']) ?></td>
		<td ondblclick="editSelect(this, ['General','Software Engineering','Cloud Computing','Mobile Computing','Business Analytics','Network and Security'], 'major')" data-column="major"><?= htmlspecialchars($row['major']) ?></td>
		<td ondblclick="editSelect(this, ['Coding / Programming','Animation / Video Editing','UML Diagram','Documentation','UI/UX Design','Average'], 'expertise')" data-column="expertise"><?= htmlspecialchars($row['expertise']) ?></td>
		<td><button class="del-btn" onclick="deleteRow(<?= $row['id'] ?>)">Delete</button></td>
    </tr>
    <?php endwhile; ?>
</table>

<br>

<div style="text-align:center; margin-top:20px; font-size:20px;">
  <a href="?export=excel">📤 Export to Excel File (All students information)</a>
</div>

<div style="text-align:center; margin-top:40px;">

  <!-- Back Button -->
  <button 
    onclick="window.location.href='index.php'" 
    style="
      background:#007bff; 
      color:white; 
      border:none; 
      padding:14px 28px; 
      border-radius:10px; 
      font-size:25px; 
      cursor:pointer; 
      transition: all 0.3s ease;
      margin-bottom:20px;
    "
    onmouseover="this.style.background='#0056b3'; this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#007bff'; this.style.transform='scale(1)'">⬅️ Back
  </button>

  <br>
  
</div>
 
<script>
let previousValue = "";

// 🔔 Toast Message (top-center, bigger, animated)
function showToast(message, type = "success") {
    const toast = document.createElement("div");
    toast.innerText = message;
    toast.style.position = "fixed";
    toast.style.top = "30px";
    toast.style.left = "50%";
    toast.style.transform = "translateX(-50%)";
    toast.style.padding = "15px 30px";
    toast.style.fontSize = "16px";
    toast.style.fontWeight = "bold";
    toast.style.color = "#fff";
    toast.style.borderRadius = "8px";
    toast.style.backgroundColor = type === "success" ? "#28a745" : "#dc3545";
    toast.style.zIndex = "9999";

    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 2500);
}

// Function to make editable text fields
function editText(cell, column) {

    if (cell.querySelector("input")) return;

    const originalValue = cell.innerText.trim();

    const input = document.createElement("input");
    input.value = originalValue;
    input.style.width = "100%";

    cell.innerHTML = "";
    cell.appendChild(input);
    input.focus();

    input.onblur = function () {
        const value = input.value.trim();

        if (!value) {
            cell.innerText = originalValue;
            showToast("Cannot be empty", "error");
            return;
        }

        cell.innerText = value;
        updateCell(cell, column, value, originalValue);
    };

    input.onkeydown = function (e) {
        if (e.key === "Escape") {
            cell.innerText = originalValue;
        }
    };
}

// ======================
// EDIT SELECT (ONLY ONE VERSION)
// ======================
function editSelect(cell, options, column) {

    const originalValue = cell.innerText.trim();

    const select = document.createElement("select");

    options.forEach(opt => {
        const option = document.createElement("option");
        option.value = opt;
        option.text = opt;
        if (opt === originalValue) option.selected = true;
        select.appendChild(option);
    });

    cell.innerHTML = "";
    cell.appendChild(select);
    select.focus();

    select.onblur = function () {
        const value = select.value;
        cell.innerText = value;
        updateCell(cell, column, value, originalValue);
    };

    select.onkeydown = function (e) {
        if (e.key === "Escape") {
            cell.innerText = originalValue;
        }
    };
}

// ======================
// UPDATE AJAX (SAFE)
// ======================
function updateCell(cell, column, value, originalValue) {

    const id = cell.parentElement.dataset.id;

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function () {
        if (this.responseText.includes("updated")) {
            showToast("Saved");
        } else {
            cell.innerText = originalValue;
            showToast("Update failed", "error");
        }
    };

    xhr.send(
        "action=update&id=" +
        encodeURIComponent(id) +
        "&column=" +
        encodeURIComponent(column) +
        "&value=" +
        encodeURIComponent(value)
    );
}

// ======================
// DELETE
// ======================
function deleteRow(id) {
    if (!confirm("Delete?")) return;

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onload = function () {
        if (this.responseText.includes("deleted")) {
            showToast("Deleted");
            location.reload();
        }
    };

    xhr.send("action=delete&id=" + encodeURIComponent(id));
}
</script>

<script>
// 🔍 SEARCH + FILTER FUNCTION
function filterTable() {
    const searchValue = document.getElementById("searchInput").value.toLowerCase();
    const filters = {
        status: document.getElementById("filterStatus").value.toLowerCase(),
        day: document.getElementById("filterDay").value.toLowerCase(),
        time: document.getElementById("filterFreeTime").value.toLowerCase(),
        comm: document.getElementById("filterComm").value.toLowerCase(),
        group: document.getElementById("filterGroup").value.toLowerCase(),
        year: document.getElementById("filterYear").value.toLowerCase(),
        major: document.getElementById("filterMajor").value.toLowerCase(),
        expertise: document.getElementById("filterExpertise").value.toLowerCase(),
    };

    const rows = document.querySelectorAll("#studentTable tr:not(:first-child)");

    rows.forEach(row => {
        const cells = row.querySelectorAll("td");
        const name = cells[1].innerText.toLowerCase();
        const studentID = cells[2].innerText.toLowerCase();
        const status = cells[5].innerText.toLowerCase();
        const day = cells[6].innerText.toLowerCase();
        const time = cells[7].innerText.toLowerCase();
        const comm = cells[8].innerText.toLowerCase();
        const group = cells[9].innerText.toLowerCase();
        const year = cells[10].innerText.toLowerCase();
        const major = cells[11].innerText.toLowerCase();
        const expertise = cells[12].innerText.toLowerCase();

        // Search filter
        const matchesSearch = name.includes(searchValue) || studentID.includes(searchValue);

        // Dropdown filters
        const matchesFilters =
            (!filters.status || status === filters.status) &&
            (!filters.day || day === filters.day) &&
            (!filters.time || time === filters.time) &&
            (!filters.comm || comm === filters.comm) &&
            (!filters.group || group === filters.group) &&
            (!filters.year || year === filters.year) &&
            (!filters.major || major === filters.major) &&
            (!filters.expertise || expertise === filters.expertise);

        // Show or hide
        if (matchesSearch && matchesFilters) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// 🎯 Attach filter listeners
document.getElementById("searchInput").addEventListener("input", filterTable);
document.querySelectorAll("select").forEach(sel => sel.addEventListener("change", filterTable));

// 🔄 Clear all filters
function clearFilters() {
    document.getElementById("searchInput").value = "";
    document.querySelectorAll("select").forEach(sel => sel.value = "");
    filterTable();
}
</script>

</body>
</html>
