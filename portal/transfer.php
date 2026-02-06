<?php
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch Account and Card for display
$acc_query = "SELECT a.*, c.card_number FROM accounts a LEFT JOIN cards c ON a.account_id = c.account_id WHERE a.user_id = $user_id LIMIT 1";
$acc_res = mysqli_query($conn, $acc_query);
$acc_data = mysqli_fetch_assoc($acc_res);

$display_card = $acc_data && $acc_data['card_number'] ?
    substr($acc_data['card_number'], 0, 4) . " **** **** " . substr($acc_data['card_number'], -4) :
    "NO ACTIVE CARD";
$display_balance = $acc_data ? number_format($acc_data['available_balance'], 2) : "0.00";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Ultra Secure Transfer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #050505;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            overflow: hidden;
            height: 100vh;
        }

        .bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, #4B0082 0%, transparent 40%), radial-gradient(circle at 80% 70%, #B8860B 0%, transparent 40%);
            z-index: -1;
            filter: blur(100px);
            animation: pulseBg 10s infinite alternate;
        }

        @keyframes pulseBg {
            0% {
                transform: scale(1);
                opacity: 0.5;
            }

            100% {
                transform: scale(1.2);
                opacity: 0.8;
            }
        }

        .ultra-glass {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .btn-premium {
            background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%);
            color: #000;
            font-weight: 900;
            letter-spacing: 2px;
            transition: 0.4s all cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .card-preview {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transform: perspective(1000px) rotateX(10deg);
        }

        input,
        select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            padding: 1rem !important;
            border-radius: 1.25rem !important;
            outline: none;
            width: 100%;
            color: white !important;
        }

        input:focus,
        select:focus {
            border-color: #FFD700 !important;
        }

        .type-pill {
            cursor: pointer;
            padding: 8px 20px;
            border-radius: 100px;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: 0.3s;
        }

        .type-pill.active {
            background: #FFD700;
            color: #000;
            border-color: #FFD700;
        }
    </style>
</head>

<body class="flex items-center justify-center min-h-screen">
    <div class="bg-canvas"></div>

    <div class="ultra-glass p-8 md:p-10 w-full max-w-xl rounded-[3rem]">

        <div class="flex items-center justify-between mb-6 p-3 bg-white/5 rounded-2xl border border-white/10">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-white/50 uppercase tracking-widest">Available Balance</span>
            </div>
            <div class="text-[14px] font-mono text-yellow-500 font-bold">Rs.
                <?php echo $display_balance; ?>
            </div>
        </div>

        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-black text-white tracking-tighter">Secure Transfer</h2>
            <a href="dashboard.php"
                class="text-white/40 hover:text-white transition-all text-sm font-bold bg-white/5 px-4 py-2 rounded-xl border border-white/10">
                <i class="fa fa-arrow-left"></i>
            </a>
        </div>

        <div class="card-preview mb-8">
            <div class="flex justify-between items-start mb-6">
                <i class="fa fa-microchip text-2xl text-yellow-500/80"></i>
                <div class="text-right text-[10px] text-white/40 font-bold uppercase tracking-widest">NextGen Account
                    Holder</div>
            </div>
            <div class="mb-4">
                <p class="text-[10px] text-white/30 uppercase tracking-[0.3em]">Source Card</p>
                <h3 class="text-xl font-mono text-white tracking-widest">
                    <?php echo $display_card; ?>
                </h3>
            </div>
        </div>

        <div class="flex gap-4 mb-8">
            <div id="typeInternal" onclick="setTransferType('internal')" class="type-pill active">Internal Transfer
            </div>
            <div id="typeExternal" onclick="setTransferType('external')" class="type-pill">External Bank</div>
        </div>

        <form id="transferForm" class="space-y-5">
            <input type="hidden" id="transferType" value="internal">

            <div id="externalBankGroup" class="hidden">
                <label class="text-[10px] font-black text-yellow-500 uppercase tracking-widest ml-1">Select Bank</label>
                <select id="bankName" class="mt-2">
                    <option value="HBL">Habib Bank Limited (HBL)</option>
                    <option value="UBL">United Bank Limited (UBL)</option>
                    <option value="MCB">MCB Bank</option>
                    <option value="AlFalah">Bank AlFalah</option>
                    <option value="Meezan">Meezan Bank</option>
                </select>
            </div>

            <div>
                <label id="recipientLabel"
                    class="text-[10px] font-black text-yellow-500 uppercase tracking-widest ml-1">Recipient
                    Account</label>
                <input type="text" id="accNum" required placeholder="Enter Account Number or IBAN" class="mt-2">
            </div>

            <div>
                <label class="text-[10px] font-black text-yellow-500 uppercase tracking-widest ml-1">Amount
                    (PKR)</label>
                <input type="number" id="amount" required placeholder="0.00" min="1" step="0.01"
                    class="mt-2 text-white font-bold text-lg">
            </div>

            <button type="submit" id="submitBtn"
                class="btn-premium w-full py-4 rounded-2xl uppercase text-xs shadow-2xl mt-4">
                Execute Transaction <i class="fa fa-bolt ml-2"></i>
            </button>
        </form>

        <div id="successOverlay"
            class="hidden absolute inset-0 bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center text-center p-10 z-50 rounded-[3rem]">
            <div
                class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mb-6 shadow-[0_0_40px_rgba(34,197,94,0.4)]">
                <i class="fa fa-check text-4xl text-white"></i>
            </div>
            <h3 class="text-2xl font-black text-white mb-2">Transfer Complete</h3>
            <p id="successMsg" class="text-white/50 text-sm mb-8">Reference: TXN-99221102</p>
            <a href="dashboard.php" class="btn-premium px-10 py-3 rounded-xl text-[10px] uppercase">Return to Hub</a>
        </div>
    </div>

    <script>
        function setTransferType(type) {
            document.getElementById('transferType').value = type;
            document.getElementById('typeInternal').classList.toggle('active', type === 'internal');
            document.getElementById('typeExternal').classList.toggle('active', type === 'external');

            const extGroup = document.getElementById('externalBankGroup');
            if (type === 'external') {
                extGroup.classList.remove('hidden');
                document.getElementById('recipientLabel').innerText = "Recipient IBAN / Account";
            } else {
                extGroup.classList.add('hidden');
                document.getElementById('recipientLabel').innerText = "Recipient Account Number";
            }
        }

        document.getElementById('transferForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const type = document.getElementById('transferType').value;

            btn.innerHTML = '<i class="fa fa-sync fa-spin"></i> Encrypting...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('to_account', document.getElementById('accNum').value);
            formData.append('amount', document.getElementById('amount').value);
            formData.append('transfer_type', type);
            if (type === 'external') {
                formData.append('bank_name', document.getElementById('bankName').value);
            }

            fetch('../backend/transfer_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('successOverlay').classList.remove('hidden');
                        document.getElementById('successMsg').innerText = "Reference: " + data.ref;
                    } else {
                        alert(data.error || "Transaction denied");
                        btn.innerHTML = 'Execute Transaction <i class="fa fa-bolt ml-2"></i>';
                        btn.disabled = false;
                    }
                })
                .catch(() => {
                    alert("System error. Connection lost.");
                    btn.disabled = false;
                });
        });
    </script>
</body>

</html>