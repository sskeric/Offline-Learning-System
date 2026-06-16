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

// --- AJAX endpoints ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // Helper: return and exit
    function respond($msg) { echo $msg; exit; }

    // Find smallest missing positive integer for groups.id
    function next_group_id($conn) {
        $res = $conn->query("SELECT id FROM groups ORDER BY id ASC");
        $ids = [];
        while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
        $n = 1;
        foreach ($ids as $id) {
            if ($id == $n) $n++;
            elseif ($id > $n) break;
        }
        return $n;
    }

    if ($action === 'create_group_auto') {
		// Enhanced validation
		if (!isset($_POST['max_students']) || $_POST['max_students'] === '') {
			respond("❌ Field cannot be blank.");
		}
    
		$max_students_input = $_POST['max_students'];
    
		// Check if it's numeric
		if (!is_numeric($max_students_input)) {
			respond("❌ Only numbers are allowed.");
		}
    
		$max_students = intval($max_students_input);
    
		// Check range
		if ($max_students < 2) {
			respond("❌ Max students must be at least 2.");
		}
    
		// Check if it's a reasonable maximum (optional)
		if ($max_students > 10) {
			respond("❌ Maximum students cannot exceed more than 10.");
		}
    
		// find next free id <=9
		$res = $conn->query("SELECT id FROM groups ORDER BY id ASC");
		$ids = [];
		while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
		$n = 1;
		while (in_array($n, $ids) && $n <= 30) $n++;
		if ($n > 30) respond("❌ No available group numbers (max 30).");
		$group_name = "Group " . $n;
		$ins = $conn->prepare("INSERT INTO groups (id, group_name, max_students) VALUES (?, ?, ?)");
		$ins->bind_param("isi", $n, $group_name, $max_students);
		if ($ins->execute()) respond("✅ Group created: $group_name (max $max_students)");
		else respond("❌ Failed to create group: " . $conn->error);
	}
	
	if ($action === 'delete_all_groups') {
		// Check if there are any groups
		$countGroups = $conn->query("SELECT COUNT(*) AS c FROM groups")->fetch_assoc()['c'];
		if ($countGroups == 0) {
			respond("❌ No group list found to delete.");
		}

		// Unassign all students first
		$conn->query("UPDATE students SET group_id = NULL");

		// Delete all groups
		if ($conn->query("DELETE FROM groups")) {
			respond("✅ All groups deleted and students unassigned.");
		} else {
			respond("❌ Failed to delete all groups: " . $conn->error);
		}
	}

    if ($action === 'delete_group') {
        $gid = intval($_POST['group_id']);
        // unassign students
        $conn->query("UPDATE students SET group_id = NULL WHERE group_id = $gid");
        if ($conn->query("DELETE FROM groups WHERE id = $gid")) respond("✅ Group deleted and students unassigned.");
        else respond("❌ Failed to delete group: " . $conn->error);
    }

    if ($action === 'edit_max') {
		$gid = intval($_POST['group_id']);
    
		if (!isset($_POST['max_students']) || $_POST['max_students'] === '') {
			respond("❌ Field cannot be blank.");
		}
    
		$max_students_input = $_POST['max_students'];
    
		if (!is_numeric($max_students_input)) {
			respond("❌ Only numbers are allowed.");
		}
    
		$newMax = intval($max_students_input);
    
		if ($newMax < 2) {
			respond("❌ Max must be at least 2.");
		}
    
		if ($newMax > 10) {
			respond("❌ Maximum students cannot exceed more than 10.");
		}
    
		// count current members
		$count = $conn->query("SELECT COUNT(*) as c FROM students WHERE group_id = $gid")->fetch_assoc()['c'];
		if ($newMax < $count) respond("❌ Cannot reduce max below current members ($count). Remove any members first.");
		$stmt = $conn->prepare("UPDATE groups SET max_students = ? WHERE id = ?");
		$stmt->bind_param("ii", $newMax, $gid);
		if ($stmt->execute()) respond("✅ Max updated.");
		else respond("❌ Failed to update: " . $stmt->error);
	}

	if ($action === 'assign_student') {
		$input_id = trim($_POST['student_id']); // can be numeric or i########
		$gid = intval($_POST['group_id']);

		// 🟢 Detect which type we got
		if (preg_match('/^i\d{8}$/', $input_id)) {
			// It's a real student_id like i00000000
			$stmt = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
			$stmt->bind_param("s", $input_id);
			$stmt->execute();
			$result = $stmt->get_result();
			if ($result->num_rows === 0) respond("❌ Student ID not found: $input_id");
			$sid = intval($result->fetch_assoc()['id']);
		} elseif (is_numeric($input_id)) {
			// It's an internal numeric ID
			$sid = intval($input_id);
		} else {
			respond("❌ Invalid student ID format.");
		}

		// ✅ Fetch current group first
		$stmt = $conn->prepare("SELECT group_id FROM students WHERE id = ?");
		$stmt->bind_param("i", $sid);
		$stmt->execute();
		$result = $stmt->get_result();
		$current_group = $result->fetch_assoc()['group_id'] ?? null;

		// ✅ Case 1: Unassign (group_id = 0)
		if ($gid === 0) {
			if ($current_group === null) {
				respond("ℹ️ Student is not currently in any group.");
			}
			$stmt = $conn->prepare("UPDATE students SET group_id = NULL WHERE id = ?");
			$stmt->bind_param("i", $sid);
			$stmt->execute();
			respond("✅ Student removed from group.");
		}

		// ✅ Case 2: Student already in the same group
		if ($current_group == $gid) {
			respond("ℹ️ Student is already in this group.");
		}

		// ✅ Case 3: Student already in another group
		if ($current_group !== null && $current_group != $gid) {
			respond("❌ Student already assigned to another group.");
		}

		// ✅ Check group exists
		$g = $conn->query("SELECT max_students FROM groups WHERE id = $gid")->fetch_assoc();
		if (!$g) respond("❌ Group not found.");
		$max = intval($g['max_students']);

		// ✅ Check capacity
		$count = intval($conn->query("SELECT COUNT(*) AS c FROM students WHERE group_id = $gid")->fetch_assoc()['c']);
		if ($count >= $max) respond("❌ Group is full (limit $max).");

		// ✅ Assign student
		$stmt = $conn->prepare("UPDATE students SET group_id = ? WHERE id = ?");
		$stmt->bind_param("ii", $gid, $sid);
		if ($stmt->execute()) {
			respond("✅ Student assigned successfully.");
		} else {
			respond("❌ Failed to assign: " . $stmt->error);
		}
	}

    if ($action === 'remove_student') {
        $sid = intval($_POST['student_id']);
        $stmt = $conn->prepare("UPDATE students SET group_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $sid);
        if ($stmt->execute()) respond("✅ Student removed from group.");
        else respond("❌ Failed to remove: " . $stmt->error);
    }
	
	if ($action === 'update_note') {
		$sid = intval($_POST['student_id']);
		$note = trim($_POST['note']);
		$stmt = $conn->prepare("UPDATE students SET notes = ? WHERE id = ?");
		$stmt->bind_param("si", $note, $sid);
		if ($stmt->execute()) respond("✅ Note updated successfully.");
		else respond("❌ Failed to update note.");
	}
    // fallback
    respond("❌ Unknown action.");
}

