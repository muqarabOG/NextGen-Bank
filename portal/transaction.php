<?php
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Fetch Balance
$acc_q = mysqli_query($conn, "SELECT available_balance FROM accounts WHERE user_id = $user_id LIMIT 1");
$acc = mysqli_fetch_assoc($acc_q);
$balance = $acc ? $acc['available_balance'] : 0;

// Fetch Stats
$income_q = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE to_account_id IN (SELECT account_id FROM accounts WHERE user_id = $user_id) AND status = 'Completed'");
$income = mysqli_fetch_assoc($income_q)['total'] ?? 0;

$expense_q = mysqli_query($conn, "SELECT SUM(amount) as total FROM transactions WHERE from_account_id IN (SELECT account_id FROM accounts WHERE user_id = $user_id) AND status = 'Completed'");
$expense = mysqli_fetch_assoc($expense_q)['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Transaction Ledger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #050505;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            scroll-behavior: smooth;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            border-radius: 2.5rem;
        }

        input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                color: black !important;
                padding: 0 !important;
            }

            .glass-card {
                background: transparent !important;
                border: 1px solid #eee !important;
                color: black !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                color: black !important;
            }

            th {
                border-bottom: 2px solid #eee !important;
                color: #333 !important;
            }

            tr {
                border-bottom: 1px solid #eee !important;
            }

            .print-header {
                display: block !important;
            }
        }

        .print-header {
            display: none;
            margin-bottom: 2rem;
        }
    </style>
</head>

<body class="p-4 md:p-10">

    <div class="print-header text-center">
        <h1 class="text-3xl font-black italic">NextGen Bank</h1>
        <p class="uppercase tracking-widest text-[10px] font-bold">Official Account Statement</p>
        <p class="text-xs mt-2">Generated for:
            <?php echo $full_name; ?> | Date:
            <?php echo date('d M Y'); ?>
        </p>
    </div>

    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center mb-10 no-print">
            <div>
                <h1 class="text-3xl font-black italic text-yellow-500">NextGen<span class="text-white">.</span>Portal
                </h1>
                <p class="text-white/30 text-[10px] font-bold uppercase tracking-[0.3em] mt-1">Global Transaction Node
                </p>
            </div>
            <div class="flex items-center gap-4 bg-white/5 p-2 pr-6 rounded-full border border-white/10">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=EAB308&color=000"
                    class="w-10 h-10 rounded-full border border-white/20">
                <div>
                    <p class="text-xs font-bold">
                        <?php echo $full_name; ?>
                    </p>
                    <p class="text-[9px] text-green-500 font-black">ACTIVE SESSION</p>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10 no-print">
            <div class="glass-card p-8 col-span-2 relative overflow-hidden">
                <p class="text-[10px] font-black text-white/40 uppercase tracking-widest mb-2">Total Balance</p>
                <h2 class="text-5xl font-black italic">Rs.
                    <?php echo number_format($balance, 2); ?>
                </h2>
                <div class="flex gap-4 mt-8">
                    <a href="transfer.php"
                        class="bg-yellow-500 text-black px-8 py-3 rounded-2xl font-black text-[10px] uppercase transition-all">Move
                        Funds</a>
                    <a href="dashboard.php"
                        class="bg-white/5 border border-white/10 px-8 py-3 rounded-2xl font-black text-[10px] uppercase hover:bg-white/10 transition">Overview</a>
                </div>
            </div>
            <div class="glass-card p-8 border-green-500/20">
                <p class="text-[10px] font-black text-green-500 uppercase tracking-widest mb-1">Total Income</p>
                <h3 class="text-2xl font-black">+ Rs.
                    <?php echo number_format($income); ?>
                </h3>
            </div>
            <div class="glass-card p-8 border-red-500/20">
                <p class="text-[10px] font-black text-red-500 uppercase tracking-widest mb-1">Total Expenses</p>
                <h3 class="text-2xl font-black">- Rs.
                    <?php echo number_format($expense); ?>
                </h3>
            </div>
        </div>

        <div class="glass-card p-8" id="statementSection">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 mb-8 no-print">
                <div>
                    <h3 class="text-xl font-bold italic tracking-tight uppercase">Master Ledger</h3>
                </div>

                <div class="flex flex-wrap gap-4 w-full lg:w-auto items-end">
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-white/30 uppercase ml-1">From Date</label>
                        <input type="date" id="startDate"
                            class="bg-white/5 border border-white/10 text-white text-xs p-3 rounded-xl outline-none focus:border-yellow-500 w-full md:w-36">
                    </div>
                    <div class="space-y-1">
                        <label class="text-[9px] font-black text-white/30 uppercase ml-1">To Date</label>
                        <input type="date" id="endDate"
                            class="bg-white/5 border border-white/10 text-white text-xs p-3 rounded-xl outline-none focus:border-yellow-500 w-full md:w-36">
                    </div>
                    <button onclick="applyDateFilter()"
                        class="bg-yellow-500 text-black px-6 py-3 rounded-xl text-[10px] font-black uppercase transition-all">Filter</button>
                    <button onclick="window.print()"
                        class="bg-white/10 text-white px-5 py-3 rounded-xl text-[10px] font-black uppercase hover:bg-white/20 transition">Print</button>
                </div>
            </div>

            <div id="dateWarning" class="hidden mb-8 p-6 bg-red-500/10 border-l-4 border-red-500 rounded-r-2xl">
                <p class="text-sm font-black text-red-500 uppercase">Archival Limit Reached</p>
                <p class="text-[11px] text-white/60 mt-1">Electronic access is limited to 1 year. Visit branch for older
                    records.</p>
            </div>

            <div class="overflow-x-auto" id="tableContainer">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="text-[10px] text-white/20 font-black uppercase tracking-widest border-b border-white/5">
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Details</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <!-- Dynamic Content -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function loadTransactions(startDate = null, endDate = null) {
            const formData = new FormData();
            if (startDate) formData.append('start_date', startDate);
            if (endDate) formData.append('end_date', endDate);

            fetch('../backend/transaction_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    const tbody = document.querySelector('tbody');
                    tbody.innerHTML = '';
                    if (data.success && data.transactions.length > 0) {
                        data.transactions.forEach(tx => {
                            const date = new Date(tx.transaction_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
                            const isIncoming = tx.to_account_id && tx.to_account_id == data.my_account_id;
                            const amtClass = isIncoming ? 'text-green-500' : 'text-red-400';
                            const prefix = isIncoming ? '+' : '-';

                            tbody.innerHTML += `
                                <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
                                    <td class="px-6 py-5 italic font-mono text-white/40">${date}</td>
                                    <td class="px-6 py-5">
                                        <p class="font-bold">${tx.type_name}</p>
                                        <p class="text-[9px] text-white/30 uppercase">REF: ${tx.transaction_reference}</p>
                                    </td>
                                    <td class="px-6 py-5 text-right font-black ${amtClass}">${prefix} Rs. ${parseFloat(tx.amount).toLocaleString()}</td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="text-[8px] font-black px-3 py-1 rounded-full uppercase italic ${tx.status == 'Completed' ? 'bg-green-500/10 text-green-500' : 'bg-yellow-500/10 text-yellow-500'}">${tx.status}</span>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-20 text-white/10 uppercase font-black">No Records Found</td></tr>';
                    }
                });
        }

        function applyDateFilter() {
            const start = document.getElementById('startDate').value;
            const end = document.getElementById('endDate').value;
            if (!start || !end) return alert("Select range.");
            loadTransactions(start, end);
        }

        document.addEventListener('DOMContentLoaded', () => loadTransactions());
    </script>
</body>

</html>