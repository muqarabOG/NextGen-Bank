<?php
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'Customer') {
    header("Location: ../login.html");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch categories
$cat_res = mysqli_query($conn, "SELECT * FROM complaint_categories");
$categories = [];
while ($c = mysqli_fetch_assoc($cat_res))
    $categories[] = $c;

// Fetch existing complaints for the side/bottom view
$comp_res = mysqli_query($conn, "SELECT c.*, cc.category_name FROM complaints c JOIN complaint_categories cc ON c.category_id = cc.category_id WHERE c.user_id = $user_id ORDER BY c.submission_date DESC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NextGen | Support Terminal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

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
            background: radial-gradient(circle at 10% 10%, #2e1065 0%, transparent 40%), radial-gradient(circle at 90% 90%, #854d0e 0%, transparent 40%);
            z-index: -1;
            filter: blur(100px);
        }

        .sidebar {
            width: 260px;
            background: rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            padding: 2rem;
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
            text-decoration: none;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.05);
            color: #FFD700;
            border: 1px solid rgba(255, 215, 0, 0.2);
        }

        .main-content {
            flex: 1;
            overflow-y: auto;
            padding: 2.5rem;
        }

        .action-panel {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 2.5rem;
            padding: 2.5rem;
            backdrop-filter: blur(10px);
        }

        input,
        textarea,
        select {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: white !important;
        }

        input:focus,
        textarea:focus {
            border-color: #FFD700 !important;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
        }

        ::-webkit-scrollbar {
            width: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(255, 215, 0, 0.2);
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="bg-canvas"></div>

    <aside class="sidebar">
        <div class="mb-10 px-4">
            <h1 class="text-2xl font-black tracking-tighter uppercase italic text-white">NextGen<span
                    class="text-yellow-500">.</span></h1>
        </div>
        <nav class="flex-1">
            <a href="dashboard.php" class="nav-link"><i class="fa fa-wallet"></i> Terminal</a>
            <a href="support.php" class="nav-link active"><i class="fa fa-headset"></i> Support</a>
            <a href="transaction.php" class="nav-link"><i class="fa fa-history"></i> Activity</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="mb-10">
            <h2 class="text-3xl font-black uppercase tracking-tighter">Support <span
                    class="text-yellow-500">Protocol</span></h2>
            <p class="text-white/40 text-[9px] font-bold tracking-[0.4em] uppercase">Authorized Resolution Node</p>
        </header>

        <div class="grid grid-cols-12 gap-8">
            <div class="col-span-12 lg:col-span-7 space-y-8">
                <div class="action-panel">
                    <h4
                        class="text-xs font-black uppercase tracking-[0.2em] text-white/60 mb-6 flex items-center gap-2">
                        <i class="fa fa-plus-circle text-yellow-500"></i> New Ticket
                    </h4>
                    <form id="complaintForm" class="space-y-6">
                        <div>
                            <label
                                class="text-[10px] font-black uppercase text-white/40 mb-2 block tracking-widest">Category</label>
                            <select id="category" class="w-full p-4 rounded-xl text-sm" required>
                                <option value="" disabled selected>Select Incident Type</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['category_id']; ?>">
                                        <?php echo $cat['category_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black uppercase text-white/40 mb-2 block tracking-widest">Title</label>
                            <input type="text" id="title" class="w-full p-4 rounded-xl text-sm" placeholder="Subject"
                                required>
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-black uppercase text-white/40 mb-2 block tracking-widest">Description</label>
                            <textarea id="description" rows="4" class="w-full p-4 rounded-xl text-sm resize-none"
                                placeholder="Incident details..." required></textarea>
                        </div>
                        <button type="submit" id="submitBtn"
                            class="w-full bg-yellow-500 text-black font-black py-4 rounded-xl uppercase text-xs tracking-widest hover:bg-yellow-400 transition shadow-lg shadow-yellow-500/20">
                            Deploy Ticket
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-5">
                <div class="action-panel h-full overflow-hidden flex flex-col">
                    <h4 class="text-xs font-black uppercase tracking-[0.2em] text-white/60 mb-6"><i
                            class="fa fa-clock-rotate-left mr-2"></i> Recent History</h4>
                    <div class="space-y-4 flex-1 overflow-y-auto pr-2">
                        <?php if (mysqli_num_rows($comp_res) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($comp_res)):
                                $status_class = $row['status'] == 'Resolved' ? 'bg-green-500/20 text-green-500' : 'bg-yellow-500/20 text-yellow-500';
                                ?>
                                <div class="p-5 bg-white/5 rounded-2xl border border-white/5 hover:border-white/10 transition">
                                    <div class="flex justify-between items-start mb-2">
                                        <p class="text-[9px] font-mono text-white/30">
                                            <?php echo $row['ticket_number']; ?>
                                        </p>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo str_replace('_', ' ', $row['status']); ?>
                                        </span>
                                    </div>
                                    <h5 class="text-sm font-bold truncate">
                                        <?php echo htmlspecialchars($row['title']); ?>
                                    </h5>
                                    <p class="text-[10px] text-white/40 mt-1">
                                        <?php echo date('d M Y', strtotime($row['submission_date'])); ?>
                                    </p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="mt-20 text-center text-white/20 uppercase font-black text-[10px] tracking-widest">No
                                active tickets</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('complaintForm').onsubmit = function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.innerHTML = '<i class="fa fa-sync fa-spin"></i> Deploying...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('category_id', document.getElementById('category').value);
            formData.append('title', document.getElementById('title').value);
            formData.append('description', document.getElementById('description').value);

            fetch('../backend/complaint_process.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert("TICKET DEPLOYED: " + data.ticket_id);
                        location.reload();
                    } else {
                        alert(data.error || "Deployment Denied.");
                        btn.innerHTML = 'Deploy Ticket';
                        btn.disabled = false;
                    }
                });
        };
    </script>
</body>

</html>