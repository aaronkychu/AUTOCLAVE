<?php
session_start();
$mysqli = new mysqli("localhost", "root", "3838", "autoclave");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// ---------- GET Requests ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

	// Session Check
    if ($action === 'session_check') {
        if (isset($_SESSION['user_id'])) {
            echo json_encode([
                "logged_in" => true,
				"user_id"   => $_SESSION['user_id'],
                "username" => $_SESSION['username']
            ]);
        } else {
            echo json_encode(["logged_in" => false]);
        }
        exit;
    }
	
	// Fetching autoclaves
	if ($action === 'get_autoclaves') {
		$result = $mysqli->query("SELECT id, name FROM autoclaves ORDER BY id ASC");
		$autoclaves = [];
		while ($row = $result->fetch_assoc()) {
			$autoclaves[] = $row;
		}
		echo json_encode($autoclaves);
		exit;
	}
	
    // Status check in Index.html
    if ($action === 'get_status') {
        $autoclave_id = intval($_GET['autoclave_id']);
        $stmt = $mysqli->prepare("SELECT load_status, start_time, end_time 
                                  FROM autoclave_cycles 
                                  WHERE autoclave_id=? 
                                  ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $autoclave_id);
        $stmt->execute();
        $stmt->bind_result($status, $start_time, $end_time);
        if ($stmt->fetch()) {
            echo json_encode([
                "status" => $status,
                "start_time" => $start_time,
                "end_time" => $end_time
            ]);
        } else {
            echo json_encode(["status" => "Idle"]);
        }
        $stmt->close();
        $mysqli->close();
        exit;
    }

    // Combined report in Report.html
	if ($action === 'get_report') {
		$autoclave_id = intval($_GET['autoclave_id']);
		$start_date = isset($_GET['start_date']) ? $_GET['start_date'] . " 00:00:00" : date('Y-m-d', strtotime('-3 months')) . " 00:00:00";
		$end_date   = isset($_GET['end_date'])   ? $_GET['end_date']   . " 23:59:59" : date('Y-m-d') . " 23:59:59";

		$results = [];

		// Cycles
		$stmt = $mysqli->prepare("SELECT c.id, c.start_time, c.end_time, c.indicator_result, c.error_message, c.result_datetime,
										 u1.username AS started_by, u2.username AS ended_by, u3.username AS result_by
								  FROM autoclave_cycles c
								  LEFT JOIN users u1 ON c.started_by = u1.id
								  LEFT JOIN users u2 ON c.ended_by = u2.id
								  LEFT JOIN users u3 ON c.result_by = u3.id
								  WHERE c.autoclave_id=? 
								  AND (c.start_time BETWEEN ? AND ? OR c.end_time BETWEEN ? AND ?)");
		$stmt->bind_param("issss", $autoclave_id, $start_date, $end_date, $start_date, $end_date);
		$stmt->execute();
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$results[] = [
				"id" => $row['id'], // include primary key
				"type" => "Cycle",
				"start_time" => $row['start_time'],
				"started_by" => $row['started_by'] ?? "",
				"end_time" => $row['end_time'] ?: "Running",
				"ended_by" => $row['ended_by'] ?? "",
				"status" => $row['indicator_result'] ?? "",
				"result_by" => $row['result_by'] ?? "",
				"result_datetime" => $row['result_datetime'] ?? "",
				"notes" => $row['error_message'] ?? ""
			];
		}
		$stmt->close();

		// Biological tests
		$stmt = $mysqli->prepare("SELECT b.id, b.test_datetime, b.result, b.notes, u.username AS performed_by
								  FROM biological_tests b
								  LEFT JOIN users u ON b.performed_by = u.id
								  WHERE b.autoclave_id=? 
								  AND b.test_datetime BETWEEN ? AND ?");
		$stmt->bind_param("iss", $autoclave_id, $start_date, $end_date);
		$stmt->execute();
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$results[] = [
				"id"=> $row['id'],
				"type" => "Biological Test",
				"start_time" => "",
				"started_by" => "",
				"end_time" => $row['test_datetime'],
				"ended_by" => "",
				"status" => $row['result'],
				"result_by" => $row['performed_by'] ?? "",
				"notes" => $row['notes'] ?? ""
			];
		}
		$stmt->close();

		// Sorting
		usort($results, function($a, $b) {
			$aEnd = ($a['end_time'] === "Running") ? "9999-12-31 23:59:59" : $a['end_time'];
			$bEnd = ($b['end_time'] === "Running") ? "9999-12-31 23:59:59" : $b['end_time'];
			return strcmp($bEnd, $aEnd);
		});

		header('Content-Type: application/json');
		echo json_encode($results);
		$mysqli->close();
		exit;
	}

	// Edit item in Report.html
	if ($action === 'get_record') {
		$id = intval($_GET['id']);
		$type = $_GET['type'];

		if ($type === "Cycle") {
			$stmt = $mysqli->prepare("SELECT c.id, c.start_time, c.end_time, c.indicator_result, c.error_message, c.result_datetime,
											 u1.username AS started_by, u2.username AS ended_by, u3.username AS result_by
									  FROM autoclave_cycles c
									  LEFT JOIN users u1 ON c.started_by = u1.id
									  LEFT JOIN users u2 ON c.ended_by = u2.id
									  LEFT JOIN users u3 ON c.result_by = u3.id
									  WHERE c.id=?");
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$res = $stmt->get_result();
			$row = $res->fetch_assoc();
			$result[] = [
				"id" => $row['id'], // include primary key
				"type" => "Cycle",
				"start_time" => $row['start_time'],
				"started_by" => $row['started_by'] ?? "",
				"end_time" => $row['end_time'] ?: "Running",
				"ended_by" => $row['ended_by'] ?? "",
				"status" => $row['indicator_result'] ?? "",
				"result_by" => $row['result_by'] ?? "",
				"result_datetime" => $row['result_datetime'] ?? "",
				"notes" => $row['error_message'] ?? ""
			];
			echo json_encode($result);
			$stmt->close();
			exit;
		}

		if ($type === "Biological Test") {
			$stmt = $mysqli->prepare("SELECT b.id, b.test_datetime, b.result, b.notes, u.username AS performed_by
									  FROM biological_tests b
									  LEFT JOIN users u ON b.performed_by = u.id
									  WHERE b.id=?");
			$stmt->bind_param("i", $id);
			$stmt->execute();
			$res = $stmt->get_result();
			$row = $res->fetch_assoc();
			$result[] = [
				"id"=> $row['id'],
				"type" => "Biological Test",
				"start_time" => "",
				"started_by" => "",
				"end_time" => $row['test_datetime'],
				"ended_by" => "",
				"status" => $row['result'],
				"result_by" => $row['performed_by'] ?? "",
				"notes" => $row['notes'] ?? ""
			];
			echo json_encode($result);
			$stmt->close();
			exit;
		}
	}


}

// ---------- POST Requests ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Registration ---
    if ($action === 'register') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $auth_code = $_POST['auth_code'];

        if ($auth_code !== "AARONCHU") {
            echo "Invalid authorization code.";
            exit;
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $password_hash);
        if ($stmt->execute()) {
			$_SESSION['user_id'] = $stmt->insert_id;
			$_SESSION['username'] = $username;
            echo "Registration successful.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
        exit;
    }

    // --- Login ---
    if ($action === 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $stmt = $mysqli->prepare("SELECT id, password_hash FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id, $hash);
        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            echo "Login successful";
        } else {
            echo "Invalid login.";
        }
        $stmt->close();
        exit;
    }
	
	// --- Logout ---
	if ($action === 'logout') {
		session_destroy();
		header("Location: login.html");
		exit;
	}
	
    // Start cycle
    if ($action === 'start_cycle') {
        $autoclave_id = intval($_POST['autoclave_id']);
		$start_time = date("Y-m-d H:i:s");
		$user_id = $_SESSION['user_id'];
		$username = $_SESSION['username'];
		
        $check = $mysqli->prepare("SELECT id FROM autoclave_cycles WHERE autoclave_id=? AND load_status='Running'");
        $check->bind_param("i", $autoclave_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            echo "Autoclave $autoclave_id already has a running cycle!";
        } else {
			$stmt = $mysqli->prepare("INSERT INTO autoclave_cycles (autoclave_id, start_time, load_status, started_by) VALUES (?, ?, 'Running', ?)");
			$stmt->bind_param("isi", $autoclave_id, $start_time, $user_id);
            $stmt->execute();
			echo "Cycle started at $start_time by user $username";
            $stmt->close();
        }
        $check->close();
    }

    // End cycle
    if ($action === 'end_cycle') {
        $autoclave_id = intval($_POST['autoclave_id']);
        $end_time = date("Y-m-d H:i:s");
		$user_id = $_SESSION['user_id'];
		$username = $_SESSION['username'];
        $stmt = $mysqli->prepare("UPDATE autoclave_cycles SET end_time=?, ended_by=?, load_status='Completed' WHERE autoclave_id=? AND load_status='Running'");
        $stmt->bind_param("sii", $end_time, $user_id, $autoclave_id);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? "Cycle ended at $end_time by user $username for Autoclave $autoclave_id" : "No running cycle found for Autoclave $autoclave_id";
        $stmt->close();
    }

    // Cycle result (Pass/Fail)
    if ($action === 'cycle_result') {
        $autoclave_id = intval($_POST['autoclave_id']);
		$user_id = $_SESSION['user_id'];
		$username = $_SESSION['username'];
        $result = $_POST['result'];
		$result_time = date("Y-m-d H:i:s");
        $error_message = $_POST['error_message'] ?? null;
        $stmt = $mysqli->prepare("UPDATE autoclave_cycles 
                                  SET indicator_result=?, result_by=?, result_datetime=?, error_message=? 
                                  WHERE autoclave_id=? AND load_status='Completed' 
                                  ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("sissi", $result, $user_id, $result_time, $error_message, $autoclave_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "Result ($result) recorded successfully by user $username for Autoclave $autoclave_id";
        } else {
            echo "No completed cycle found for Autoclave $autoclave_id";
        }
        $stmt->close();
    }

	// Save biological test
	if ($action === 'save_test') {
		$autoclave_id = intval($_POST['autoclave_id']); // pass from frontend
		$test_datetime = $_POST['test_datetime'];
		$result = $_POST['result'];
		$notes = $_POST['notes'];
		$user_id = $_SESSION['user_id'];
		$username = $_SESSION['username'];
		$stmt = $mysqli->prepare("INSERT INTO biological_tests (autoclave_id, test_datetime, result, notes, performed_by) VALUES (?, ?, ?, ?, ?)");
		$stmt->bind_param("isssi", $autoclave_id, $test_datetime, $result, $notes, $user_id);
		$stmt->execute();
		echo "Biological test saved for Autoclave $autoclave_id at $test_datetime by user $username";
		$stmt->close();
	}

	// Update Result in Report.html
	if ($action === 'update_record') {
		$id = intval($_POST['id']);
		$status = $_POST['status'] ?? "";
		$notes = $_POST['notes'] ?? "";
		$user_id = intval($_POST['user_id']);
		$type = $_POST['type'] ?? "Cycle"; 
		$current_time = date("Y-m-d H:i:s");

		if ($type === "Cycle") {
			$stmt = $mysqli->prepare("UPDATE autoclave_cycles 
									  SET indicator_result=?, error_message=?, result_by=?, updated_at=? 
									  WHERE id=?");
			$stmt->bind_param("ssisi", $status, $notes, $user_id, $current_time, $id);
		} else {
			$stmt = $mysqli->prepare("UPDATE biological_tests 
									  SET result=?, notes=?, performed_by=?, updated_at=? 
									  WHERE id=?");
			$stmt->bind_param("ssisi", $status, $notes, $user_id, $current_time, $id);
		}
		if ($stmt->execute()) {
			echo "Update successful";
		} else {
			echo "Error: " . $stmt->error;
		}
		$stmt->close();
		exit;
	}

}

$mysqli->close();
?>
