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

// ✅ Handle AJAX actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // ➕ Add new course
    if ($action == 'add') {
        // Check for any unedited "New Course"
        $check = $conn->prepare("SELECT id FROM courses WHERE TRIM(LOWER(course_name)) = 'new course' AND TRIM(LOWER(course_code)) = 'aaa0000a'");
		$check->execute();
		$check->store_result();
		if ($check->num_rows > 0) {
			exit("❌ Please edit the existing default course before adding another one.");
		}
		
		 // Check if the default course name already exists
        $check_name = $conn->prepare("SELECT id, course_code FROM courses WHERE TRIM(LOWER(course_name)) = 'new course'");
        $check_name->execute();
        $check_name->store_result();
        if ($check_name->num_rows > 0) {
            $check_name->bind_result($course_id, $course_code);
            $check_name->fetch();
            echo "❌ A course with name 'New Course' is default value (Code: $course_code). Please edit the existing default value first.";
            exit;
        }
		
		// Check if the default course code already exists
        $check_duplicate = $conn->prepare("SELECT id FROM courses WHERE course_code = 'AAA0000A'");
        $check_duplicate->execute();
        $check_duplicate->store_result();
        if ($check_duplicate->num_rows > 0) {
            echo "❌ A course with code 'AAA0000A' is default value . Please edit default value first.";
            exit;
        }
		
        // Insert a new blank/default record
        $sql = "INSERT INTO courses (
            course_name, course_code, status, `type`, years, credit_hour, prerequisites, assessment_list, tips
        ) VALUES (
            'New Course', 'AAA0000A', 'In Progress', 'Core', 1, 3, '-', 'Quiz/Test, Assignment, FE', 'No tips yet'
        )";

        if ($conn->query($sql)) {
            echo "added";
        } else {
            exit("❌ Failed to add course: " . $conn->error);
        }
        exit;
    }

    // ✏️ Update existing course
    if ($action == 'update') {
        $id = $_POST['id'];
        $column = $_POST['column'];
        $value = trim($_POST['value']);

        // 🔎 Basic validation
        if ($value === '') {
            exit("❌ Field cannot be left blank.");
        }

        // ✅ Course code format
        if ($column == 'course_code' && !preg_match("/^[A-Z]{3}\d{4}[A-Z]$/", $value)) {
            exit("❌ Invalid Course Code (format AAA0000A).");
        }

        // ✅ Credit hour = 1 digit (1–9)
        if ($column == 'credit_hour' && !preg_match("/^[1-9]$/", $value)) {
            exit("❌ Invalid Credit Hour (must be 1 digit between 1–9).");
        }

        // 🔁 Duplicate check for name or code
        if (in_array($column, ['course_name', 'course_code'])) {
            $check = $conn->prepare("SELECT id FROM courses WHERE $column = ? AND id != ?");
            $check->bind_param("si", $value, $id);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) {
                exit("❌ Duplicate $column found. Please use a unique value.");
            }
        }

        // ✅ Safe update
        $stmt = $conn->prepare("UPDATE courses SET `$column` = ? WHERE id = ?");
        $stmt->bind_param("si", $value, $id);
        if ($stmt->execute()) {
            echo "updated";
        } else {
            exit("❌ Update failed: " . $stmt->error);
        }
        exit;
    }

    // ❌ Delete course
    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        if ($conn->query("DELETE FROM courses WHERE id=$id")) {
            echo "deleted";
        } else {
            exit("❌ Delete failed: " . $conn->error);
        }
        exit;
    }
}


// Fetch data
$result = $conn->query("SELECT * FROM courses ORDER BY id ASC");
$courses = $conn->query("SELECT course_name FROM courses ORDER BY course_name ASC");
$course_names = [];
while ($r = $courses->fetch_assoc()) $course_names[] = $r['course_name'];
?>

