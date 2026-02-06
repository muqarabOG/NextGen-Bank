<?php
require_once '../includes/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Fetch all accounts for this user
$accounts_query = "SELECT a.*, t.type_name 
                   FROM accounts a 
                   JOIN account_types t ON a.type_id = t.type_id 
                   WHERE a.user_id = $user_id";
$accounts_res = mysqli_query($conn, $accounts_query);
$all_accounts = [];
while ($row = mysqli_fetch_assoc($accounts_res)) {
    $all_accounts[] = $row;
}

$selected_acc_num = $_GET['acc'] ?? ($all_accounts[0]['account_number'] ?? null);
$primary_account = null;
foreach ($all_accounts as $acc) {
    if ($acc['account_number'] == $selected_acc_num) {
        $primary_account = $acc;
        break;
    }
}
if (!$primary_account && !empty($all_accounts))
    $primary_account = $all_accounts[0];

$balance = $primary_account ? number_format($primary_account['current_balance'], 2) : "0.00";
$account_num = $primary_account ? $primary_account['account_number'] : "N/A";
$account_type = $primary_account ? $primary_account['type_name'] : "N/A";

// Fetch recent transactions
$trans_query = "SELECT t.*, tt.type_name as trans_type, tt.category 
                FROM transactions t 
                JOIN transaction_types tt ON t.transaction_type_id = tt.type_id 
                WHERE t.from_account_id IN (SELECT account_id FROM accounts WHERE user_id = $user_id) 
                OR t.to_account_id IN (SELECT account_id FROM accounts WHERE user_id = $user_id) 
                ORDER BY t.transaction_date DESC LIMIT 5";
$trans_res = mysqli_query($conn, $trans_query);

// Fetch Card details
$card_query = "SELECT * FROM cards WHERE user_id = $user_id LIMIT 1";
$card_res = mysqli_query($conn, $card_query);
$card = mysqli_fetch_assoc($card_res);

$card_num = $card ? "**** " . substr($card['card_number'], -4) : "No Active Card";

