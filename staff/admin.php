<?php
require_once '../includes/db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Staff') {
    // Note: In a real app, check for 'Admin' role in staff table.
    // Assuming admin_salman (user_id 6 in in2.sql) is the admin for this session.
    header("Location: ../login.html");
    exit;
}

// 1. Fetch System Stats
$stats = [
    'total_customers' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE user_type = 'Customer'"))['count'],
    'total_deposits' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(current_balance) as sum FROM accounts"))['sum'],
    'open_complaints' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM complaints WHERE status != 'Resolved'"))['count'],
    'pending_approvals' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM prospective_customers WHERE status = 'Pending'"))['count']
];

// 2. Fetch Recent Transactions
$recent_tx = mysqli_query($conn, "SELECT t.*, u.full_name as initiator 
                                  FROM transactions t 
                                  JOIN users u ON t.initiated_by_user_id = u.user_id 
                                  ORDER BY t.transaction_date DESC LIMIT 10");

// 3. Fetch Staff Members
$staff_list = mysqli_query($conn, "SELECT u.user_id, u.full_name, u.username, s.staff_role, s.department 
                                   FROM users u 
                                   JOIN staff s ON u.user_id = s.user_id");

// 4. Handle Staff Management (Add Staff)
if (isset($_POST['add_staff'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $dept = mysqli_real_escape_string($conn, $_POST['department']);

    mysqli_begin_transaction($conn);
    try {
        $user_q = "INSERT INTO users (full_name, username, password_hash, user_type, cnic, contact_number, email, gender, date_of_birth) 
                   VALUES ('$full_name', '$username', '$password', 'Staff', '0000000000000', '00000000000', '$username@nextgenbank.com', 'Male', '1990-01-01')";
        mysqli_query($conn, $user_q);
        $user_id = mysqli_insert_id($conn);

        $staff_q = "INSERT INTO staff (user_id, staff_role, department, employee_id) 
                    VALUES ($user_id, '$role', '$dept', 'EMP" . rand(100, 999) . "')";
        mysqli_query($conn, $staff_q);

        mysqli_commit($conn);
        $msg = "Staff member $full_name added successfully.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Failed to add staff: " . $e->getMessage();
    }
}

// 5. Handle Account Request Status
if (isset($_POST['update_prospect'])) {
    $prospect_id = intval($_POST['update_prospect']);
    $action = $_POST['action']; // 'approve' or 'reject'
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    $staff_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        mysqli_begin_transaction($conn);
        try {
            $p_query = "SELECT * FROM prospective_customers WHERE prospect_id = $prospect_id";
            $p = mysqli_fetch_assoc(mysqli_query($conn, $p_query));
            if (!$p)
                throw new Exception("Error: Prospective record not found in system.");

            // Find user by normalized CNIC digits
            $cnic = preg_replace('/[^0-9]/', '', $p['cnic']);
            $user_lookup = mysqli_query($conn, "SELECT user_id FROM users WHERE cnic = '$cnic' LIMIT 1");
            $user = mysqli_fetch_assoc($user_lookup);

            if (!$user) {
                throw new Exception("Security Alert: No base user record found for CNIC $cnic. Please re-register or check data integrity.");
            }

            $user_id = $user['user_id'];
            mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE user_id = $user_id");

            $acc_num = "NGB" . str_pad($user_id, 7, "0", STR_PAD_LEFT);
            mysqli_query($conn, "INSERT INTO accounts (user_id, account_number, type_id, current_balance, available_balance, opening_date, status) VALUES ($user_id, '$acc_num', " . $p['requested_account_type_id'] . ", 0, 0, CURDATE(), 'Active')");
            $account_id = mysqli_insert_id($conn);

            // Issue initial card
            $card_num = "4588" . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
            $expiry = date('Y-m-d', strtotime('+4 years'));
            mysqli_query($conn, "INSERT INTO cards (user_id, account_id, card_number, card_type_id, status, expiry_date, cvv_hash, pin_hash, issue_date) VALUES ($user_id, $account_id, '$card_num', 1, 'Active', '$expiry', '123', '1234', CURDATE())");

            mysqli_query($conn, "UPDATE prospective_customers SET status = 'Approved', verified_by_staff_id = $staff_id, verification_date = CURRENT_TIMESTAMP WHERE prospect_id = $prospect_id");

            mysqli_commit($conn);
            $msg = "Success: Account authorized for " . $p['full_name'] . " and Multi-Card issued.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    } else {
        mysqli_query($conn, "UPDATE prospective_customers SET status = 'Rejected', rejection_reason = '$reason', verified_by_staff_id = $staff_id, verification_date = CURRENT_TIMESTAMP WHERE prospect_id = $prospect_id");
        $msg = "Account request rejected.";
    }
}

// 6. Handle Card Request Status
if (isset($_POST['update_card_request'])) {
    $request_id = intval($_POST['update_card_request']);
    $action = $_POST['action'];
    $staff_id = $_SESSION['user_id'];

    if ($action === 'approve') {
        mysqli_begin_transaction($conn);
        try {
            $r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM card_requests WHERE request_id = $request_id"));
            $card_num = "4588" . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
            $expiry = date('Y-m-d', strtotime('+4 years'));
            mysqli_query($conn, "INSERT INTO cards (user_id, account_id, card_number, card_type_id, status, expiry_date, cvv_hash, pin_hash, issue_date) VALUES (" . $r['user_id'] . ", " . $r['account_id'] . ", '$card_num', " . $r['requested_card_type_id'] . ", 'Active', '$expiry', '123', '1234', CURDATE())");
            mysqli_query($conn, "UPDATE card_requests SET status = 'Issued', processed_by_staff_id = $staff_id, process_date = CURRENT_TIMESTAMP WHERE request_id = $request_id");
            mysqli_commit($conn);
            $msg = "Card $card_num issued successfully.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    } else {
        $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Denied');
        mysqli_query($conn, "UPDATE card_requests SET status = 'Rejected', rejection_reason = '$reason', processed_by_staff_id = $staff_id, process_date = CURRENT_TIMESTAMP WHERE request_id = $request_id");
        $msg = "Card request rejected.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGB | Admin Command Center</title>
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
            background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, transparent 50%), radial-gradient(circle at 10% 10%, #451a03 0%, transparent 40%);
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

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        input,
        select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 text-3xl font-black italic">NGB<span class="text-yellow-500">.</span>ADMIN</div>
        <nav class="flex-1">
            <a href="admin.php"
                class="flex items-center gap-4 p-4 text-yellow-500 bg-white/5 rounded-2xl border border-yellow-500/20"><i
                    class="fa fa-chart-pie"></i> Overview</a>
            <a href="accountant.php" class="flex items-center gap-4 p-4 text-white/40 hover:text-white transition"><i
                    class="fa fa-user-shield"></i> Account Requests</a>
            <a href="support_staff.php" class="flex items-center gap-4 p-4 text-white/40 hover:text-white transition"><i
                    class="fa fa-headset"></i> Support Desk</a>
        </nav>
        <div class="pt-8 border-t border-white/5">
            <a href="../logout.php" class="flex items-center gap-4 p-4 text-red-500/60 transition"><i
                    class="fa fa-power-off"></i> Logout</a>
        </div>
    </aside>

    <main class="flex-1 p-10 overflow-y-auto">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-4xl font-black tracking-tighter uppercase italic">System <span
                        class="text-yellow-500">Intelligence</span></h2>
                <p class="text-white/30 text-[9px] font-black tracking-[0.4em] uppercase">Master Operational Dashboard
                </p>
            </div>
            <div class="bg-white/5 px-6 py-3 rounded-2xl border border-white/10">
                <p class="text-[9px] font-black text-green-500 uppercase leading-none mb-1">Status: Operational</p>
                <p class="text-xs font-bold">
                    <?php echo $_SESSION['full_name']; ?>
                </p>
            </div>
        </header>

        <?php if (isset($msg)): ?>
            <div
                class="bg-green-500/10 border border-green-500/20 p-4 rounded-2xl text-green-500 text-sm mb-8 animate-pulse">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-4 gap-6 mb-10">
            <div class="glass-card stat-card p-8">
                <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1">Total Assets</p>
                <h3 class="text-3xl font-black text-yellow-500">Rs.
                    <?php echo number_format($stats['total_deposits'], 0); ?>
                </h3>
            </div>
            <div class="glass-card stat-card p-8">
                <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1">Total Users</p>
                <h3 class="text-3xl font-black italic">
                    <?php echo $stats['total_customers']; ?>
                </h3>
            </div>
            <div class="glass-card stat-card p-8">
                <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1">Active Tickets</p>
                <h3 class="text-3xl font-black text-red-500">
                    <?php echo $stats['open_complaints']; ?>
                </h3>
            </div>
            <div class="glass-card stat-card p-8">
                <p class="text-[9px] font-black text-white/40 uppercase tracking-widest mb-1">Pending KYCs</p>
                <h3 class="text-3xl font-black text-blue-500">
                    <?php echo $stats['pending_approvals']; ?>
                </h3>
            </div>
        </div>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-8 space-y-8">
                <!-- Management Console -->
                <div class="glass-card p-8 mb-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/60 mb-6 font-mono"><i
                            class="fa fa-shuttle-space mr-2 text-yellow-500"></i> Operational Queue</h4>

                    <div class="grid grid-cols-2 gap-8">
                        <div>
                            <p class="text-[9px] font-black text-blue-400 uppercase tracking-widest mb-4">Pending KYC
                                Approvals</p>
                            <div class="space-y-4 max-h-64 overflow-y-auto pr-2 scrollbar-hide">
                                <?php
                                $kycs = mysqli_query($conn, "SELECT * FROM prospective_customers WHERE status = 'Pending' LIMIT 10");
                                if (mysqli_num_rows($kycs) > 0):
                                    while ($k = mysqli_fetch_assoc($kycs)): ?>
                                        <div
                                            class="p-4 bg-white/[0.03] rounded-2xl border border-white/5 flex justify-between items-center group">
                                            <div>
                                                <p class="text-xs font-bold"><?php echo $k['full_name']; ?></p>
                                                <p class="text-[8px] text-white/30 uppercase"><?php echo $k['form_number']; ?>
                                                </p>
                                            </div>
                                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="update_prospect"
                                                        value="<?php echo $k['prospect_id']; ?>">
                                                    <button type="submit" name="action" value="approve"
                                                        class="w-6 h-6 rounded-lg bg-green-500/20 text-green-500 hover:bg-green-500 hover:text-white transition"><i
                                                            class="fa fa-check text-[8px]"></i></button>
                                                </form>
                                                <button onclick="rejectPrompt('prospect', <?php echo $k['prospect_id']; ?>)"
                                                    class="w-6 h-6 rounded-lg bg-red-500/20 text-red-500 hover:bg-red-500 hover:text-white transition"><i
                                                        class="fa fa-times text-[8px]"></i></button>
                                            </div>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <p class="text-[9px] text-white/10 uppercase py-4">Zero Pending KYCs</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <p class="text-[9px] font-black text-yellow-500 uppercase tracking-widest mb-4">Card
                                Issuance Queue</p>
                            <div class="space-y-4 max-h-64 overflow-y-auto pr-2 scrollbar-hide">
                                <?php
                                $cards = mysqli_query($conn, "SELECT cr.*, u.full_name, ct.type_name FROM card_requests cr JOIN users u ON cr.user_id = u.user_id JOIN card_types ct ON cr.requested_card_type_id = ct.card_type_id WHERE cr.status = 'Pending' LIMIT 10");
                                if (mysqli_num_rows($cards) > 0):
                                    while ($c = mysqli_fetch_assoc($cards)): ?>
                                        <div
                                            class="p-4 bg-white/[0.03] rounded-2xl border border-white/5 flex justify-between items-center group">
                                            <div>
                                                <p class="text-xs font-bold"><?php echo $c['full_name']; ?></p>
                                                <p class="text-[8px] text-white/30 uppercase"><?php echo $c['type_name']; ?></p>
                                            </div>
                                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="update_card_request"
                                                        value="<?php echo $c['request_id']; ?>">
                                                    <button type="submit" name="action" value="approve"
                                                        class="w-6 h-6 rounded-lg bg-green-500/20 text-green-500 hover:bg-green-500 hover:text-white transition"><i
                                                            class="fa fa-check text-[8px]"></i></button>
                                                </form>
                                                <button onclick="rejectPrompt('card', <?php echo $c['request_id']; ?>)"
                                                    class="w-6 h-6 rounded-lg bg-red-500/20 text-red-500 hover:bg-red-500 hover:text-white transition"><i
                                                        class="fa fa-times text-[8px]"></i></button>
                                            </div>
                                        </div>
                                    <?php endwhile;
                                else: ?>
                                    <p class="text-[9px] text-white/10 uppercase py-4">No Pending Cards</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/60 mb-6">Recent Transaction Flow
                    </h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-white/20 border-b border-white/5">
                                    <th class="pb-4">Ref</th>
                                    <th class="pb-4">Initiator</th>
                                    <th class="pb-4">Amount</th>
                                    <th class="pb-4">Date</th>
                                    <th class="pb-4 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tx = mysqli_fetch_assoc($recent_tx)): ?>
                                    <tr class="border-b border-white/5 hover:bg-white/5 transition">
                                        <td class="py-4 font-mono text-white/40">
                                            <?php echo $tx['transaction_reference']; ?>
                                        </td>
                                        <td class="py-4 font-bold">
                                            <?php echo $tx['initiator']; ?>
                                        </td>
                                        <td class="py-4 text-yellow-500 font-bold">Rs.
                                            <?php echo number_format($tx['amount'], 2); ?>
                                        </td>
                                        <td class="py-4 text-white/30">
                                            <?php echo date("d M H:i", strtotime($tx['transaction_date'])); ?>
                                        </td>
                                        <td class="py-4 text-right"><span
                                                class="px-2 py-1 bg-green-500/10 text-green-500 rounded-full text-[8px] font-black">SUCCESS</span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-4 space-y-8">
                <div class="glass-card p-8 bg-yellow-500/[0.02] border-yellow-500/10">
                    <h4 class="text-xs font-black uppercase tracking-widest text-yellow-500 mb-6">Add New Staff Member
                    </h4>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="full_name" placeholder="Full Name"
                            class="w-full p-4 rounded-xl outline-none" required>
                        <input type="text" name="username" placeholder="Username"
                            class="w-full p-4 rounded-xl outline-none" required>
                        <input type="password" name="password" placeholder="Temporal Password"
                            class="w-full p-4 rounded-xl outline-none" required>
                        <select name="role" class="w-full p-4 rounded-xl outline-none" required>
                            <option value="Cashier">Cashier</option>
                            <option value="Accountant">Accountant</option>
                            <option value="Complain_Handler">Support Handler</option>
                            <option value="Admin">Admin</option>
                        </select>
                        <select name="department" class="w-full p-4 rounded-xl outline-none" required>
                            <option value="Operations">Operations</option>
                            <option value="Accounts">Accounts</option>
                            <option value="IT">IT Support</option>
                        </select>
                        <button type="submit" name="add_staff"
                            class="w-full bg-yellow-500 text-black font-black py-4 rounded-xl uppercase text-[10px] tracking-widest hover:scale-105 transition">Initialize
                            Account</button>
                    </form>
                </div>

                <div class="glass-card p-8 bg-red-500/[0.02] border-red-500/10 mb-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-red-500 mb-6">High Priority Alerts</h4>
                    <div class="space-y-4">
                        <?php
                        $critical = mysqli_query($conn, "SELECT c.*, u.full_name FROM complaints c JOIN users u ON c.user_id = u.user_id WHERE c.status != 'Resolved' AND c.category_id = 1 LIMIT 5");
                        if ($critical && mysqli_num_rows($critical) > 0):
                            while ($c = mysqli_fetch_assoc($critical)): ?>
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <div>
                                        <p class="text-[10px] font-bold text-red-500 uppercase tracking-tighter">CRITICAL ISSUE
                                        </p>
                                        <h5 class="text-xs font-bold"><?php echo htmlspecialchars($c['title']); ?></h5>
                                        <p class="text-[9px] text-white/30"><?php echo $c['full_name']; ?> |
                                            <?php echo $c['ticket_number']; ?>
                                        </p>
                                    </div>
                                    <a href="support_staff.php" class="text-white/20 hover:text-yellow-500 transition"><i
                                            class="fa fa-external-link text-xs"></i></a>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="text-center text-[10px] text-white/20 uppercase font-black py-4">Status: No Critical
                                Alerts</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="glass-card p-8">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/60 mb-6">Staff Roster</h4>
                    <div class="space-y-4">
                        <?php while ($s = mysqli_fetch_assoc($staff_list)): ?>
                            <div class="flex justify-between items-center p-4 bg-white/5 rounded-2xl">
                                <div>
                                    <p class="text-xs font-bold">
                                        <?php echo $s['full_name']; ?>
                                    </p>
                                    <p class="text-[9px] text-white/30 uppercase tracking-tighter">
                                        <?php echo $s['staff_role']; ?> /
                                        <?php echo $s['department']; ?>
                                    </p>
                                </div>
                                <i class="fa fa-user-gear text-white/20"></i>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        function rejectPrompt(type, id) {
            const reason = prompt("Enter rejection reason:");
            if (reason) {
                const form = document.createElement('form');
                form.method = 'POST';
                const inputId = document.createElement('input');
                inputId.type = 'hidden';
                inputId.name = (type === 'card' ? 'update_card_request' : 'update_prospect');
                inputId.value = id;
                const inputAction = document.createElement('input');
                inputAction.type = 'hidden';
                inputAction.name = 'action';
                inputAction.value = 'reject';
                const inputReason = document.createElement('input');
                inputReason.type = 'hidden';
                inputReason.name = 'reason';
                inputReason.value = reason;

                form.appendChild(inputId);
                form.appendChild(inputAction);
                form.appendChild(inputReason);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>