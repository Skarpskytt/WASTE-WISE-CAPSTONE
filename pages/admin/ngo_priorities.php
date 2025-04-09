<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for admin access only
checkAuth(['admin']);

$pdo = getPDO();

// Check if priority_rank column exists in ngo_profiles table, add if not
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM ngo_profiles LIKE 'priority_rank'");
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("ALTER TABLE ngo_profiles ADD COLUMN priority_rank INT DEFAULT 9999");
        $successMessage = "Priority ranking system has been initialized.";
    }
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Handle priority updates
if (isset($_POST['update_priorities'])) {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['ngo_ranks'] as $ngo_id => $rank) {
            $stmt = $pdo->prepare("UPDATE ngo_profiles SET priority_rank = ? WHERE user_id = ?");
            $stmt->execute([$rank, $ngo_id]);
        }
        
        $pdo->commit();
        $successMessage = "NGO priorities have been updated successfully!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMessage = "Error updating priorities: " . $e->getMessage();
    }
}

// Get all approved NGOs with their priority ranks
$stmt = $pdo->prepare("
    SELECT np.*, u.email, u.organization_name as user_org_name, u.id as user_id 
    FROM ngo_profiles np
    JOIN users u ON np.user_id = u.id
    WHERE np.status = 'approved'
    ORDER BY np.priority_rank ASC
");
$stmt->execute();
$ngos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distribution weights based on rank
$distributionWeights = [
    1 => 35,
    2 => 25,
    3 => 20,
    4 => 15,
    5 => 5
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NGO Priority Rankings | WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        primarylight: '#5d8a4e',
                        primarydark: '#385029',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                        accent: '#ffa62b',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .priority-item {
            cursor: grab;
            transition: all 0.2s ease;
        }
        
        .priority-item:hover {
            transform: translateY(-2px);
        }
        
        .priority-item:active {
            cursor: grabbing;
        }
        
        .sortable-ghost {
            opacity: 0.4;
        }
        
        .rank-badge {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            font-weight: bold;
        }
        
        .rank-badge-1 {
            background-color: #FFD700;
            color: #333;
        }
        
        .rank-badge-2 {
            background-color: #C0C0C0;
            color: #333;
        }
        
        .rank-badge-3 {
            background-color: #CD7F32;
        }
        
        .rank-badge-4, .rank-badge-5 {
            background-color: #47663B;
        }
    </style>
</head>

<body class="flex h-screen bg-gray-50">
    <?php include '../layout/nav.php' ?>
    
    <div class="flex flex-col w-full overflow-auto">
        <!-- Page Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-primarycol flex items-center gap-2">
                            <i class="fas fa-sort-amount-up"></i>
                            NGO Priority Rankings
                        </h1>
                        <p class="text-gray-500 text-sm mt-1">
                            Set priority rankings for NGOs to determine auto-distribution order
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($successMessage)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="alert alert-success shadow-lg">
                <div>
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($successMessage) ?></span>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="alert alert-error shadow-lg">
                <div>
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($errorMessage) ?></span>
                </div>
                <button class="btn btn-sm btn-circle btn-ghost" onclick="this.parentElement.remove()">×</button>
            </div>
        </div>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left - NGO Rankings -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-primarycol border-b border-gray-200 pb-4">
                            <i class="fas fa-sort-numeric-down mr-2"></i>
                            NGO Ranking Order
                        </h2>
                        
                        <div class="mt-4 mb-6 text-gray-600 text-sm">
                            <div class="flex items-center gap-2 bg-blue-50 p-3 rounded-md">
                                <i class="fas fa-info-circle text-blue-500"></i>
                                <p>
                                    Drag and drop to reorder NGOs. Top 5 NGOs will receive auto-distributed donations 
                                    based on their rank position. Changes will take effect once you save.
                                </p>
                            </div>
                        </div>
                        
                        <?php if (empty($ngos)): ?>
                            <div class="py-8 text-center">
                                <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-gray-400 mb-4">
                                    <i class="fas fa-users-slash text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900">No approved NGOs</h3>
                                <p class="mt-1 text-gray-500">No approved NGOs found in the system. Approve NGOs to start ranking them.</p>
                                <a href="ngo.php" class="btn btn-primary btn-sm mt-4">Manage NGOs</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="priorityForm">
                                <div id="ngo-list" class="space-y-3 mb-6">
                                    <?php foreach ($ngos as $index => $ngo): 
                                        $displayRank = $index + 1;
                                        $orgName = !empty($ngo['organization_name']) ? $ngo['organization_name'] : $ngo['user_org_name'];
                                    ?>
                                        <div class="priority-item flex items-center gap-3 bg-white border border-gray-200 p-4 rounded-lg shadow-sm" data-id="<?= $ngo['user_id'] ?>">
                                            <div class="rank-badge rank-badge-<?= min($displayRank, 5) ?>">
                                                <?= $displayRank ?>
                                            </div>
                                            
                                            <div class="flex-1">
                                                <div class="font-medium"><?= htmlspecialchars($orgName) ?></div>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($ngo['email']) ?></div>
                                            </div>
                                            
                                            <div class="distribution-percentage text-sm font-medium px-3 py-1 rounded-full">
                                                <?php if ($displayRank <= 5): ?>
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full">
                                                        <?= $distributionWeights[$displayRank] ?? 0 ?>% share
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                                        Not in top 5
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-grip-vertical text-gray-400"></i>
                                                <input type="hidden" name="ngo_ranks[<?= $ngo['user_id'] ?>]" value="<?= $displayRank ?>" class="rank-input">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="flex justify-end border-t border-gray-200 pt-4">
                                    <button type="submit" name="update_priorities" class="btn btn-primary gap-2">
                                        <i class="fas fa-save"></i> Save Priority Rankings
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right - Distribution Info -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-xl font-bold text-primarycol border-b border-gray-200 pb-4">
                            <i class="fas fa-chart-pie mr-2"></i>
                            Distribution Breakdown
                        </h2>
                        
                        <div class="mt-6">
                            <canvas id="distributionChart"></canvas>
                        </div>
                        
                        <div class="mt-6 space-y-3">
                            <h3 class="font-semibold text-gray-700">Distribution Rules</h3>
                            <ul class="space-y-2 text-sm text-gray-600">
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Top 5 NGOs receive automatic donations based on priority</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Rank 1 receives 35% of available donations</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Rank 2 receives 25% of available donations</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Rank 3 receives 20% of available donations</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Rank 4 receives 15% of available donations</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-green-500 mt-1"></i>
                                    <span>Rank 5 receives 5% of available donations</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-primarycol border-b border-gray-200 pb-4">
                            <i class="fas fa-info-circle mr-2"></i>
                            About Auto-Distribution
                        </h2>
                        
                        <div class="mt-4 space-y-4 text-sm text-gray-600">
                            <p>
                                When staff marks excess food for auto-distribution, the system automatically allocates 
                                portions to the top 5 ranked NGOs based on their priority position.
                            </p>
                            
                            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-lightbulb text-amber-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-amber-800">Example</h3>
                                        <p class="mt-2 text-amber-700">
                                            If 200 items are marked for auto-distribution, the #1 ranked NGO would receive 
                                            70 items (35%), #2 would receive 50 items (25%), and so on.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <p>
                                NGOs will be notified of their allocation and must accept or reject within 24 hours. 
                                Rejected allocations are redistributed to other NGOs automatically.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize SortableJS for drag-and-drop functionality
        const ngoList = document.getElementById('ngo-list');
        if (ngoList) {
            new Sortable(ngoList, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function() {
                    updateRankings();
                }
            });
        }
        
        // Update rankings after drag and drop
        function updateRankings() {
            const items = document.querySelectorAll('.priority-item');
            
            items.forEach((item, index) => {
                const rank = index + 1;
                const rankBadge = item.querySelector('.rank-badge');
                const rankInput = item.querySelector('.rank-input');
                const distributionPercentage = item.querySelector('.distribution-percentage');
                
                // Update rank badge
                rankBadge.textContent = rank;
                rankBadge.className = `rank-badge rank-badge-${Math.min(rank, 5)}`;
                
                // Update hidden input
                rankInput.value = rank;
                
                // Update distribution percentage display
                if (rank <= 5) {
                    const percentage = getDistributionPercentage(rank);
                    distributionPercentage.innerHTML = `<span class="bg-green-100 text-green-800 px-2 py-1 rounded-full">${percentage}% share</span>`;
                } else {
                    distributionPercentage.innerHTML = `<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full">Not in top 5</span>`;
                }
            });
        }
        
        function getDistributionPercentage(rank) {
            const percentages = {
                1: 35,
                2: 25, 
                3: 20,
                4: 15,
                5: 5
            };
            
            return percentages[rank] || 0;
        }
        
        // Initialize distribution chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('distributionChart').getContext('2d');
            
            const distributionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Rank 1 (35%)', 'Rank 2 (25%)', 'Rank 3 (20%)', 'Rank 4 (15%)', 'Rank 5 (5%)'],
                    datasets: [{
                        data: [35, 25, 20, 15, 5],
                        backgroundColor: [
                            '#FFD700', // Gold
                            '#C0C0C0', // Silver
                            '#CD7F32', // Bronze
                            '#5d8a4e', // Green
                            '#385029'  // Dark Green
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        title: {
                            display: true,
                            text: 'Auto-Distribution Allocation'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>