// --- Page display: fetch groups and students ---
$groups = $conn->query("SELECT * FROM groups ORDER BY id ASC");
$students = $conn->query("SELECT id, name, student_id, phone, email, group_id, notes FROM students ORDER BY name ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Group Management - Offline ODL Canvas</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
	body { font-family: Arial, sans-serif; background:#f7f9fb; margin:20px; color:#222; }
	h1 { color:#007bff; text-align:center; }
	.controls { display:flex; gap:12px; justify-content:center; margin-bottom:10px; flex-wrap:wrap; }
	.btn { padding:10px 14px; border-radius:7px; border:none; cursor:pointer; font-weight:600; }
	.btn-green { background:#28a745; color:white; }
	.btn-red { background:#dc3545; color:white; }
	.btn-blue { background:#007bff; color:white; }
	.btn-orange {background: #FFA500; color:white; }
	table { width: 100%; border-collapse:collapse; background:white; box-shadow:0 2px 8px rgba(0,0,0,0.06); margin-bottom:20px; }
	th, td { border:1px solid #e6ecf1; padding:8px 10px; text-align:left; vertical-align:top; }
	th { background:#f1f6fb; color:#333; font-weight:700; }
	.small { font-size:13px; color:#555; }
	.student-chip { display:inline-block; padding:4px 7px; margin:3px; background:#eef6ff; border-radius:5px; font-size:13px; }
	.student-chip button { margin-left:8px; border:none; background:transparent; cursor:pointer; color:#c00; font-weight:700; }
	.center { text-align:center; }
	.inline-input { width:80px; padding:6px; text-align:center; }
	.select-group { padding:6px; }
	.msg { position:fixed; top:30px; left:50%; transform:translateX(-50%); padding:12px 20px; border-radius:8px; color:#fff; font-weight:700; z-index:9999; display:none; }
	.msg.success { background:#28a745; }
	.msg.error { background:#dc3545; }
	.flex-row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; justify-content:center; margin-bottom:12px; }
	@media (max-width:900px) { th, td { font-size:13px; } .inline-input { width:60px; } }

</style>
</head>

<body>

<h1>Group Management (Offline ODL)</h1>

<br>

<h2 style="font-size:25px;color:#000000;margin-top:10px; text-align:center;">Group List</h2>
<h3 style="text-align:center;"> If you want to change any group you need to leave other group first before you can join in</h3>

<div class="controls">
  <button class="btn btn-green" onclick="createGroupAuto()">➕ Create Group </button>
  <button class="btn btn-orange" onclick="location.href='student_info.php'">👥 Go to Student Info</button>
  <button class="btn btn-red" onclick="deleteAllGroups()">🗑️ Delete All Groups</button>
</div>

<br>

<!-- Groups table -->
<table>
<tr>
	<th style="width:20px; text-align:center;">ID</th>
	<th style="width:100px; text-align:center;">Group Name</th>
	<th style="width:110px; text-align:center;">Max Students</th>
	<th style="width:150px; text-align:center;">Created At</th>
	<th style="text-align:center;">Students</th>
	<th style="width:100px" class="center">Action</th>
</tr>

<?php while ($g = $groups->fetch_assoc()): 
	$gid = (int)$g['id'];
    $studentsIn = $conn->query("SELECT id,name,student_id FROM students WHERE group_id = $gid ORDER BY name ASC");
    $countIn = $studentsIn->num_rows;
?>

<tr id="group-row-<?= $gid ?>">
    <td class="center"><?= $gid ?></td>
    <td><?= htmlspecialchars($g['group_name']) ?></td>
    <td class="center">
      <input class="inline-input" type="number" min="1" max="30" id="max-<?= $gid ?>" value="<?= $g['max_students'] ?>"
             onblur="updateMax(<?= $gid ?>)" />
      <div class="small">members: <?= $countIn ?></div>
    </td>
    <td><?= $g['created_at'] ?></td>
    <td>
      <?php if ($studentsIn->num_rows): 
          while ($s = $studentsIn->fetch_assoc()): ?>
            <span class="student-chip"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)
              <button title="Remove" onclick="removeStudent(<?= $s['id'] ?>)">✖</button>
            </span>
      <?php endwhile;
          else: ?>
            <span class="small"><i>No students assigned</i></span>
      <?php endif; ?>
    </td>
    <td class="center">
      <button class="btn btn-red" onclick="deleteGroup(<?= $gid ?>)">🗑️ Delete</button>
	  <br><br>
      <button class="btn" onclick="promptAssign(<?= $gid ?>)">➕ Assign</button>
	  <br><br>
    </td>
</tr>
<?php endwhile; ?>
</table>

<div style="text-align:center; margin-top:30px;">
  <button 
    onclick="saveAsPDF()" 
    style="
      background:#f4fa3e;
      color:black;
      border:none;
      padding:14px 28px;
      border-radius:10px;
      font-size:20px;
      cursor:pointer;
      transition:all 0.3s ease;
    "
    onmouseover="this.style.background='#faf13e'; this.style.transform='scale(1.05)'"
    onmouseout="this.style.background='#f4fa3e'; this.style.transform='scale(1)'">
    📄 Save as PDF (Group List)
  </button>
</div>
<h3 style="text-align:center;">Save your PDF file (rename based on your "student-id_group_course") then email to your class lecturer </h3>

<br><br>

<!-- Student list for individual assignment -->
<h2 style="font-size:25px;color:#000000;margin-top:10px; text-align:center;">Student List</h2>
<h3 style="text-align:center;">Double click on a notes field to edit. Click outside to notes field to save </h3>
<table>

<br>

<tr>
	<th style="text-align:center;">ID</th>
	<th style="text-align:center;">Name</th>
	<th style="text-align:center;">Student ID</th>
	<th style="text-align:center;">Phone</th>
	<th style="text-align:center;">Email</th>
	<th style="text-align:center;">Group</th>
	<th style="text-align:center;">Notes</th>
</tr>
<?php $i = 1; 
while ($s = $students->fetch_assoc()): ?>

<tr>
	<td style="width: 20px;"><?= $i++ ?></td>
    <td style="width: 400px;"><?= htmlspecialchars($s['name']) ?></td>
	<td style="width: 80px;"><?= htmlspecialchars($s['student_id']) ?></td>
    <td style="width: 140px;"><?= htmlspecialchars($s['phone']) ?></td>
    <td style="width: 200px;"><?= htmlspecialchars($s['email']) ?></td>
    <td style="width: 60px;">
      <select class="select-group" onchange="assignStudent(<?= $s['id'] ?>, this.value)">
        <option value="">None</option>
        <?php
          // re-fetch groups to fill dropdown (ordered)
          $gR = $conn->query("SELECT id, group_name, max_students FROM groups ORDER BY id ASC");
          while ($gr = $gR->fetch_assoc()) {
            $gid = $gr['id'];
            // compute current count
            $c = intval($conn->query("SELECT COUNT(*) as c FROM students WHERE group_id = $gid")->fetch_assoc()['c']);
            $disabled = ($c >= $gr['max_students']) ? " data-full='1' " : "";
            $selected = ($s['group_id'] == $gid) ? "selected" : "";
            echo "<option value='{$gid}' {$selected} {$disabled}>{$gr['group_name']} (".($gr['max_students']-$c)." slots)</option>";
          }
        ?>
      </select>
    </td>
<td ondblclick="editText(this, <?= $s['id'] ?>)"><?= htmlspecialchars($s['notes']) ?></td>
</tr>
<?php endwhile; ?>
</table>

<!-- Centered Back Button -->
<div style="display: flex; justify-content: center; margin-top: 30px;">
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
    "
    onmouseover="this.style.background='#0056b3'; this.style.transform='scale(1.1)'"
    onmouseout="this.style.background='#007bff'; this.style.transform='scale(1)'">⬅️ Back
  </button>
</div>
<div id="msg" class="msg"></div>

<script>
// helper show message
function showMsg(text, type = 'success') {
  const el = document.getElementById('msg');
  if (!el) return;
  
  el.textContent = text;
  el.className = 'msg ' + (type === 'success' ? 'success' : 'error');
  el.style.display = 'block';
  
  setTimeout(() => {
    el.style.display = 'none';
  }, 2200);
}

// Create group auto (smallest missing id reused)
function createGroupAuto() {
    function askAgain() {
        const input = prompt("Enter maximum students per group (min = 2), (max = 10):", "3");
        
        // User clicked cancel
        if (input === null) return null;
        
        // User entered empty string
        if (input.trim() === "") {
            alert("❌ Field cannot be blank. Please enter a number.");
            return askAgain();
        }
        
        // Check if input contains non-numeric characters
        if (!/^\d+$/.test(input)) {
            alert("❌ Only numbers are allowed. Please enter a numeric value.");
            return askAgain();
        }
        
        const num = parseInt(input);
        
        // Check if number is valid and meets minimum requirement
        if (isNaN(num) || num < 2) {
            alert("❌ Invalid number. Please enter a value of 2 or higher.");
            return askAgain();
        }
        
		if (num > 10) {
            alert("❌ Maximum students cannot exceed more than 10.");
            return askAgain();
        }
		
        return num;
    }

    const max = askAgain();
    if (max === null) return; // user canceled

    const fd = new FormData();
    fd.append('action', 'create_group_auto');
    fd.append('max_students', max);

    fetch('', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(t => {
        if (t.includes('❌')) {
            showMsg(t, 'error');
        } else {
            showMsg(t, 'success');
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(err => {
        showMsg('❌ Network error occurred', 'error');
    });
}

// Delete ALL groups and unassign all students
function deleteAllGroups() {
  // 🧠 Check if there are any groups in the table first
  const groupRows = document.querySelectorAll("table tr[id^='group-row-']");
  if (groupRows.length === 0) {
    alert("❌ No group list found to delete.");
    return;
  }

  if (!confirm("⚠️ This will delete ALL groups and unassign ALL students.\n\nAre you sure you want to continue?")) return;
  
  const fd = new FormData();
  fd.append('action', 'delete_all_groups');

  fetch('', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(t => {
      showMsg(t, t.includes('✅') ? 'success' : 'error');
      setTimeout(() => location.reload(), 1000);
    })
    .catch(() => showMsg('❌ Network error occurred', 'error'));
}

// Delete group quick
function deleteGroup(id) {
	if (!confirm("Delete this group and unassign its students?")) return;
	const fd = new FormData();
	fd.append('action','delete_group');
	fd.append('group_id', id);
	fetch('', {method:'POST', body:fd}).then(r=>r.text()).then(t=>{ showMsg(t, t.includes('✅')?'success':'error'); setTimeout(()=>location.reload(),700); });
}

// Update max students inline (onblur)
function updateMax(gid) {
    const el = document.getElementById('max-' + gid);
    const inputValue = el.value.trim();
    
    // Check for empty input
    if (inputValue === "") {
        showMsg('❌ Field cannot be blank', 'error');
        el.value = el.defaultValue; // Reset to original value
        return;
    }
    
    // Check for non-numeric characters
    if (!/^\d+$/.test(inputValue)) {
        showMsg('❌ Only numbers are allowed', 'error');
        el.value = el.defaultValue; // Reset to original value
        return;
    }
    
    const val = parseInt(inputValue);
    
    // Check valid range
    if (isNaN(val) || val < 2) {
        showMsg('❌ Max must be at least 2', 'error');
        el.value = el.defaultValue; // Reset to original value
        return;
    }
    
	// Check maximum range
    if (val > 10) {
        showMsg('❌ Maximum students cannot exceed more than 10', 'error');
        el.value = el.defaultValue; // Reset to original value
        return;
    }
	
    // If value hasn't changed, do nothing
    if (val === parseInt(el.defaultValue)) {
        return;
    }
    
    const fd = new FormData();
    fd.append('action','edit_max');
    fd.append('group_id', gid);
    fd.append('max_students', val);
    
    fetch('', {method:'POST', body:fd})
    .then(r => r.text())
    .then(t => {
        if (t.includes('✅')) {
            showMsg(t, 'success');
            el.defaultValue = val; // Update the default value
            setTimeout(() => location.reload(), 900);
        } else {
            showMsg(t, 'error');
            el.value = el.defaultValue; // Reset to original value on error
        }
    })
    .catch(err => {
        showMsg('❌ Network error occurred', 'error');
        el.value = el.defaultValue; // Reset to original value on error
    });
}

// Assign student via dropdown
function assignStudent(sid, gid) {
	// gid empty -> unassign
	const fd = new FormData();
	fd.append('action','assign_student');
	fd.append('student_id', sid);
	fd.append('group_id', gid ? gid : 0);
	fetch('', {method:'POST', body:fd}).then(r=>r.text()).then(t=>{
		showMsg(t, t.includes('✅')?'success':'error');
		setTimeout(()=>location.reload(),700);
	});
}

// Remove student from group (button)
function removeStudent(sid) {
	if (!confirm("Remove this student from the group?")) return;
	const fd = new FormData();
	fd.append('action','remove_student');
	fd.append('student_id', sid);
	fetch('', {method:'POST', body:fd}).then(r=>r.text()).then(t=>{ showMsg(t, t.includes('✅')?'success':'error'); setTimeout(()=>location.reload(),700); });
}

// Prompt quick assign chooser (choose student to assign to this group)
function promptAssign(gid) {
	function askAgain() {
		let sid = prompt("Enter Student ID (e.g. i00000000) to assign to this group:");
		if (sid === null) return null; // user cancelled
		sid = sid.trim();

		// ❌ 1. Empty
		if (sid === "") {
			alert("❌ Student ID cannot be blank.");
		return askAgain();
		}

		// ❌ 2. Too long / too short
		if (sid.length !== 9) {
			alert("❌ Student ID must be exactly 9 characters (e.g. i00000000).");
		return askAgain();
		}

		// ❌ 3. Wrong format (must start with i + 8 digits)
		if (!/^i\d{8}$/.test(sid)) {
			alert("❌ Invalid ID format. Must start with 'i' followed by 8 digits (e.g. i00000000).");
		return askAgain();
		}
	return sid;
  }

	const sid = askAgain();
	if (!sid) return; // user canceled

	// ✅ Send to backend
	const form = new FormData();
	form.append("action", "assign_student");
	form.append("student_id", sid);
	form.append("group_id", gid);

	fetch("", { method: "POST", body: form })
	.then(r => r.text())
	.then(msg => {
		if (msg.includes("Student ID not found")) {
			alert("ℹ️ Student ID not found");
			promptAssign(gid);
			return;
		}
		else if (msg.includes("ℹ️ Student is already in this group.")) {
			alert("ℹStudent is already in this group..");
			promptAssign(gid); // retry
		} 
		else if (msg.includes("❌ Student already assigned to another group")) {
			alert("❌ Student already assigned to another group");
			promptAssign(gid);
			return;
		} else {
			alert(msg);
			if (msg.includes("✅")) location.reload();
		}
	})
    .catch(() => alert("⚠️ Error assigning student."));
}

function editText(cell, sid) {
	const oldValue = cell.innerText.trim();
	const input = document.createElement('input');
	input.type = 'text';
	input.value = oldValue;
	input.style.width = '95%';
	input.style.padding = '4px';
	input.style.fontSize = '14px';

	cell.innerHTML = '';
	cell.appendChild(input);
	input.focus();

	// Save on blur or Enter key
	function save() {
		const newValue = input.value.trim();
		if (newValue === oldValue) {
			cell.innerText = oldValue;
			return;
		}

    const fd = new FormData();
    fd.append('action', 'update_note');
    fd.append('student_id', sid);
    fd.append('note', newValue);

    fetch('', { method: 'POST', body: fd })
      .then(r => r.text())
      .then(t => {
        cell.innerText = newValue;
        showMsg(t, t.includes('✅') ? 'success' : 'error');
      })
      .catch(() => {
        alert('⚠️ Error updating note.');
        cell.innerText = oldValue;
      });
	}
  input.addEventListener('blur', save);
  input.addEventListener('keydown', e => { if (e.key === 'Enter') input.blur(); });
}

function saveAsPDF() {
  // Tip
  alert("📝 Tip: In the print dialog, click Destination dropdown list then choose 'Save as PDF' to download the file.");

  // Select only the first table (Group List)
  const groupTable = document.querySelector("table");
  if (!groupTable) {
    alert("❌ Group List not found!");
    return;
  }

  // Clone the table to modify safely
  const clonedTable = groupTable.cloneNode(true);

  // --- REMOVE Action column (header + all cells) ---
  const headerCells = clonedTable.querySelectorAll("th");
  let actionColIndex = -1;
  headerCells.forEach((th, index) => {
    if (th.textContent.trim().toLowerCase() === "action") {
      actionColIndex = index;
      th.remove();
    }
  });

  // Remove the "Action" column cells in each row
  if (actionColIndex >= 0) {
    clonedTable.querySelectorAll("tr").forEach(row => {
      const cells = row.querySelectorAll("td, th");
      if (cells[actionColIndex]) cells[actionColIndex].remove();
    });
  }

  // Remove any buttons inside table
  clonedTable.querySelectorAll("button").forEach(btn => btn.remove());

  // Format multi-line student names with small spacing
  clonedTable.querySelectorAll("td").forEach(td => {
    td.innerHTML = td.innerHTML
      .replace(/<br\s*\/?>/gi, "\n")
      .replace(/\n+/g, "\n");
    td.style.whiteSpace = "pre-line";
  });

  // Save original content
  const originalContent = document.body.innerHTML;

  // Replace with only the cleaned Group List content
  document.body.innerHTML = `
    <h1 style="text-align:center;">Group List</h1>
    ${clonedTable.outerHTML}
    <p style="text-align:center;margin-top:10px;">Generated on ${new Date().toLocaleString()}</p>
    <style>
      body { font-family: Arial, sans-serif; margin: 20px; }
      table { width:100%; border-collapse: collapse; margin-top: 20px; }
      th, td { border: 1px solid #ccc; padding: 6px; text-align: left; vertical-align: top; }
      th { background-color: #f2f2f2; }
    </style>
  `;

  // Trigger print (Save as PDF)
  window.print();

  // Restore page after print
  setTimeout(() => {
    document.body.innerHTML = originalContent;
  }, 800);
}

</script>

</body>
</html>
