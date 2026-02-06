<?php
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Utility Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        body {
            background: #050505;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 10% 10%, #2e1065 0%, transparent 40%), radial-gradient(circle at 90% 90%, #854d0e 0%, transparent 40%);
            z-index: -1;
            filter: blur(80px);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2.5rem;
            backdrop-filter: blur(20px);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
        }

        select,
        input {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-radius: 1.2rem !important;
            padding: 1.2rem !important;
            width: 100%;
            outline: none;
            transition: 0.3s;
        }

        select:focus,
        input:focus {
            border-color: #FFD700 !important;
        }

        .btn-pay {
            background: #FFD700;
            color: #000;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 1.2rem;
            border-radius: 1.2rem;
            width: 100%;
            transition: 0.4s;
            margin-top: 2rem;
        }

        .btn-pay:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(255, 215, 0, 0.2);
        }

        option {
            background: #1a0033;
            color: white;
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <div class="glass-card">
        <div class="mb-8">
            <a href="dashboard.php"
                class="text-white/30 hover:text-yellow-500 text-[10px] uppercase font-black tracking-widest transition flex items-center gap-2">
                <i class="fa fa-chevron-left"></i> Hub Terminal
            </a>
        </div>

        <div class="text-center mb-10">
            <h2 class="text-3xl font-black uppercase tracking-tighter">Utility <span class="text-yellow-500">Pay</span>
            </h2>
            <p class="text-[9px] text-white/30 uppercase font-bold tracking-[0.5em] mt-2 italic">Neural Network Billing
            </p>
        </div>

        <form id="billingForm" class="space-y-6">
            <div>
                <label
                    class="text-[10px] font-black uppercase tracking-widest text-yellow-500/80 ml-2 mb-2 block">Utility
                    Type</label>
                <select id="category" onchange="updateAgencies()" required>
                    <option value="" disabled selected>Choose Type</option>
                    <option value="Electricity">Electricity</option>
                    <option value="Gas">Natural Gas</option>
                    <option value="Water">Water</option>
                    <option value="Internet">Internet</option>
                </select>
            </div>

            <div id="agencyGroup" class="hidden">
                <label
                    class="text-[10px] font-black uppercase tracking-widest text-yellow-500/80 ml-2 mb-2 block">Provider</label>
                <select id="agency" required></select>
            </div>

            <div id="refGroup" class="hidden">
                <label
                    class="text-[10px] font-black uppercase tracking-widest text-yellow-500/80 ml-2 mb-2 block">Consumer
                    ID</label>
                <input type="text" id="refId" placeholder="Referenence Number" required>
            </div>

            <div id="billDetails" class="hidden p-6 bg-white/5 border border-white/10 rounded-2xl animate-fade-in">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-[9px] text-white/40 uppercase font-bold">Status: <span
                                class="text-red-500">UNPAID</span></p>
                        <h4 class="text-xl font-black text-white" id="billAmount">Rs. 0.00</h4>
                    </div>
                    <p class="text-[10px] text-white/40 uppercase font-bold text-right">Due Date<br><span
                            class="text-white" id="dueDate">-- --</span></p>
                </div>
            </div>

            <button type="submit" id="mainBtn" class="btn-pay">Fetch Bill Data</button>
        </form>
    </div>

    <script>
        const agencyData = {
            Electricity: ['LESCO', 'GEPCO', 'K-Electric', 'MEPCO'],
            Gas: ['SNGPL', 'SSGPL'],
            Water: ['WASA Lahore', 'WASA Karachi', 'WASA Islamabad'],
            Internet: ['PTCL', 'Nayatel', 'StormFiber']
        };

        let billLoaded = false;
        let amountToPay = 0;

        function updateAgencies() {
            const cat = document.getElementById('category').value;
            const ag = document.getElementById('agency');
            ag.innerHTML = '<option value="" disabled selected>Select Provider</option>';
            agencyData[cat].forEach(a => {
                const opt = document.createElement('option');
                opt.value = a; opt.textContent = a;
                ag.appendChild(opt);
            });
            document.getElementById('agencyGroup').classList.remove('hidden');
            document.getElementById('refGroup').classList.remove('hidden');
        }

        document.getElementById('billingForm').onsubmit = function (e) {
            e.preventDefault();
            const btn = document.getElementById('mainBtn');

            if (!billLoaded) {
                btn.innerHTML = '<i class="fa fa-sync fa-spin"></i> Fetching...';
                setTimeout(() => {
                    amountToPay = (Math.random() * (15000 - 500) + 500).toFixed(2);
                    document.getElementById('billAmount').innerText = 'Rs. ' + parseFloat(amountToPay).toLocaleString();
                    document.getElementById('dueDate').innerText = '28 FEB 2026';
                    document.getElementById('billDetails').classList.remove('hidden');
                    btn.innerHTML = 'Pay Bill Now';
                    btn.classList.add('bg-green-500');
                    billLoaded = true;
                }, 1000);
            } else {
                if (confirm(`Authorize payment of Rs. ${amountToPay}?`)) {
                    btn.disabled = true;
                    btn.innerHTML = 'Processing...';

                    const formData = new FormData();
                    formData.append('type', document.getElementById('category').value);
                    formData.append('provider', document.getElementById('agency').value);
                    formData.append('ref', document.getElementById('refId').value);
                    formData.append('amount', amountToPay);

                    fetch('../backend/bill_process.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                alert("PAYMENT SUCCESSFUL: " + data.ref);
                                location.href = 'dashboard.php';
                            } else {
                                alert(data.error || "Payment Denied.");
                                btn.disabled = false;
                                btn.innerHTML = 'Pay Bill Now';
                            }
                        });
                }
            }
        };
    </script>
</body>

</html>