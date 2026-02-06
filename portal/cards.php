<?php
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Fetch all cards for this user
$cards_query = "SELECT c.*, ct.type_name as card_type_name, ct.card_category 
                FROM cards c 
                JOIN card_types ct ON c.card_type_id = ct.card_type_id 
                WHERE c.user_id = $user_id ORDER BY c.issue_date DESC";
$cards_res = mysqli_query($conn, $cards_query);
$all_cards = [];
while ($row = mysqli_fetch_assoc($cards_res)) {
    $all_cards[] = $row;
}

$active_card = $all_cards[0] ?? null;
$card_exists = $active_card ? true : false;
$card_num = $active_card ? $active_card['card_number'] : "XXXX XXXX XXXX XXXX";
$card_status = $active_card ? $active_card['status'] : "Inactive";
$limit = $active_card ? $active_card['daily_spending_limit'] : 100000;

// Fetch Card Types for Request
$ct_res = mysqli_query($conn, "SELECT * FROM card_types WHERE annual_fee >= 0");
$card_types = [];
while ($row = mysqli_fetch_assoc($ct_res)) {
    $card_types[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Card Control Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #050505;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            overflow: hidden;
            display: flex;
            height: 100vh;
        }

        .bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 10% 10%, #2e1065 0%, transparent 40%), radial-gradient(circle at 90% 90%, #854d0e 0%, transparent 40%);
            z-index: -1;
            filter: blur(100px);
        }

        .sidebar {
            width: 280px;
            background: rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            padding: 2rem;
            z-index: 50;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            border-radius: 15px;
            color: rgba(255, 255, 255, 0.5);
            transition: 0.3s;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.05);
            color: #FFD700;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 3rem;
        }

        .credit-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.03) 100%);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 2.5rem;
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.7);
            transition: 0.5s;
            max-width: 460px;
            position: relative;
            overflow: hidden;
        }

        .frozen-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 162, 255, 0.1);
            backdrop-filter: grayscale(1) blur(5px);
            display:
                <?php echo ($card_status === 'Inactive') ? 'flex' : 'none'; ?>
            ;
            align-items: center;
            justify-content: center;
            z-index: 40;
        }

        .blocked-stamp {
            display:
                <?php echo ($card_status === 'Blocked') ? 'block' : 'none'; ?>
            ;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-20deg);
            font-size: 3rem;
            font-weight: 900;
            color: rgba(255, 0, 0, 0.6);
            border: 4px solid rgba(255, 0, 0, 0.6);
            padding: 0.5rem 1rem;
            z-index: 45;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #333;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #FFD700;
        }

        input:checked+.slider:before {
            transform: translateX(22px);
            background-color: #000;
        }

        .action-panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2rem;
            padding: 2rem;
            transition: 0.3s;
        }

        .restricted {
            opacity: 0.3;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-12 px-4">
            <h1 class="text-2xl font-black tracking-tighter uppercase italic">NextGen<span
                    class="text-yellow-500">.</span></h1>
        </div>
        <nav class="flex-1">
            <a href="dashboard.php" class="nav-link"><i class="fa fa-th-large"></i> Dashboard</a>
            <a href="transfer.php" class="nav-link"><i class="fa fa-paper-plane"></i> Payments</a>
            <a href="cards.php" class="nav-link active"><i class="fa fa-credit-card"></i> My Wallet</a>
            <a href="transaction.php" class="nav-link"><i class="fa fa-history"></i> Activity</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="flex justify-between items-center mb-10">
            <div>
                <h2 class="text-4xl font-black uppercase tracking-tighter">Card <span
                        class="text-yellow-500">Matrix</span></h2>
                <p class="text-white/40 text-[10px] font-bold tracking-[0.4em] uppercase mt-1">Authorized Deployment
                    Zone</p>
            </div>
            <div class="flex items-center gap-4 bg-white/5 px-4 py-2 rounded-2xl border border-white/10">
                <span class="text-xs font-bold uppercase">
                    <?php echo $full_name; ?>
                </span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=FFD700&color=000"
                    class="w-8 h-8 rounded-lg">
            </div>
        </header>

        <div class="grid grid-cols-12 gap-8">
            <!-- Visual Card -->
            <div class="col-span-12 xl:col-span-5 space-y-6">
                <div id="visualCard"
                    class="credit-card p-10 relative aspect-[1.58/1] <?php echo ($card_status === 'Blocked') ? 'filter grayscale brightness-50' : ''; ?>">
                    <div class="frozen-overlay" id="freezeOverlay">
                        <div class="flex flex-col items-center gap-2">
                            <i class="fa fa-snowflake text-3xl text-blue-400 animate-pulse"></i>
                            <span class="text-xs font-black tracking-widest uppercase">Secured / Dormant</span>
                        </div>
                    </div>
                    <div class="blocked-stamp">TERMINATED</div>

                    <div class="flex justify-between items-start relative z-10">
                        <i class="fa fa-microchip text-5xl text-yellow-500/90 shadow-2xl"></i>
                        <h2 class="text-2xl font-black italic text-white/20">NextGen</h2>
                    </div>

                    <div class="mt-16 relative z-10">
                        <p class="text-3xl font-mono tracking-[0.2em] text-white/90">
                            <?php echo $card_exists ? implode(' ', str_split($card_num, 4)) : "XXXX XXXX XXXX XXXX"; ?>
                        </p>
                    </div>

                    <div class="flex justify-between items-end mt-12 relative z-10">
                        <div>
                            <p class="text-[9px] text-white/30 uppercase font-bold tracking-widest">Card Holder</p>
                            <p class="text-lg font-bold uppercase tracking-tight">
                                <?php echo $full_name; ?>
                            </p>
                        </div>
                        <i class="fab fa-cc-visa text-4xl opacity-50"></i>
                    </div>
                </div>

                <!-- Gallery of Cards -->
                <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
                    <?php if (count($all_cards) > 1): ?>
                        <?php foreach ($all_cards as $c): ?>
                            <div onclick="location.reload()"
                                class="flex-shrink-0 w-24 h-16 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center cursor-pointer hover:border-yellow-500 transition">
                                <p class="text-[8px] font-black"><?php echo substr($c['card_number'], -4); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Request Panel -->
                <div class="action-panel border-yellow-500/20 bg-yellow-500/[0.02]">
                    <h4 class="font-bold text-sm mb-4">Request New Deployment</h4>
                    <div class="space-y-4">
                        <select id="requestCardType"
                            class="w-full bg-black/40 border border-white/10 rounded-xl px-4 py-3 text-xs outline-none focus:border-yellow-500">
                            <?php foreach ($card_types as $ct): ?>
                                <option value="<?php echo $ct['card_type_id']; ?>"><?php echo $ct['type_name']; ?>
                                    (<?php echo $ct['card_category']; ?>) - Fee: <?php echo $ct['annual_fee']; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button onclick="requestNewCard()"
                            class="w-full bg-yellow-500 text-black py-4 rounded-xl text-[10px] font-black uppercase hover:scale-[1.02] transition">Authorize
                            Issuance <i class="fa fa-unlock ml-2"></i></button>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="col-span-12 xl:col-span-7 space-y-6 <?php echo $card_exists ? '' : 'restricted'; ?>">
                <div class="grid grid-cols-2 gap-6">
                    <div class="action-panel p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div
                                class="w-10 h-10 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-400 border border-blue-500/20">
                                <i class="fa fa-snowflake"></i>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="freezeToggle" onchange="toggleFreeze()" <?php echo ($card_status === 'Inactive' ? 'checked' : ''); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <p class="font-bold text-sm">Freeze Terminal</p>
                        <p class="text-[10px] text-white/30 italic">Temporary lock protocol</p>
                    </div>

                    <div onclick="permBlock()"
                        class="action-panel p-6 cursor-pointer hover:border-red-500/40 transition group">
                        <div
                            class="w-10 h-10 bg-red-500/10 rounded-xl flex items-center justify-center text-red-500 border border-red-500/20 mb-4 group-hover:bg-red-500 group-hover:text-white transition">
                            <i class="fa fa-ban"></i>
                        </div>
                        <p class="font-bold text-sm">Permanent Block</p>
                        <p class="text-[10px] text-white/30 font-bold text-red-500/50 uppercase">Irreversible Action</p>
                    </div>
                </div>

                <div class="action-panel">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <p class="font-bold text-sm uppercase tracking-widest text-white/60">Daily Spending Limit
                            </p>
                        </div>
                        <span id="limitLabel" class="text-xl font-black text-yellow-500 font-mono">Rs.
                            <?php echo number_format($limit); ?>
                        </span>
                    </div>
                    <input type="range" id="limitRange" min="5000" max="500000" step="1000"
                        value="<?php echo $limit; ?>" class="w-full h-1 bg-white/10 rounded-lg appearance-none"
                        oninput="updateLimitLabel(this.value)" onchange="saveLimit(this.value)">
                    <div class="flex justify-between mt-4 text-[10px] font-bold text-white/20 uppercase">
                        <span>Min: 5k</span><span>Max: 500k</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function updateLimitLabel(val) {
            document.getElementById('limitLabel').innerText = 'Rs. ' + parseInt(val).toLocaleString();
        }

        function toggleFreeze() {
            const isFrozen = document.getElementById('freezeToggle').checked;
            const formData = new FormData();
            formData.append('action', 'toggle_freeze');
            formData.append('status', isFrozen ? 'Inactive' : 'Active');

            fetch('../backend/card_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('freezeOverlay').style.display = isFrozen ? 'flex' : 'none';
                    } else {
                        alert("Activation Error: Verification Failed.");
                        document.getElementById('freezeToggle').checked = !isFrozen;
                    }
                });
        }

        function saveLimit(val) {
            const formData = new FormData();
            formData.append('action', 'update_limit');
            formData.append('limit', val);

            fetch('../backend/card_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) alert("Limit Update Failed.");
                });
        }

        function permBlock() {
            if (confirm("SYSTEM WARNING: This will permanently revoke all access for this card. Proceed?")) {
                const formData = new FormData();
                formData.append('action', 'permanent_block');
                fetch('../backend/card_process.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert("Termination Protocol Failed.");
                    });
            }
        }

        function requestNewCard() {
            const typeId = document.getElementById('requestCardType').value;
            if (confirm("Confirm request for selected card category? Transaction fees may apply.")) {
                const formData = new FormData();
                formData.append('action', 'request_card');
                formData.append('card_type_id', typeId);
                fetch('../backend/card_process.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) location.reload();
                    });
            }
        }
    </script>
</body>

</html>