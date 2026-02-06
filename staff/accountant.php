<?php
require_once '../includes/db_config.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Staff') {
    header("Location: ../login.html");
    exit;
}

// Logic for fetching prospective customer data
if (isset($_POST['fetch_form'])) {
    $form_number = mysqli_real_escape_string($conn, $_POST['fetch_form']);
    $query = "SELECT * FROM prospective_customers WHERE form_number = '$form_number' LIMIT 1";
    $res = mysqli_query($conn, $query);

    if (mysqli_num_rows($res) > 0) {
        $userData = mysqli_fetch_assoc($res);
        // Use placeholders if images are missing in this version
        $front = "https://via.placeholder.com/400x250?text=CNIC+Front";
        $back = "https://via.placeholder.com/400x250?text=CNIC+Back";

        echo json_encode(['status' => 'success', 'front' => $front, 'back' => $back, 'name' => $userData['full_name'], 'cnic' => $userData['cnic'], 'prospect_id' => $userData['prospect_id']]);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Form Number not found']);
        exit;
    }
}

// Logic for approving account
if (isset($_POST['approve_prospect'])) {
    $prospect_id = intval($_POST['approve_prospect']);

    mysqli_begin_transaction($conn);
    try {
        $p_query = "SELECT * FROM prospective_customers WHERE prospect_id = $prospect_id";
        $p_res = mysqli_query($conn, $p_query);
        $p = mysqli_fetch_assoc($p_res);

        if (!$p)
            throw new Exception("Error: Prospective record not found.");

        // Find user by normalized CNIC digits
        $cnic = preg_replace('/[^0-9]/', '', $p['cnic']);
        $user_q = mysqli_query($conn, "SELECT user_id FROM users WHERE cnic = '$cnic' LIMIT 1");
        $user = mysqli_fetch_assoc($user_q);

        if (!$user) {
            throw new Exception("Security Alert: No base user found for CNIC $cnic. Customer must register correctly before approval.");
        }

        $user_id = $user['user_id'];

        // 2. Activate user
        mysqli_query($conn, "UPDATE users SET is_active = 1 WHERE user_id = $user_id");

        // 3. Create account if not exists
        $acc_num = "NGB" . str_pad($user_id, 7, "0", STR_PAD_LEFT);
        mysqli_query($conn, "INSERT INTO accounts (user_id, account_number, type_id, current_balance, available_balance, opening_date, status) 
                            VALUES ($user_id, '$acc_num', " . $p['requested_account_type_id'] . ", 0, 0, CURDATE(), 'Active')");
        $account_id = mysqli_insert_id($conn);

        // 4. Update prospect status
        mysqli_query($conn, "UPDATE prospective_customers SET status = 'Approved', verified_by_staff_id = " . $_SESSION['user_id'] . ", verification_date = CURRENT_TIMESTAMP WHERE prospect_id = $prospect_id");

        // 5. Create default card
        $card_num = "4588" . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
        $expiry = date('Y-m-d', strtotime('+4 years'));
        mysqli_query($conn, "INSERT INTO cards (user_id, account_id, card_number, card_type_id, status, expiry_date, cvv_hash, pin_hash, issue_date) 
                            VALUES ($user_id, $account_id, '$card_num', 1, 'Active', '$expiry', '123', '1234', CURDATE())");

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => "Account $acc_num activated successfully."]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Logic for rejecting account
if (isset($_POST['reject_prospect'])) {
    $prospect_id = intval($_POST['reject_prospect']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Documentation incomplete');

    $q = "UPDATE prospective_customers SET status = 'Rejected', rejection_reason = '$reason', verified_by_staff_id = " . $_SESSION['user_id'] . ", verification_date = CURRENT_TIMESTAMP WHERE prospect_id = $prospect_id";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success', 'message' => 'Prospect rejected.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}

// Logic for card request approval
if (isset($_POST['approve_card'])) {
    $request_id = intval($_POST['approve_card']);

    mysqli_begin_transaction($conn);
    try {
        $r_q = mysqli_query($conn, "SELECT * FROM card_requests WHERE request_id = $request_id");
        $r = mysqli_fetch_assoc($r_q);
        if (!$r)
            throw new Exception("Request not found");

        $card_num = "4588" . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
        $expiry = date('Y-m-d', strtotime('+4 years'));
        $cvv = rand(100, 999);
        $pin = rand(1000, 9999);

        // Create card
        $ins = "INSERT INTO cards (user_id, account_id, card_number, card_type_id, status, expiry_date, cvv_hash, pin_hash, issue_date) 
                VALUES (" . $r['user_id'] . ", " . $r['account_id'] . ", '$card_num', " . $r['requested_card_type_id'] . ", 'Active', '$expiry', '$cvv', '$pin', CURDATE())";
        mysqli_query($conn, $ins);

        // Update request
        mysqli_query($conn, "UPDATE card_requests SET status = 'Issued', processed_by_staff_id = " . $_SESSION['user_id'] . ", process_date = CURRENT_TIMESTAMP WHERE request_id = $request_id");

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'message' => "Card $card_num issued successfully."]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Logic for card request rejection
if (isset($_POST['reject_card'])) {
    $request_id = intval($_POST['reject_card']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Ineligible');

    $q = "UPDATE card_requests SET status = 'Rejected', rejection_reason = '$reason', processed_by_staff_id = " . $_SESSION['user_id'] . ", process_date = CURRENT_TIMESTAMP WHERE request_id = $request_id";
    if (mysqli_query($conn, $q)) {
        echo json_encode(['status' => 'success', 'message' => 'Card request rejected.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGB | Accountant Desk - Live</title>
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

        /* Laser Scan Animation */
        .scan-line {
            width: 100%;
            height: 3px;
            background: var(--accent);
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0.8;
            box-shadow: 0 0 20px var(--accent);
            animation: scan 4s linear infinite;
            z-index: 10;
            display: none;
        }

        @keyframes scan {
            0% {
                top: 0;
            }

            100% {
                top: 100%;
            }
        }

        .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: grayscale(100%) brightness(0.7) contrast(1.2);
            transition: 1s;
        }

        .image-container.scanning img {
            filter: grayscale(0%) brightness(1) sepia(20%);
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 text-3xl font-black italic">NGB<span class="text-yellow-500">.</span></div>
        <nav class="flex-1">
            <a href="accountant.php" class="nav-link active"><i class="fa fa-user-shield"></i> Accountant</a>
            <a href="cashier.php" class="nav-link"><i class="fa fa-vault"></i> Cashier</a>
            <a href="support_staff.php" class="nav-link"><i class="fa fa-headset"></i> Support</a>
        </nav>
        <div class="pt-8 border-t border-white/5">
            <a href="../logout.php" class="nav-link text-red-500/60"><i class="fa fa-power-off"></i> Logout</a>
        </div>
    </aside>

    <main class="flex-1 p-12 overflow-y-auto">
        <header class="flex justify-between items-end mb-12">
            <div>
                <h2 class="text-5xl font-black italic tracking-tighter uppercase text-white">Document <span
                        class="text-white/20">Portal</span></h2>
                <p class="text-yellow-500 text-[10px] font-black tracking-[0.4em] uppercase mt-2">Active Auditor
                    Session:
                    <?php echo $_SESSION['full_name']; ?>
                </p>
            </div>
            <div id="status-name" class="text-right text-sm font-bold text-white/40 italic">Ready for Verification</div>
        </header>

        <div class="grid grid-cols-12 gap-10">
            <div class="col-span-8 glass-card p-10">
                <div class="flex gap-4 mb-12">
                    <input type="text" id="reg_id" placeholder="Enter Form Number (e.g. FORM-2026...)"
                        class="flex-1 bg-white/5 border border-white/10 px-8 py-5 rounded-3xl outline-none focus:border-yellow-500 text-white font-mono">
                    <button onclick="fetchUserData()"
                        class="bg-yellow-500 text-black font-black px-12 rounded-3xl hover:scale-105 transition uppercase text-[11px] tracking-widest">Run
                        Scan</button>
                </div>

                <div class="grid grid-cols-2 gap-8">
                    <div
                        class="image-container relative h-56 bg-black/50 rounded-3xl overflow-hidden border border-white/5 flex items-center justify-center">
                        <div id="front-scan" class="scan-line"></div>
                        <img id="cnic-front-img" src="" style="display:none;">
                        <span id="front-placeholder" class="text-[9px] text-white/20 uppercase font-black">Waiting for
                            Data...</span>
                    </div>
                    <div
                        class="image-container relative h-56 bg-black/50 rounded-3xl overflow-hidden border border-white/5 flex items-center justify-center">
                        <div id="back-scan" class="scan-line"></div>
                        <img id="cnic-back-img" src="" style="display:none;">
                        <span id="back-placeholder" class="text-[9px] text-white/20 uppercase font-black">Waiting for
                            Data...</span>
                    </div>
                </div>
            </div>

            <div class="col-span-4 space-y-8">
                <!-- Account Approval Panel (Appears after scan) -->
                <div id="approvalPanel"
                    class="glass-card p-8 bg-yellow-500/[0.03] border-yellow-500/10 text-center opacity-20 pointer-events-none">
                    <i class="fa fa-fingerprint text-4xl text-yellow-500 mb-4"></i>
                    <h4 class="text-sm font-black uppercase mb-6 tracking-widest">Final Approval</h4>
                    <p id="prospectDisplay" class="text-[10px] text-white/40 mb-6 uppercase">No Subject Selected</p>
                    <button onclick="approveAccount()"
                        class="w-full py-4 bg-white text-black rounded-2xl font-black uppercase text-[10px] hover:bg-yellow-500 transition mb-4">Approve
                        & Issue Card</button>
                    <button onclick="rejectAccount()"
                        class="w-full py-2 text-red-500 font-bold uppercase text-[9px] hover:text-white transition">Reject
                        Document</button>
                </div>

                <!-- Card Request Queue -->
                <div class="glass-card p-8 border-yellow-500/10 bg-white/[0.02]">
                    <h4 class="text-xs font-black uppercase tracking-widest text-yellow-500 mb-6">Pending Card Requests
                    </h4>
                    <div class="space-y-4">
                        <?php
                        $cards = mysqli_query($conn, "SELECT cr.*, u.full_name, ct.type_name FROM card_requests cr JOIN users u ON cr.user_id = u.user_id JOIN card_types ct ON cr.requested_card_type_id = ct.card_type_id WHERE cr.status = 'Pending'");
                        if (mysqli_num_rows($cards) > 0):
                            while ($c = mysqli_fetch_assoc($cards)): ?>
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5 flex justify-between items-center">
                                    <div>
                                        <h5 class="text-xs font-bold"><?php echo $c['full_name']; ?></h5>
                                        <p class="text-[9px] text-white/30 uppercase"><?php echo $c['type_name']; ?> •
                                            <?php echo date('d M Y', strtotime($c['request_date'])); ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button onclick="processCard(<?php echo $c['request_id']; ?>, 'approve')"
                                            class="w-8 h-8 rounded-lg bg-green-500/20 text-green-500 hover:bg-green-500 hover:text-white transition"><i
                                                class="fa fa-check text-[10px]"></i></button>
                                        <button onclick="processCard(<?php echo $c['request_id']; ?>, 'reject')"
                                            class="w-8 h-8 rounded-lg bg-red-500/20 text-red-500 hover:bg-red-500 hover:text-white transition"><i
                                                class="fa fa-times text-[10px]"></i></button>
                                    </div>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <p class="text-[9px] text-white/20 uppercase text-center font-bold py-4 italic">No Pending Card
                                Requests</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function processCard(id, action) {
            if (action === 'reject') {
                const reason = prompt("Enter rejection reason:");
                if (!reason) return;
                const formData = new FormData();
                formData.append('reject_card', id);
                formData.append('reason', reason);
                fetch('accountant.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                    alert(data.message);
                    location.reload();
                });
            } else {
                if (confirm("Issue this card?")) {
                    const formData = new FormData();
                    formData.append('approve_card', id);
                    fetch('accountant.php', { method: 'POST', body: formData }).then(res => res.json()).then(data => {
                        alert(data.message);
                        location.reload();
                    });
                }
            }
        }

        let currentProspectId = null;

        function fetchUserData() {
            const id = document.getElementById('reg_id').value;
            if (!id) return alert("Please enter a Form Number");

            // Show Scanning Effect
            document.querySelectorAll('.scan-line').forEach(el => el.style.display = 'block');
            document.getElementById('status-name').innerText = "Analyzing Secure Database...";

            const formData = new FormData();
            formData.append('fetch_form', id);

            fetch('accountant.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    setTimeout(() => {
                        if (data.status === 'success') {
                            const fImg = document.getElementById('cnic-front-img');
                            const bImg = document.getElementById('cnic-back-img');
                            fImg.src = data.front;
                            bImg.src = data.back;
                            fImg.style.display = 'block';
                            bImg.style.display = 'block';

                            document.getElementById('front-placeholder').style.display = 'none';
                            document.getElementById('back-placeholder').style.display = 'none';
                            document.getElementById('status-name').innerText = "IDENTIFIED: " + data.name;
                            document.getElementById('prospectDisplay').innerText = data.name + " (" + data.cnic + ")";
                            currentProspectId = data.prospect_id;

                            document.getElementById('approvalPanel').classList.remove('opacity-20', 'pointer-events-none');
                            document.querySelectorAll('.image-container').forEach(el => el.classList.add('scanning'));
                        } else {
                            alert(data.message || "User not found!");
                            document.querySelectorAll('.scan-line').forEach(el => el.style.display = 'none');
                        }
                    }, 1500);
                });
        }

        function approveAccount() {
            if (!currentProspectId) return;

            if (confirm("Authorize account activation and card issuance?")) {
                const formData = new FormData();
                formData.append('approve_prospect', currentProspectId);

                fetch('accountant.php', { method: 'POST', body: formData })
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
        }

        function rejectAccount() {
            if (!currentProspectId) return;
            const reason = prompt("Enter rejection reason:");
            if (reason) {
                const formData = new FormData();
                formData.append('reject_prospect', currentProspectId);
                formData.append('reason', reason);

                fetch('accountant.php', { method: 'POST', body: formData })
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
        }
    </script>
</body>

</html>