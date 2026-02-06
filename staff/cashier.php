<?php
require_once '../includes/db_config.php';

// Check if staff is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Staff') {
    header("Location: ../login.html");
    exit;
}

$response = ['status' => 'error', 'message' => ''];

// 1. Fetch Account Details
if (isset($_POST['fetch_account'])) {
    $acc_num = mysqli_real_escape_string($conn, $_POST['fetch_account']);
    $query = "SELECT a.*, u.full_name, u.cnic, t.type_name 
              FROM accounts a 
              JOIN users u ON a.user_id = u.user_id 
              JOIN account_types t ON a.type_id = t.type_id 
              WHERE a.account_number = '$acc_num' LIMIT 1";
    $res = mysqli_query($conn, $query);

    if (mysqli_num_rows($res) > 0) {
        $accData = mysqli_fetch_assoc($res);
        $response = [
            'status' => 'success',
            'name' => $accData['full_name'],
            'cnic' => $accData['cnic'],
            'balance' => number_format($accData['current_balance'], 2),
            'type' => $accData['type_name'],
            'account_id' => $accData['account_id']
        ];
    } else {
        $response['message'] = 'Account not found';
    }
    echo json_encode($response);
    exit;
}

// 2. Process Transaction (Deposit/Withdrawal)
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $acc_id = intval($_POST['account_id']);
    $amount = floatval($_POST['amount']);
    $staff_id = $_SESSION['user_id'];

    if ($amount <= 0) {
        $response['message'] = 'Invalid amount';
        echo json_encode($response);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        // Get current balance
        $res = mysqli_query($conn, "SELECT current_balance, account_number FROM accounts WHERE account_id = $acc_id FOR UPDATE");
        $acc = mysqli_fetch_assoc($res);

        if (!$acc)
            throw new Exception("Account not found");

        if ($action === 'withdraw' && $acc['current_balance'] < $amount) {
            throw new Exception("Insufficient balance");
        }

        $new_balance = ($action === 'deposit') ? $acc['current_balance'] + $amount : $acc['current_balance'] - $amount;
        $type_id = ($action === 'deposit') ? 1 : 2; // From transaction_types: 1=Deposit, 2=Withdrawal
        $ref = ($action === 'deposit' ? "DEP" : "WTH") . time() . rand(10, 99);

        // Update balance
        mysqli_query($conn, "UPDATE accounts SET current_balance = $new_balance, available_balance = $new_balance WHERE account_id = $acc_id");

        // Record transaction
        $trans_query = "INSERT INTO transactions (transaction_reference, to_account_id, from_account_id, transaction_type_id, amount, status, initiated_by_user_id, verified_by_staff_id) 
                        VALUES ('$ref', " . ($action === 'deposit' ? $acc_id : 'NULL') . ", " . ($action === 'withdraw' ? $acc_id : 'NULL') . ", $type_id, $amount, 'Completed', $staff_id, $staff_id)";
        mysqli_query($conn, $trans_query);

        mysqli_commit($conn);
        $response = ['status' => 'success', 'message' => ucfirst($action) . " of Rs. " . number_format($amount, 2) . " successful.", 'new_balance' => number_format($new_balance, 2)];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGB | Cashier Terminal - Live</title>
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

        input {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
            transition: 0.3s;
        }

        input:focus {
            border-color: var(--accent) !important;
            outline: none;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 text-3xl font-black italic">NGB<span class="text-yellow-500">.</span></div>
        <nav class="flex-1">
            <a href="accountant.php" class="nav-link"><i class="fa fa-user-shield"></i> Accountant</a>
            <a href="cashier.php" class="nav-link active"><i class="fa fa-vault"></i> Cashier</a>
            <a href="support_staff.php" class="nav-link"><i class="fa fa-headset"></i> Support</a>
        </nav>
        <div class="pt-8 border-t border-white/5">
            <a href="../logout.php" class="nav-link text-red-500/60"><i class="fa fa-power-off"></i> Logout</a>
        </div>
    </aside>

    <main class="flex-1 p-12 overflow-y-auto">
        <header class="flex justify-between items-end mb-12">
            <div>
                <h2 class="text-5xl font-black italic tracking-tighter uppercase text-white">Cashier <span
                        class="text-white/20">Terminal</span></h2>
                <p class="text-yellow-500 text-[10px] font-black tracking-[0.4em] uppercase mt-2">Vault Operator:
                    <?php echo $_SESSION['full_name']; ?>
                </p>
            </div>
            <div id="account-status" class="text-right text-sm font-bold text-white/40 italic">Waiting for Account...
            </div>
        </header>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-12 lg:col-span-7 space-y-8">
                <div class="glass-card p-10">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/40 mb-6">Search Account</h4>
                    <div class="flex gap-4">
                        <input type="text" id="acc_num_search" placeholder="Enter Account Number (e.g. NGB...)"
                            class="flex-1 px-8 py-5 rounded-3xl font-mono text-lg">
                        <button onclick="fetchAccount()"
                            class="bg-yellow-500 text-black font-black px-12 rounded-3xl hover:scale-105 transition uppercase text-[11px] tracking-widest">Lookup</button>
                    </div>
                </div>

                <div id="accountDetails"
                    class="glass-card p-10 opacity-20 pointer-events-none transition-all duration-500">
                    <div class="flex justify-between items-start mb-10">
                        <div>
                            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] mb-2">Account
                                Holder</p>
                            <h3 id="holderName" class="text-3xl font-black tracking-tight italic">---- ----</h3>
                            <p id="holderCNIC" class="text-[11px] font-mono text-white/40 mt-1">CNIC: -------------</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] font-black text-white/30 uppercase tracking-[0.3em] mb-2">Liquid
                                Balance</p>
                            <h3 id="holderBalance" class="text-3xl font-black text-yellow-500 font-mono">Rs. 0.00</h3>
                            <p id="accountType"
                                class="text-[10px] bg-white/10 px-3 py-1 rounded-full mt-2 inline-block font-black uppercase text-white/60 tracking-widest">
                                ---</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <label class="text-[10px] font-black text-white/40 uppercase tracking-widest ml-4">Amount to
                                Process</label>
                            <input type="number" id="transactionAmount" placeholder="0.00"
                                class="w-full px-8 py-5 rounded-3xl text-2xl font-black font-mono">
                        </div>
                        <div class="flex items-end gap-4">
                            <button onclick="processTransaction('deposit')"
                                class="flex-1 bg-green-500/80 hover:bg-green-500 text-white font-black py-5 rounded-3xl uppercase text-xs tracking-widest transition shadow-lg shadow-green-500/10">Deposit</button>
                            <button onclick="processTransaction('withdraw')"
                                class="flex-1 bg-red-500/80 hover:bg-red-500 text-white font-black py-5 rounded-3xl uppercase text-xs tracking-widest transition shadow-lg shadow-red-500/10">Withdraw</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5">
                <div class="glass-card p-8 h-full">
                    <h4 class="text-xs font-black uppercase tracking-widest text-white/40 mb-8 flex items-center gap-3">
                        <i class="fa fa-history text-yellow-500"></i> Local Transaction Log
                    </h4>
                    <div id="localLogs" class="space-y-4 overflow-y-auto max-h-[500px] pr-2">
                        <!-- Logs will appear here -->
                        <div class="text-center py-20 text-white/10 uppercase font-black text-[10px] tracking-widest">No
                            activity in current session</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentAccountId = null;

        function fetchAccount() {
            const accNum = document.getElementById('acc_num_search').value;
            if (!accNum) return alert("Please enter an account number");

            const formData = new FormData();
            formData.append('fetch_account', accNum);

            fetch('cashier.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('holderName').innerText = data.name;
                        document.getElementById('holderCNIC').innerText = "CNIC: " + data.cnic;
                        document.getElementById('holderBalance').innerText = "Rs. " + data.balance;
                        document.getElementById('accountType').innerText = data.type;
                        currentAccountId = data.account_id;

                        document.getElementById('accountDetails').classList.remove('opacity-20', 'pointer-events-none');
                        document.getElementById('account-status').innerText = "Account Verified: " + data.name;
                    } else {
                        alert(data.message);
                        resetUI();
                    }
                });
        }

        function processTransaction(action) {
            const amount = document.getElementById('transactionAmount').value;
            if (!amount || amount <= 0) return alert("Please enter a valid amount");
            if (!currentAccountId) return alert("Session Error: No account selected");

            const formData = new FormData();
            formData.append('action', action);
            formData.append('account_id', currentAccountId);
            formData.append('amount', amount);

            fetch('cashier.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('holderBalance').innerText = "Rs. " + data.new_balance;
                        addLog(action, amount, data.message);
                        document.getElementById('transactionAmount').value = "";
                        alert(data.message);
                    } else {
                        alert(data.message);
                    }
                });
        }

        function addLog(type, amount, msg) {
            const logs = document.getElementById('localLogs');
            if (logs.querySelector('.text-center')) logs.innerHTML = "";

            const log = document.createElement('div');
            log.className = "p-4 bg-white/5 rounded-2xl border-l-4 " + (type === 'deposit' ? 'border-green-500' : 'border-red-500');
            log.innerHTML = `
                <div class="flex justify-between items-center mb-1">
                    <p class="text-[10px] font-black uppercase text-white/40">${new Date().toLocaleTimeString()}</p>
                    <p class="text-xs font-bold text-${type === 'deposit' ? 'green' : 'red'}-500 uppercase">${type}</p>
                </div>
                <p class="text-sm font-bold">Rs. ${parseFloat(amount).toLocaleString()}</p>
            `;
            logs.insertBefore(log, logs.firstChild);
        }

        function resetUI() {
            document.getElementById('accountDetails').classList.add('opacity-20', 'pointer-events-none');
            document.getElementById('holderName').innerText = "---- ----";
            document.getElementById('holderBalance').innerText = "Rs. 0.00";
            document.getElementById('account-status').innerText = "Waiting for Account...";
            currentAccountId = null;
        }
    </script>
</body>

</html>