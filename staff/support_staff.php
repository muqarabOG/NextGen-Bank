<?php
require_once '../includes/db_config.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Staff') {
    header("Location: ../login.html");
    exit;
}

$response = ['status' => 'error', 'message' => ''];

// 1. Update Complaint Status
if (isset($_POST['update_complaint'])) {
    $comp_id = intval($_POST['complaint_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $note = mysqli_real_escape_string($conn, $_POST['note']);
    $staff_id = $_SESSION['user_id'];

    mysqli_begin_transaction($conn);
    try {
        // Update main status
        $update_q = "UPDATE complaints SET status = '$status', resolution_date = " . ($status === 'Resolved' ? 'CURRENT_TIMESTAMP' : 'NULL') . " WHERE complaint_id = $comp_id";
        mysqli_query($conn, $update_q);

        // Add update note
        $note_q = "INSERT INTO complaint_updates (complaint_id, staff_id, description, update_type) 
                   VALUES ($comp_id, $staff_id, '$note', 'Note')";
        mysqli_query($conn, $note_q);

        mysqli_commit($conn);
        $response = ['status' => 'success', 'message' => 'Complaint updated successfully.'];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// Fetch all complaints
$query = "SELECT c.*, u.full_name, cc.category_name 
          FROM complaints c 
          JOIN users u ON c.user_id = u.user_id 
          JOIN complaint_categories cc ON c.category_id = cc.category_id 
          ORDER BY c.submission_date DESC";
$complaints_res = mysqli_query($conn, $query);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGB | Support Terminal - Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #FFD700;
        }

        body {
            background: #050505;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 80% 20%, #1e1b4b 0%, transparent 40%), radial-gradient(circle at 20% 80%, #451a03 0%, transparent 40%);
            z-index: -1;
            filter: blur(100px);
        }

        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.01);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(30px);
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2.5rem;
            backdrop-filter: blur(15px);
            transition: 0.5s;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 20px;
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.3);
            transition: 0.4s;
            margin-bottom: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .nav-link.active {
            color: var(--accent);
            background: rgba(255, 215, 0, 0.08);
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        select,
        textarea {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            padding: 1rem !important;
            border-radius: 1.25rem !important;
        }

        select:focus,
        textarea:focus {
            border-color: var(--accent) !important;
            outline: none;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .status-open {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .status-in_progress {
            background: rgba(255, 215, 0, 0.1);
            color: #FFD700;
        }

        .status-resolved {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 text-3xl font-black italic">NGB<span class="text-yellow-500">.</span></div>
        <nav class="flex-1">
            <a href="accountant.php" class="nav-link"><i class="fa fa-user-shield"></i> Accountant</a>
            <a href="cashier.php" class="nav-link"><i class="fa fa-vault"></i> Cashier</a>
            <a href="support_staff.php" class="nav-link active"><i class="fa fa-headset"></i> Support</a>
        </nav>
        <div class="pt-8 border-t border-white/5">
            <a href="../logout.php" class="nav-link text-red-500/60"><i class="fa fa-power-off"></i> Logout</a>
        </div>
    </aside>

    <main class="flex-1 p-12 overflow-y-auto">
        <header class="mb-12">
            <h2 class="text-5xl font-black italic tracking-tighter uppercase text-white">Support <span
                    class="text-white/20">Terminal</span></h2>
            <p class="text-yellow-500 text-[10px] font-black tracking-[0.4em] uppercase mt-2">Active Handler:
                <?php echo $_SESSION['full_name']; ?>
            </p>
        </header>

        <div class="grid grid-cols-1 gap-6">
            <?php if (mysqli_num_rows($complaints_res) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($complaints_res)): ?>
                    <div
                        class="glass-card p-8 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8 border-l-4 <?php echo $row['status'] == 'Resolved' ? 'border-green-500' : 'border-yellow-500/50'; ?>">
                        <div class="flex-1">
                            <div class="flex items-center gap-4 mb-2">
                                <span class="text-xs font-mono text-white/40 tracking-widest">
                                    <?php echo $row['ticket_number']; ?>
                                </span>
                                <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']; ?>
                                </span>
                            </div>
                            <h3 class="text-xl font-bold mb-2">
                                <?php echo htmlspecialchars($row['title']); ?>
                            </h3>
                            <p class="text-sm text-white/50 mb-4">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </p>
                            <div class="flex gap-6 text-[10px] uppercase font-black text-white/30 tracking-widest">
                                <span><i class="fa fa-user mr-2 text-yellow-500"></i>
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                </span>
                                <span><i class="fa fa-tag mr-2 text-yellow-500"></i>
                                    <?php echo htmlspecialchars($row['category_name']); ?>
                                </span>
                                <span><i class="fa fa-calendar mr-2 text-yellow-500"></i>
                                    <?php echo date("d M Y H:i", strtotime($row['submission_date'])); ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($row['status'] !== 'Resolved'): ?>
                            <div class="w-full lg:w-96 space-y-4">
                                <textarea id="note-<?php echo $row['complaint_id']; ?>"
                                    placeholder="Enter resolution note or update..."
                                    class="w-full text-xs h-24 resize-none"></textarea>
                                <div class="flex gap-2">
                                    <select id="status-<?php echo $row['complaint_id']; ?>" class="flex-1 text-xs">
                                        <option value="Open" <?php echo $row['status'] == 'Open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="In_Progress" <?php echo $row['status'] == 'In_Progress' ? 'selected' : ''; ?>>
                                            In Progress</option>
                                        <option value="Resolved">Resolve Ticket</option>
                                    </select>
                                    <button onclick="updateTicket(<?php echo $row['complaint_id']; ?>)"
                                        class="bg-yellow-500 text-black font-black px-6 rounded-2xl text-[10px] uppercase hover:scale-105 transition">Update</button>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-right">
                                <p class="text-[9px] font-black text-green-500 uppercase tracking-widest mb-1 italic">Resolution
                                    Complete</p>
                                <p class="text-xs text-white/20 italic">
                                    <?php echo date("d M Y", strtotime($row['resolution_date'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="glass-card p-20 text-center">
                    <i class="fa fa-check-circle text-6xl text-white/5 mb-6"></i>
                    <p class="text-white/20 uppercase font-black tracking-[0.2em]">Clear Horizons: No pending tickets</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function updateTicket(id) {
            const status = document.getElementById('status-' + id).value;
            const note = document.getElementById('note-' + id).value;
            if (!note) return alert("Please enter a note for the update.");

            const formData = new FormData();
            formData.append('update_complaint', '1');
            formData.append('complaint_id', id);
            formData.append('status', status);
            formData.append('note', note);

            fetch('support_staff.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
        }
    </script>
</body>

</html>