<!DOCTYPE html>
<html>
<head>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<title>Course Info | Offline ODL Canvas</title>
<style>
	body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; text-align: center; }
	h2 { color: #007bff; }
	table { width: 100%; border-collapse: collapse; background: #fff; margin-top: 15px; }
	th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
	th { background: #007bff; color: white; }
	.add-btn { background: #28a745; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; transition: 0.3s; font-size: 18px; }
	.add-btn:hover { background: #218838; transform: scale(1.05); }
	.del-btn { background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; }
	.del-btn:hover { background: #b02a37; }
	input, select { text-align: center; }
</style>

<style>
	.filter-container {
		max-width: 1000px;
		margin: 25px auto;
		padding: 20px;
		background: #E8EBE0;
		border-radius: 12px;
		box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		display: flex;
		flex-direction: column;
		gap: 15px;
		font-family: Arial, sans-serif;
	}

	.filter-group {
		display: grid;
		grid-template-columns: 220px auto; /* first column fixed width for label */
		align-items: center;
		gap: 10px;
		margin-bottom: 8px;
	}

	.filter-label {
		font-weight: bold;
		text-align: right; /* align labels to the right edge */
		padding-right: 10px;
		white-space: nowrap;
	}

	.filter-options {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}


	.filter-options label {
		display: flex;
		align-items: center;
		gap: 5px;
		font-size: 14px;
	}

	#searchInput, #filterYear {
		width: 100%;
		padding: 8px;
		border-radius: 6px;
		border: 1px solid #ccc;
	}

	.clear-btn {
		align-self: center;
		padding: 8px 16px;
		background: #007bff;
		color: white;
		border: none;
		border-radius: 6px;
		cursor: pointer;
	}

	.clear-btn:hover {
		background: #0056b3;
	}
  </style>
  
</head>
<body>

<h2>Course Information</h2>
<h3 style="color: #000000;">Search filters features.</h3>

<!-- 🔍 Advanced Filters -->
<div class="filter-container">

  <!-- 🔍 Course Name Search -->
  <div class="filter-group">
	<label for="searchName" class="filter-label">Search by Course Name: </label>
	<input type="text" id="searchName" placeholder="Enter course name" style="width: 500px; padding: 8px;">
  </div>

<!-- 🔍 Course Code Search -->
  <div class="filter-group">
    <label for="searchCode" class="filter-label">Search by Course Code: </label>
    <input type="text" id="searchCode" placeholder="Enter course code" style="width: 500px; padding: 8px;">
  </div>

  <!-- Filter by Year -->
  <div class="filter-group">
    <label for="filterYear" class="filter-label">Years: </label>
    <select id="filterYear" style="width: 519px; padding: 8px;">
      <option value="">All</option>
      <option value="1">1</option>
      <option value="2">2</option>
      <option value="3">3</option>
    </select>
  </div>

  <!-- Filter by Type / Major -->
  <div class="filter-group">
    <span class="filter-label">Filter by Type / Major: </span>
    <div class="filter-options">
      <label><input type="checkbox" id="selectAllTypeMajor"> All</label>
      <label><input type="checkbox" name="typeMajor" value="Core"> Core</label>
      <label><input type="checkbox" name="typeMajor" value="Major:All"> All Major</label>
      <label><input type="checkbox" name="typeMajor" value="Major:BA"> BA</label>
      <label><input type="checkbox" name="typeMajor" value="Major:CC"> CC</label>
      <label><input type="checkbox" name="typeMajor" value="Major:NS"> NS</label>
      <label><input type="checkbox" name="typeMajor" value="Major:MC"> MC</label>
      <label><input type="checkbox" name="typeMajor" value="Major:SE"> SE</label>
      <label><input type="checkbox" name="typeMajor" value="Major:G"> G</label>
      <label><input type="checkbox" name="typeMajor" value="MPU"> MPU</label>
    </div>
  </div>

  <!-- Filter by Status -->
  <div class="filter-group">
    <span class="filter-label">Status: </span>
    <div class="filter-options">
      <label><input type="checkbox" id="selectAllStatus"> All</label>
      <label><input type="checkbox" name="status" value="In Progress"> In Progress</label>
      <label><input type="checkbox" name="status" value="Completed"> Completed</label>
      <label><input type="checkbox" name="status" value="CT"> CT</label>
      <label><input type="checkbox" name="status" value="CE"> CE</label>
      <label><input type="checkbox" name="status" value="CW"> CW</label>
      <label><input type="checkbox" name="status" value="-"> -</label>
      <label><input type="checkbox" name="status" value="R"> R</label>
    </div>
  </div>

  <!-- Filter by Prerequisite -->
  <div class="filter-group">
    <span class="filter-label">Prerequisite: </span>
    <div class="filter-options">
      <label><input type="radio" name="prereq" value="" > All</label>
      <label><input type="radio" name="prereq" value="yes"> Yes</label>
      <label><input type="radio" name="prereq" value="no"> No</label>
    </div>
  </div>

  <!-- Filter by Assessment List -->
  <div class="filter-group">
    <span class="filter-label">Assessment List: </span>
    <div class="filter-options">
      <label><input type="checkbox" id="selectAllAssessment"> All</label>
      <label><input type="checkbox" name="assessment" value="Quiz/Test, Assignment, FE"> Quiz/Test, Assignment, FE</label>
      <label><input type="checkbox" name="assessment" value="Quiz/Test, Assignment, FA"> Quiz/Test, Assignment, FA</label>
      <label><input type="checkbox" name="assessment" value="Assignment, FE"> Assignment, FE</label>
      <label><input type="checkbox" name="assessment" value="Assignment, FA"> Assignment, FA</label>
      <label><input type="checkbox" name="assessment" value="-"> -</label>
    </div>
  </div>

  <!-- Filter by Credit Hour -->
  <div class="filter-group">
    <span class="filter-label">Credit Hour: </span>
    <div class="filter-options">
      <label><input type="checkbox" id="selectAllCredit"> All</label>
      <label><input type="checkbox" name="credit" value="1"> 1</label>
      <label><input type="checkbox" name="credit" value="2"> 2</label>
      <label><input type="checkbox" name="credit" value="3"> 3</label>
      <label><input type="checkbox" name="credit" value="4"> 4</label>
      <label><input type="checkbox" name="credit" value="7"> 7</label>
      <label><input type="checkbox" name="credit" value="8"> 8</label>
      <label><input type="checkbox" name="credit" value="9"> 9</label>
    </div>
  </div>

  <!-- Clear Button -->
  <button onclick="clearFilters()" class="clear-btn">Clear Filters</button>
</div>

<br>

<h3 style="color: #ff6600;">1. Double click on a field to edit. Click outside to field save (+ Add Course Button At Bottom).</h3>
<h3 style="color: #ff6600;">2. Everytime you (+ Add Course), the new default value are: 'New Course', 'AAA0000A'</h3>
<h3 style="color: #ff6600;">3. You cant (+ Add Course) if there is a duplicate (Course Name, Code) or the new default value haven't be edit yet</h3>
<h3 style="color: #ff6600;">3. Short form | CT = Credit Transfer | CE = Credit Exemption | CE = Credit Waived | R = Retake/Resit </h3>

<table id="courseTable">
	<tr>
		<th>ID</th>
		<th>Course Name</th>
		<th>Code</th>
		<th>Status</th>
		<th>Type</th>
		<th>Years</th>
		<th>Credit Hour</th>
		<th>Prerequisites</th>
		<th>Assessment List</th>
		<th>Tips</th>
		<th>Action</th>
	</tr>

	<?php while($row = $result->fetch_assoc()): ?>
	<tr data-id="<?= $row['id'] ?>">
		<td><?= $row['id'] ?></td>
		<td ondblclick="editText(this, 'course_name')"><?= htmlspecialchars($row['course_name']) ?></td>
		<td ondblclick="editText(this, 'course_code')"><?= htmlspecialchars($row['course_code']) ?></td>
		<td data-column="status" ondblclick="editSelect(this, ['In Progress', 'Completed', '-', 'CT', 'CE', 'CW', 'R'])"><?= htmlspecialchars($row['status']) ?></td>
		<td data-column="type" ondblclick="editSelect(this, ['Core', 'Major', 'MPU'])"><?= htmlspecialchars($row['type']) ?></td>
		<td data-column="years" ondblclick="editSelect(this, ['1', '2', '3'])"><?= htmlspecialchars($row['years']) ?></td>
		<td ondblclick="editText(this, 'credit_hour')"><?= htmlspecialchars($row['credit_hour']) ?></td>
		<td data-column="prerequisites" ondblclick="editSelect(this, ['-', '<?= implode("','", $course_names) ?>'])"><?= htmlspecialchars($row['prerequisites']) ?></td>
		<td data-column="assessment_list" ondblclick="editSelect(this, ['Quiz/Test, Assignment, FE', 'Quiz/Test, Assignment, FA', 'Assignment, FE', 'Assignment, FA', '-'])"><?= htmlspecialchars($row['assessment_list']) ?></td>
		<td ondblclick="editText(this, 'tips')"><?= htmlspecialchars($row['tips']) ?></td>
		<td><button class="del-btn" onclick="deleteRow(<?= $row['id'] ?>)">Delete</button></td>
	</tr>
<?php endwhile; ?>
</table>

<br>

<a href="#" id="exportLink" style="color: #007bff; text-decoration: underline; cursor: pointer; font-size:20px;">Export to Excel</a>

<br><br>

<button class="add-btn" onclick="addCourse()">➕ Add Course</button>

<br><br>

<button 
    onclick="window.location.href='index.php'" 
    style="
      background:#007bff; 
      color:white; 
      border:none; 
      padding:14px 28px; 
      border-radius:10px; 
      font-size:20px; 
      cursor:pointer; 
      transition: all 0.3s ease;
      margin-bottom:20px;
    "
    onmouseover="this.style.background='#0056b3'; this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#007bff'; this.style.transform='scale(1)'">⬅️ Back
</button>
  
<script>
document.getElementById("exportLink").addEventListener("click", function (e) {
	e.preventDefault(); // Prevent page reload

	const table = document.getElementById("courseTable");
	const rows = Array.from(table.querySelectorAll("tr[data-id]")).filter(
		row => row.style.display !== "none"
	); // ✅ Only visible rows

	if (rows.length === 0) {
		alert("No data to export. Try adjusting your filters.");
		return;
	}

	// 🟢 Extract headers
	const headers = Array.from(table.querySelectorAll("tr:first-child th")).map(th => th.innerText.trim());
	
	// 🟢 Extract visible row data
	const data = rows.map(row =>
		Array.from(row.children).map(cell => cell.innerText.trim())
	);

	// 🟢 Combine header + data
	const worksheetData = [headers, ...data];

	// 🟢 Create and export Excel file
	const wb = XLSX.utils.book_new();
	const ws = XLSX.utils.aoa_to_sheet(worksheetData);
	XLSX.utils.book_append_sheet(wb, ws, "Filtered Data");
	XLSX.writeFile(wb, "Filtered_Course_List.xlsx");
});
</script>

<script>
// 🔁 Smart "Select All" logic using ID
function setupSmartAllCheckbox(allId, groupSelector) {
	const allBox = document.getElementById(allId);
	const group = document.querySelectorAll(groupSelector);

	if (!allBox || !group.length) return;
	// ✅ When "All" is clicked — check/uncheck all
	allBox.addEventListener("change", function() {
		group.forEach(cb => cb.checked = this.checked);
	});

	// 🔁 When any individual checkbox changes
	group.forEach(cb => {
		cb.addEventListener("change", function() {
			if (!this.checked) {
				allBox.checked = false; // uncheck "All" if one is unchecked
			} else {
				// if all are checked, mark "All" as checked again
				const allChecked = Array.from(group).every(c => c.checked);
				allBox.checked = allChecked;
			}
		});
	});
}

window.addEventListener("DOMContentLoaded", () => {
	setupSmartAllCheckbox("selectAllTypeMajor", "input[name='typeMajor']");
	setupSmartAllCheckbox("selectAllStatus", "input[name='status']");
	setupSmartAllCheckbox("selectAllAssessment", "input[name='assessment']");
	setupSmartAllCheckbox("selectAllCredit", "input[name='credit']");

});
</script>

<script>
// 🔔 Toast messages (centered)
function showToast(message, type = "success") {
	const toast = document.createElement("div");
    toast.innerText = message;
    toast.style.position = "fixed";
    toast.style.top = "30px";
    toast.style.left = "50%";
    toast.style.transform = "translateX(-50%)";
    toast.style.padding = "15px 30px";
    toast.style.fontSize = "18px";
    toast.style.fontWeight = "bold";
    toast.style.color = "#fff";
    toast.style.borderRadius = "8px";
    toast.style.boxShadow = "0 3px 8px rgba(0,0,0,0.3)";
    toast.style.backgroundColor = type === "success" ? "#28a745" : "#dc3545";
    toast.style.opacity = "0";
    toast.style.transition = "opacity 0.4s ease";
    document.body.appendChild(toast);
    setTimeout(() => toast.style.opacity = "1", 50);
    setTimeout(() => { toast.style.opacity = "0"; setTimeout(() => toast.remove(), 400); }, 2500);
}

// 📝 Edit text
function editText(cell, column) {
    // Prevent re-creating input if already editing
    if (cell.querySelector('input')) return;
    const prev = cell.innerText.trim();
    const input = document.createElement("input");
    input.type = "text";
    input.value = prev;
    input.style.width = "100%";
    input.style.textAlign = "center";
    cell.innerHTML = "";
    cell.appendChild(input);
    input.focus();

    input.onblur = () => {
        const value = input.value.trim();

        // Block empty value except for 'tips'
        if (value === "" && column !== "tips") {
            showToast("⚠️ Field cannot be left blank.", "error");
            cell.innerText = prev; // revert immediately
            return;
        }
		
		// Client-side validation: example course_code format
		if (column === "course_name") {
		// Allow only letters, numbers, spaces; disallow special chars
			if (!/^[A-Za-z0-9 ]+$/.test(value)) {
				showToast("❌ Invalid characters in Course Name. Only letters, numbers, and spaces allowed.", "error");
				cell.innerText = prev; // revert immediately
			return;
			}
		}

        // Client-side validation: example course_code format
        if (column === "course_code" && !/^[A-Z]{3}\d{4}[A-Z]$/.test(value)) {
            showToast("❌ Invalid Code format (AAA0000A).", "error");
            cell.innerText = prev; // revert immediately
            return;
        }

        // Credit hour validation single digit 1-9
        if (column === "credit_hour" && !/^[1-9]$/.test(value)) {
            showToast("❌ Credit Hour must be a single digit 1-9.", "error");
            cell.innerText = prev; // revert immediately
            return;
        }

        // Send AJAX and only update cell on success response
        updateCell(cell, column, value, prev);
    };
}

// 🧭 Dropdown select
function editSelect(cell, options) {
	const prev = cell.innerText.trim();
    const select = document.createElement("select");
    select.style.padding = "5px";
    select.style.textAlign = "center";
    options.forEach(opt => {
        const o = document.createElement("option");
        o.value = o.text = opt;
        if (opt === prev) o.selected = true;
        select.appendChild(o);
    });
    cell.innerHTML = "";
    cell.appendChild(select);
    select.focus();
    select.onblur = () => updateCell(cell, cell.getAttribute("data-column"), select.value);
}

// ✅ AJAX update
function updateCell(cell, column, value, prev) {
    const id = cell.parentElement.getAttribute("data-id");
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        if (this.responseText.includes("updated")) {
            cell.innerText = value;  // update visible text
            showToast("✅ Changes saved!");
        } else {
            cell.innerText = prev;  // revert visible text on error
            showToast("⚠️ " + this.responseText, "error");
        }
    };
    xhr.send("action=update&id=" + id + "&column=" + column + "&value=" + encodeURIComponent(value));
}

// ➕ Add Course
function addCourse() {
    const xhr = new XMLHttpRequest();
	xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        const response = this.responseText.trim();
        if (response.includes("added")) {
            showToast("✅ New course added!");
            setTimeout(() => location.reload(), 1000);
        } else {
            // 🟡 Show the actual message returned by PHP instead of generic text
            showToast(response, "error");
        }
    };
    xhr.send("action=add");
}

// 🗑️ Delete course
function deleteRow(id) {
    if (!confirm("Are you sure to delete this record?")) return;
    const xhr = new XMLHttpRequest();
	xhr.open("POST", "", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = function() {
        showToast("🗑️ Deleted!");
        setTimeout(() => location.reload(), 1000);
    };
    xhr.send("action=delete&id=" + id);
}

// 🔁 Listen for any filter changes // 🔍 Search + Filter
document.getElementById("searchName").addEventListener("input", filterTable);
document.getElementById("searchCode").addEventListener("input", filterTable);

document.querySelectorAll(
    "#searchName, #searchCode, #filterYear, input[name='typeMajor'], input[name='status'], input[name='prereq'], input[name='assessment'], input[name='credit']"
).forEach(el => el.addEventListener("input", filterTable));

// ✅ “Select All” shortcut for Type/Major (controls all filters)
document.getElementById("selectAllTypeMajor").addEventListener("change", e => {
	document.querySelectorAll("input[name='typeMajor']").forEach(cb => cb.checked = e.target.checked);
	filterTable();
});

// ✅ Special logic for "Major: All"
document.querySelector("input[value='Major:All']").addEventListener("change", e => {
	const majors = ["Major:BA", "Major:CC", "Major:NS", "Major:MC", "Major:SE", "Major:G"];
	majors.forEach(val => {
		const box = document.querySelector(`input[value='${val}']`);
		if (box) box.checked = e.target.checked; // ✅ Tick all major checkboxes
	});
	filterTable();
});

// ✅ When user clicks any individual major, uncheck “Major: All”
document.querySelectorAll("input[name='typeMajor']").forEach(cb => {
	cb.addEventListener("change", e => {
		if (["Major:BA", "Major:CC", "Major:MC", "Major:SE", "Major:G"].includes(cb.value)) {
			const allMajor = document.querySelector("input[value='Major:All']");
			if (allMajor && allMajor.checked) allMajor.checked = false; // ✅ Uncheck Major: All
		}
		filterTable();
	});
});

// ✅ Other "Select All" options
document.getElementById("selectAllStatus")?.addEventListener("change", e => {
	document.querySelectorAll("input[name='status']").forEach(cb => cb.checked = e.target.checked);
	filterTable();
});
document.getElementById("selectAllAssessment")?.addEventListener("change", e => {
	document.querySelectorAll("input[name='assessment']").forEach(cb => cb.checked = e.target.checked);
	filterTable();
});
document.getElementById("selectAllCredit")?.addEventListener("change", e => {
	document.querySelectorAll("input[name='credit']").forEach(cb => cb.checked = e.target.checked);
	filterTable();
});

// 🔍 Main filtering logic
function filterTable() {
	const searchName = document.getElementById("searchName").value.toLowerCase();
	const searchCode = document.getElementById("searchCode").value.toUpperCase();
	const year = document.getElementById("filterYear").value;
	const selectedTypeMajors = Array.from(document.querySelectorAll("input[name='typeMajor']:checked")).map(cb => cb.value);
	const selectedStatuses = Array.from(document.querySelectorAll("input[name='status']:checked")).map(cb => cb.value);
	const prereq = document.querySelector("input[name='prereq']:checked")?.value || "";
	const selectedAssessments = Array.from(document.querySelectorAll("input[name='assessment']:checked")).map(cb => cb.value);
	const selectedCredits = Array.from(document.querySelectorAll("input[name='credit']:checked")).map(cb => cb.value);

	document.querySelectorAll("#courseTable tr[data-id]").forEach(row => {
		const course = row.children[1].innerText.toLowerCase();
		const code = row.children[2].innerText.trim().toUpperCase();
		const status = row.children[3].innerText.trim();
		const type = row.children[4].innerText.trim();
		const y = row.children[5].innerText.trim();
		const credit = row.children[6].innerText.trim();
		const prereqVal = row.children[7].innerText.trim();
		const assess = row.children[8].innerText.trim();

		let show = true;

		// 🔎 Basic filters
		if (searchName && !course.includes(searchName)) show = false;
		if (searchCode && !code.includes(searchCode)) show = false;
		if (year && y !== year) show = false;

		// ✅ Status filter
		if (selectedStatuses.length && !selectedStatuses.includes(status)) show = false;

		// ✅ Prerequisite
		if (prereq === "yes" && prereqVal === "-") show = false;
		if (prereq === "no" && prereqVal !== "-") show = false;

		// ✅ Assessment list
		if (selectedAssessments.length && !selectedAssessments.includes(assess)) show = false;

		// ✅ Credit hour
		if (selectedCredits.length && !selectedCredits.includes(credit)) show = false;

		// ✅ Type + Major unified logic
		if (selectedTypeMajors.length) {
			show = show && matchTypeMajor(selectedTypeMajors, type, code);
		}
		
		row.style.display = show ? "" : "none";
	});
    sortTableByTypeAndStatus();
}

function sortTableByTypeAndStatus() {
	const table = document.getElementById("courseTable");
	const rows = Array.from(table.querySelectorAll("tr[data-id]")).filter(r => r.style.display !== "none");

	const typeOrder = { "Core": 1, "Major": 2, "MPU": 3 };
	const statusOrder = { "In Progress": 1, "Completed": 2, "CT": 3, "CE": 4, "CW": 5, "-": 6, "R": 7 };

	rows.sort((a, b) => {
		const typeA = a.children[4].innerText.trim();
		const typeB = b.children[4].innerText.trim();
		const statusA = a.children[3].innerText.trim();
		const statusB = b.children[3].innerText.trim();
		const codeA = a.children[2].innerText.trim();
		const codeB = b.children[2].innerText.trim();

		// 🧩 Sort by Type → Status → Code
		const tA = typeOrder[typeA] || 99;
		const tB = typeOrder[typeB] || 99;
		if (tA !== tB) return tA - tB;

		const sA = statusOrder[statusA] || 99;
		const sB = statusOrder[statusB] || 99;
		if (sA !== sB) return sA - sB;

		return codeA.localeCompare(codeB);
	});
  rows.forEach(r => table.appendChild(r));
}

// 🧠 Mapping for major course codes
function matchTypeMajor(selected, type, code) {
	const majors = {
		"Major:BA": ["COURSE301","COURSE302","COURSE403","COURSE404","COURSE405"],
		"Major:CC": ["COURSE306","COURSE307","COURSE408","COURSE409","COURSE410"],
		"Major:NS": ["COURSE311","COURSE312","COURSE413","COURSE414","COURSE415"],
		"Major:MC": ["COURSE316","COURSE317","COURSE418","COURSE419","COURSE420"],
		"Major:SE": ["COURSE321","COURSE322","COURSE423","COURSE424","COURSE425"],
		"Major:G":  ["COURSE321","COURSE322","COURSE423","COURSE409","COURSE414"]
	};

	// ✅ If “All” is selected → show all courses
	if (selected.includes("All")) return true;

	// ✅ Core or MPU always included if checked
	if (selected.includes(type)) return true;

	// ✅ Major:All → any of the major codes allowed
	if (selected.includes("Major:All")) {
		return Object.values(majors).some(list => list.some(c => code.startsWith(c.substring(0,7))));
	}

	// ✅ Check specific majors
	for (const key of selected) {
		if (key.startsWith("Major:") && majors[key]) {
			if (majors[key].some(c => code.startsWith(c.substring(0,7)))) return true;
		}
	}
	return false;
}

// 🧹 Reset filters
function clearFilters() {
    // Clear search inputs
    document.getElementById("searchName").value = "";
    document.getElementById("searchCode").value = "";
    document.getElementById("filterYear").value = "";
    
    // Clear all checkboxes and radio buttons
    document.querySelectorAll("input[type='radio'], input[type='checkbox']").forEach(el => el.checked = false);
    
    // Re-apply filters (which will show all rows)
    filterTable();
}

</script>

</body>
</html>