// Fetch pending dues
$dues_query = "SELECT * FROM pending_dues WHERE user_id = $user_id AND status = 'Pending' LIMIT 3";
$dues_res = mysqli_query($conn, $dues_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Command Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono&display=swap"
        rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="stylesheet" href="../assets/css/chatbot.css">

    <style>
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
            background: radial-gradient(circle at 0% 0%, #2e1065 0%, transparent 35%), radial-gradient(circle at 100% 100%, #854d0e 0%, transparent 35%);
            z-index: -1;
            filter: blur(80px);
        }

        .sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.02);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            padding: 2rem;
        }

        .main-view {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            scroll-behavior: smooth;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2rem;
            backdrop-filter: blur(12px);
            transition: 0.3s;
        }

        .glass-card:hover {
            border-color: rgba(255, 215, 0, 0.3);
            background: rgba(255, 255, 255, 0.05);
        }

        .balance-card {
            background: linear-gradient(135deg, #4B0082 0%, #1a0033 100%);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            border-radius: 15px;
            color: rgba(255, 255, 255, 0.4);
            transition: 0.3s;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .nav-link:hover,
        .nav-link.active {
            color: #FFD700;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-link.active {
            border-left: 3px solid #FFD700;
            border-radius: 0 15px 15px 0;
        }

        .btn-action {
            background: rgba(255, 215, 0, 0.05);
            border: 1px solid rgba(255, 215, 0, 0.2);
            color: #FFD700;
            padding: 1.5rem;
            border-radius: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            transition: 0.4s;
        }

        .btn-action:hover {
            background: #FFD700;
            color: #000;
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(255, 215, 0, 0.1);
        }

        .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 8px 15px;
            outline: none;
            transition: 0.3s;
            font-size: 13px;
        }

        .search-input:focus {
            border-color: #FFD700;
            width: 250px;
        }

        #qrModal {
            backdrop-filter: blur(10px);
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 px-4">
            <h1 class="text-2xl font-black tracking-tighter uppercase italic text-white">NextGen<span
                    class="text-yellow-500">.</span></h1>
        </div>
        <nav class="flex-1">
            <a href="dashboard.php" class="nav-link active"><i class="fa fa-th-large"></i> Dashboard</a>
            <a href="transaction.php" class="nav-link"><i class="fa fa-exchange-alt"></i>Transaction History</a>
            <a href="cards.php" class="nav-link"><i class="fa fa-credit-card"></i> My Cards</a>
            <a href="support.php" class="nav-link"><i class="fa fa-headset"></i> Support</a>
            <a href="#" class="nav-link"><i class="fa fa-cog"></i> Settings</a>
        </nav>
        <div class="mt-auto">
            <a href="../logout.php" class="nav-link text-red-400 hover:text-red-500 hover:bg-red-500/5">
                <i class="fa fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-view">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-3xl font-black uppercase tracking-tighter">Command <span
                        class="text-yellow-500">Center</span></h2>
                <p class="text-white/40 text-[10px] font-bold tracking-[0.4em] uppercase italic">System Authenticated:
                    <?php echo htmlspecialchars($full_name); ?>
                </p>
            </div>

            <div class="flex items-center gap-6">
                <div class="relative hidden md:block">
                    <i class="fa fa-search absolute left-4 top-1/2 -translate-y-1/2 text-white/20 text-xs"></i>
                    <input type="text" placeholder="Search Assets..." class="search-input pl-10 w-48">
                </div>

                <button
                    class="relative w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center hover:bg-white/10 transition">
                    <i class="fa fa-bell text-white/60"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-yellow-500 rounded-full animate-ping"></span>
                </button>

                <div class="text-right hidden sm:block">
                    <p class="text-[10px] font-black text-green-500 uppercase tracking-widest leading-none">Status:
                        Secure</p>
                    <p class="text-xs font-mono text-white/40">Acc:
                        <?php echo htmlspecialchars($account_num); ?>
                    </p>
                </div>
                <div
                    class="w-12 h-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-xl shadow-lg border-yellow-500/20">
                    👤</div>
            </div>
        </header>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-12 lg:col-span-8 space-y-8">
                <div
                    class="glass-card balance-card p-10 flex flex-col md:flex-row justify-between items-center shadow-2xl">
                    <div class="z-10 w-full">
                        <div class="flex justify-between items-start mb-6 w-full">
                            <div>
                                <p class="text-purple-200 text-[10px] font-black uppercase tracking-[0.3em] mb-1">
                                    Portfolio Overlord</p>
                                <h3 class="text-4xl font-black text-white tracking-tighter" id="balanceDisplay">Rs.
                                    <?php echo $balance; ?>
                                </h3>
                            </div>
                            <div class="text-right">
                                <p class="text-white/20 text-[9px] font-black uppercase tracking-widest">Selected Entity
                                </p>
                                <p class="text-xs font-bold text-yellow-500 italic"><?php echo $account_num; ?></p>
                            </div>
                        </div>

                        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                            <?php foreach ($all_accounts as $acc): ?>
                                <div onclick="switchAccount('<?php echo $acc['account_number']; ?>', '<?php echo number_format($acc['current_balance'], 2); ?>')"
                                    class="flex-shrink-0 px-4 py-3 rounded-2xl bg-white/5 border border-white/10 cursor-pointer hover:border-yellow-500/50 transition <?php echo ($acc['account_number'] == $account_num) ? 'border-yellow-500 bg-yellow-500/10' : ''; ?>">
                                    <p class="text-[8px] font-black text-white/30 uppercase">
                                        <?php echo $acc['type_name']; ?>
                                    </p>
                                    <p class="text-[10px] font-mono font-bold"><?php echo $acc['account_number']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button onclick="window.location.href='transfer.php'" class="btn-action"><i
                            class="fa fa-paper-plane text-xl"></i> <span
                            class="text-[10px] font-black uppercase tracking-widest">Transfer</span></button>
                    <button onclick="toggleQRModal()" class="btn-action"><i class="fa fa-qrcode text-xl"></i> <span
                            class="text-[10px] font-black uppercase tracking-widest">Scanner</span></button>
                    <button onclick="window.location.href='bills.php'" class="btn-action"><i
                            class="fa fa-file-invoice-dollar text-xl"></i> <span
                            class="text-[10px] font-black uppercase tracking-widest">Bill Pay</span></button>
                    <button class="btn-action"><i class="fa fa-vault text-xl"></i> <span
                            class="text-[10px] font-black uppercase tracking-widest">Vault</span></button>
                </div>

                <div class="glass-card p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h4 class="text-xs font-black uppercase tracking-widest text-white/60">Recent Transactions</h4>
                        <button onclick="window.location.href='transaction.php'"
                            class="text-[9px] text-yellow-500 font-black uppercase hover:underline">View All</button>
                    </div>
                    <div class="space-y-6">
                        <?php if (mysqli_num_rows($trans_res) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($trans_res)): ?>
                                <div
                                    class="flex justify-between items-center p-4 rounded-2xl hover:bg-white/5 transition border-t border-white/5 first:border-t-0">
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="w-12 h-12 rounded-xl bg-<?php echo $row['category'] == 'Credit' ? 'green' : 'yellow'; ?>-500/10 border border-<?php echo $row['category'] == 'Credit' ? 'green' : 'yellow'; ?>-500/20 flex items-center justify-center text-<?php echo $row['category'] == 'Credit' ? 'green' : 'yellow'; ?>-500">
                                            <i
                                                class="fa <?php echo $row['category'] == 'Credit' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold">
                                                <?php echo htmlspecialchars($row['trans_type']); ?>
                                            </p>
                                            <p class="text-[9px] text-white/30 uppercase font-black tracking-tighter italic">
                                                <?php echo date("d M Y • H:i", strtotime($row['transaction_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p
                                        class="text-<?php echo $row['category'] == 'Credit' ? 'green' : 'red'; ?>-500 font-mono text-sm font-bold">
                                        <?php echo ($row['category'] == 'Credit' ? '+' : '-') . "Rs. " . number_format($row['amount'], 2); ?>
                                    </p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-white/20 uppercase font-black text-xs py-10">No recent activity
                                detected</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 space-y-8">
                <div
                    class="glass-card p-8 bg-gradient-to-br from-white/10 to-transparent border-white/20 relative overflow-hidden group">
                    <div
                        class="absolute -right-5 -top-5 w-24 h-24 bg-yellow-500/10 rounded-full blur-3xl group-hover:bg-yellow-500/20 transition">
                    </div>
                    <p class="text-[10px] font-black uppercase text-yellow-500 tracking-[0.4em] mb-12">Virtual Obsidian
                    </p>
                    <h5 class="text-2xl font-mono tracking-[0.25em] mb-8 text-white/90 italic">
                        <?php echo $card_num; ?>
                    </h5>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[8px] text-white/40 uppercase tracking-widest font-black">Operator</p>
                            <p class="text-sm font-bold uppercase tracking-tighter">
                                <?php echo htmlspecialchars($full_name); ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <i class="fab fa-cc-visa text-4xl text-white/20 group-hover:text-white transition"></i>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-8 border-green-500/20 bg-green-500/5">
                    <div class="flex items-center gap-4 mb-6">
                        <div
                            class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center animate-pulse">
                            <i class="fa fa-fingerprint text-green-500"></i>
                        </div>
                        <h4 class="text-xs font-black uppercase tracking-widest">Biometric Guard</h4>
                    </div>
                    <p class="text-[11px] text-white/40 leading-relaxed mb-6 italic">Secure session active. Protected by
                        NextGen Quantum Shielding.</p>
                    <div class="w-full h-1 bg-white/5 rounded-full overflow-hidden">
                        <div id="bioProgress"
                            class="h-full bg-green-500 w-[85%] shadow-[0_0_15px_#22c55e] transition-all duration-1000">
                        </div>
                    </div>
                </div>

                <div class="glass-card p-6 border-yellow-500/10 bg-white/5">
                    <p class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-2">System Time</p>
                    <p id="liveClock"
                        class="text-3xl font-mono font-bold text-yellow-500 tracking-tighter drop-shadow-[0_0_10px_rgba(255,215,0,0.3)]">
                        00:00:00</p>
                </div>

                <div class="glass-card p-6 border-red-500/10 bg-red-500/5">
                    <h4 class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-4">Pending Dues</h4>
                    <div class="space-y-4">
                        <?php if (mysqli_num_rows($dues_res) > 0): ?>
                            <?php while ($due = mysqli_fetch_assoc($dues_res)): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-white/60"><?php echo htmlspecialchars($due['due_type']); ?></span>
                                    <span class="font-black text-red-500">Rs.
                                        <?php echo number_format($due['due_amount'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-[9px] text-white/20 italic">No outstanding dues</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="qrModal" class="fixed inset-0 z-[100] hidden flex items-center justify-center bg-black/80 p-4">
        <div class="glass-card p-8 w-full max-w-sm border-yellow-500/30 text-center relative">
            <button onclick="toggleQRModal()" class="absolute top-4 right-4 text-white/40 hover:text-white"><i
                    class="fa fa-times text-xl"></i></button>
            <h3 class="text-xl font-black uppercase tracking-tighter mb-2">Account <span
                    class="text-yellow-500">QR</span></h3>
            <div class="bg-white p-4 rounded-3xl inline-block mt-4" id="qrcode"></div>
            <p class="text-xs font-mono text-yellow-500 mt-6 font-bold tracking-widest uppercase">ID:
                <?php echo htmlspecialchars($user_id); ?>-SEC
            </p>
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('liveClock').textContent = `${hours}:${minutes}:${seconds}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        const balanceDisplay = document.getElementById('balanceDisplay');
        let currentBalance = "<?php echo $balance; ?>";
        let hidden = false;

        balanceDisplay.addEventListener('click', () => {
            hidden = !hidden;
            balanceDisplay.innerHTML = hidden ? "Rs. ••••••••" : "Rs. " + currentBalance;
        });

        function switchAccount(num, bal) {
            window.location.href = 'dashboard.php?acc=' + num;
        }

        let qrGenerated = false;
        function toggleQRModal() {
            document.getElementById('qrModal').classList.toggle('hidden');
            if (!qrGenerated) {
                new QRCode(document.getElementById("qrcode"), {
                    text: "ngb-user-<?php echo $user_id; ?>",
                    width: 180, height: 180,
                    colorDark: "#000", colorLight: "#fff",
                    correctLevel: QRCode.CorrectLevel.H
                });
                qrGenerated = true;
            }
        }
    </script>
    <script src="../assets/js/chatbot.js"></script>
</body>

</html>