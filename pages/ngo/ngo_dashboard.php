<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check if user is NGO
checkAuth(['ngo']);

$pdo = getPDO();

$ngoId = $_SESSION['user_id'];

// Fetch more detailed NGO information
$ngoInfoQuery = $pdo->prepare("
    SELECT 
        id,
        CONCAT(fname, ' ', lname) as full_name,
        email,
        phone,
        organization_name,
        COALESCE(profile_image, 'default.jpg') as profile_image,
        created_at,
        (SELECT COUNT(*) FROM donated_products WHERE ngo_id = users.id) as total_requests
    FROM users 
    WHERE id = ? AND role = 'ngo'
");
$ngoInfoQuery->execute([$ngoId]);
$ngoInfo = $ngoInfoQuery->fetch(PDO::FETCH_ASSOC);

// Add a personalized welcome message variable
$greeting = "";
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Get total donations received (count)
$totalDonationsQuery = $pdo->prepare("
    SELECT COUNT(*) as count, SUM(received_quantity) as total_quantity
    FROM donated_products
    WHERE ngo_id = ?
");
$totalDonationsQuery->execute([$ngoId]);
$totalDonations = $totalDonationsQuery->fetch(PDO::FETCH_ASSOC);

// Get received donations
$receivedDonationsQuery = $pdo->prepare("
    SELECT COUNT(*) as count, SUM(received_quantity) as quantity
    FROM donated_products
    WHERE ngo_id = ?
");
$receivedDonationsQuery->execute([$ngoId]);
$receivedDonations = $receivedDonationsQuery->fetch(PDO::FETCH_ASSOC);

// Get pending requests
$pendingRequestsQuery = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM ngo_donation_requests dr
    JOIN donated_products dp ON dr.id = dp.donation_request_id
    WHERE dp.ngo_id = ? AND dr.status = 'pending'
");
$pendingRequestsQuery->execute([$ngoId]);
$pendingRequests = $pendingRequestsQuery->fetch(PDO::FETCH_ASSOC);

// Get ready for pickup (prepared but not yet received)
$readyForPickupQuery = $pdo->prepare("
    SELECT COUNT(*) as count, SUM(dr.quantity_requested) as quantity
    FROM ngo_donation_requests dr
    LEFT JOIN donated_products dp ON dr.id = dp.donation_request_id AND dp.ngo_id = ?
    WHERE dr.status = 'approved' AND dp.id IS NULL
");
$readyForPickupQuery->execute([$ngoId]);
$readyForPickup = $readyForPickupQuery->fetch(PDO::FETCH_ASSOC);

// Get donation history for charts (last 6 months)
$donationHistoryQuery = $pdo->prepare("
    SELECT 
        DATE_FORMAT(received_date, '%Y-%m') as month,
        SUM(received_quantity) as quantity
    FROM donated_products
    WHERE ngo_id = ?
    GROUP BY DATE_FORMAT(received_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$donationHistoryQuery->execute([$ngoId]);
$donationHistory = $donationHistoryQuery->fetchAll(PDO::FETCH_ASSOC);
// Reverse to get chronological order
$donationHistory = array_reverse($donationHistory);

// Get top 3 categories received
$topCategoriesQuery = $pdo->prepare("
    SELECT 
        p.category,
        SUM(dp.received_quantity) as total_quantity
    FROM donated_products dp
    JOIN ngo_donation_requests dr ON dp.donation_request_id = dr.id
    JOIN product_info p ON dr.product_id = p.id
    WHERE dp.ngo_id = ?
    GROUP BY p.category
    ORDER BY total_quantity DESC
    LIMIT 3
");
$topCategoriesQuery->execute([$ngoId]);
$topCategories = $topCategoriesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get top 3 branches by donations
$topBranchesQuery = $pdo->prepare("
    SELECT 
        b.name as branch_name,
        COUNT(*) as donation_count
    FROM donated_products dp
    JOIN ngo_donation_requests dr ON dp.donation_request_id = dr.id
    JOIN branches b ON dr.branch_id = b.id
    WHERE dp.ngo_id = ?
    GROUP BY b.name
    ORDER BY donation_count DESC
    LIMIT 3
");
$topBranchesQuery->execute([$ngoId]);
$topBranches = $topBranchesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get recent donations (last 5)
$recentDonationsQuery = $pdo->prepare("
    SELECT 
        dr.id,
        dr.quantity_requested as quantity,
        dp.received_date as pickup_date,
        dr.request_date as created_at,
        dr.status,
        CASE WHEN dp.id IS NOT NULL THEN 1 ELSE 0 END as is_received,
        p.name as product_name,
        b.name as branch_name,
        (SELECT CASE WHEN EXISTS (
            SELECT 1 FROM donation_prepared prep 
            WHERE prep.ngo_request_id = dr.id
        ) THEN 1 ELSE 0 END) as is_prepared
    FROM ngo_donation_requests dr
    LEFT JOIN donated_products dp ON dr.id = dp.donation_request_id AND dp.ngo_id = ?
    JOIN product_info p ON dr.product_id = p.id
    JOIN branches b ON dr.branch_id = b.id
    WHERE dr.ngo_id = ? AND dr.status IN ('pending', 'approved', 'rejected')
    ORDER BY dr.request_date DESC
    LIMIT 5
");
$recentDonationsQuery->execute([$ngoId, $ngoId]);
$recentDonations = $recentDonationsQuery->fetchAll(PDO::FETCH_ASSOC);

// Format chart data for JSON
$chartLabels = [];
$chartData = [];
foreach ($donationHistory as $entry) {
    $month = date('M Y', strtotime($entry['month'] . '-01'));
    $chartLabels[] = $month;
    $chartData[] = (int)$entry['quantity'];
}

// Calculate impact (estimated people fed - assuming each donation helps 4 people)
$impactPeopleCount = $receivedDonations['quantity'] * 4;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard - WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primarycol: '#47663B',
                        sec: '#E8ECD7',
                        third: '#EED3B1',
                        fourth: '#1F4529',
                    }
                }
            }
        }
    </script>
</head>

<body class="flex h-screen bg-slate-100">
    <?php include '../layout/ngo_nav.php' ?>

    <div class="flex flex-col w-full p-6 space-y-6 overflow-y-auto">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-2xl font-bold text-primarycol">
                    <?= $greeting ?>, <?= htmlspecialchars($ngoInfo['full_name']) ?>
                </div>
                <div class="text-sm text-gray-500">
                    <?= htmlspecialchars($ngoInfo['organization_name'] ?? 'NGO Dashboard') ?> | Member since <?= date('F Y', strtotime($ngoInfo['created_at'])) ?>
                </div>
            </div>
            <div class="text-sm text-gray-500">Dashboard Overview</div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Donations Received -->
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-primarycol">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-sec rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primarycol" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500">Total Donations</h2>
                        <div class="flex items-end gap-1">
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($totalDonations['count']) ?></p>
                            <p class="text-sm text-gray-500">donations</p>
                        </div>
                        <p class="text-sm text-gray-500"><?= number_format($totalDonations['total_quantity']) ?> items total</p>
                    </div>
                </div>
            </div>

            <!-- Pending Requests -->
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-yellow-50 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500">Pending Requests</h2>
                        <div class="flex items-end gap-1">
                            <p class="text-2xl font-bold text-gray-800"><?= $pendingRequests['count'] ?></p>
                            <p class="text-sm text-gray-500">awaiting approval</p>
                        </div>
                        <a href="donation_history.php" class="text-xs text-primarycol hover:underline">View requests</a>
                    </div>
                </div>
            </div>

            <!-- Ready for Pickup -->
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-blue-500">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500">Ready for Pickup</h2>
                        <div class="flex items-end gap-1">
                            <p class="text-2xl font-bold text-gray-800"><?= $readyForPickup['count'] ?></p>
                            <p class="text-sm text-gray-500">donations</p>
                        </div>
                        <p class="text-sm text-gray-500"><?= number_format($readyForPickup['quantity'] ?? 0) ?> items available</p>
                    </div>
                </div>
            </div>

            <!-- Impact -->
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-green-500">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-50 rounded-full">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-sm font-medium text-gray-500">Estimated Impact</h2>
                        <div class="flex items-end gap-1">
                            <p class="text-2xl font-bold text-gray-800"><?= number_format($impactPeopleCount) ?></p>
                            <p class="text-sm text-gray-500">people fed</p>
                        </div>
                        <p class="text-sm text-gray-500"><?= number_format($receivedDonations['quantity'] ?? 0) ?> items distributed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Donation History Chart -->
            <div class="bg-white p-6 rounded-lg shadow-md col-span-2">
                <h3 class="text-lg font-semibold text-primarycol mb-4">Donation History</h3>
                <div class="h-80">
                    <canvas id="donationHistoryChart"></canvas>
                </div>
            </div>

            <!-- Activity Feed -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-primarycol mb-4">Recent Activity</h3>
                <?php if (empty($recentDonations)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <p>No recent donation activity</p>
                        <a href="food_browse.php" class="btn btn-sm bg-primarycol text-white mt-4">Browse Available Food</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($recentDonations as $donation): ?>
                            <div class="border-l-4 p-3 <?= getStatusColor($donation['status'], $donation['is_received'], $donation['is_prepared']) ?>">
                                <div class="flex justify-between">
                                    <span class="font-semibold"><?= htmlspecialchars($donation['product_name']) ?></span>
                                    <span class="text-sm"><?= getStatusBadge($donation['status'], $donation['is_received'], $donation['is_prepared']) ?></span>
                                </div>
                                <p class="text-sm">Qty: <?= $donation['quantity'] ?> from <?= htmlspecialchars($donation['branch_name']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= date('M d, Y', strtotime($donation['created_at'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        <a href="donation_history.php" class="btn btn-sm btn-outline w-full mt-2">View All History</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Additional Insights Section -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Top Categories -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-primarycol mb-4">Top Categories Received</h3>
                <?php if (empty($topCategories)): ?>
                    <p class="text-center text-gray-500 py-4">No donation data available</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach($topCategories as $index => $category): ?>
                            <div class="flex justify-between items-center">
                                <span class="font-medium"><?= htmlspecialchars($category['category']) ?></span>
                                <span class="text-sm text-gray-600"><?= number_format($category['total_quantity']) ?> items</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <?php 
                                $maxQuantity = $topCategories[0]['total_quantity'];
                                $percentage = ($category['total_quantity'] / $maxQuantity) * 100;
                                $colors = ['bg-blue-500', 'bg-green-500', 'bg-yellow-500'];
                                ?>
                                <div class="<?= $colors[$index] ?> h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Branches -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-primarycol mb-4">Top Donor Branches</h3>
                <?php if (empty($topBranches)): ?>
                    <p class="text-center text-gray-500 py-4">No branch data available</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach($topBranches as $branch): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-sec rounded-full flex items-center justify-center">
                                    <span class="text-primarycol font-bold"><?= substr(htmlspecialchars($branch['branch_name']), 0, 1) ?></span>
                                </div>
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($branch['branch_name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $branch['donation_count'] ?> donations</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-primarycol mb-4">Quick Actions</h3>
                <div class="space-y-3">
                    <a href="food_browse.php" class="btn btn-block bg-primarycol text-white">
                        Browse Available Donations
                    </a>
                    <a href="donation_history.php" class="btn btn-block btn-outline border-primarycol text-primarycol">
                        View Donation History
                    </a>
                    <a href="donation_history.php" class="btn btn-block btn-outline border-primarycol text-primarycol">
                        Confirm Pickups
                    </a>
                </div>
                
                <!-- Help Card -->
                <div class="bg-sec rounded-lg p-4 mt-6">
                    <h4 class="font-medium text-primarycol">Need Help?</h4>
                    <p class="text-sm mt-1">Contact our support team for any questions about the donation process.</p>
                    <a href="mailto:support@wastewise.com" class="text-sm text-primarycol hover:underline mt-2 inline-block">support@wastewise.com</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Create donation history chart
        const ctx = document.getElementById('donationHistoryChart').getContext('2d');
        const donationHistoryChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Donations Received',
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: '#47663B',
                    borderColor: '#47663B',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Quantity'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Monthly Donation History',
                        font: {
                            size: 16
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions for status colors and badges
function getStatusColor($status, $isReceived, $isPrepared = 0) {
    if ($isReceived) {
        return 'border-green-500 bg-green-50';
    }
    
    switch ($status) {
        case 'approved':
            return $isPrepared ? 
                'border-blue-500 bg-blue-50' : 
                'border-yellow-500 bg-yellow-50';
        case 'pending':
            return 'border-yellow-500 bg-yellow-50';
        case 'rejected':
            return 'border-red-500 bg-red-50';
        default:
            return 'border-gray-500 bg-gray-50';
    }
}

function getStatusBadge($status, $isReceived, $isPrepared = 0) {
    if ($isReceived) {
        return '<span class="badge badge-sm badge-success">Received</span>';
    }
    
    switch ($status) {
        case 'approved':
            return $isPrepared ? 
                '<span class="badge badge-sm badge-info">Ready for Pickup</span>' : 
                '<span class="badge badge-sm badge-warning">Processing</span>';
        case 'pending':
            return '<span class="badge badge-sm badge-warning">Pending</span>';
        case 'rejected':
            return '<span class="badge badge-sm badge-error">Rejected</span>';
        default:
            return '<span class="badge badge-sm">Unknown</span>';
    }
}
?>
