<?php
require_once '../config/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

$base_sql = "
    SELECT pt.*, 
           IF(r.requester_id = pt.user_id, p_user.user_id, r_user.user_id) as other_user_id,
           IF(r.requester_id = pt.user_id, p_user.first_name, r_user.first_name) as other_first_name,
           IF(r.requester_id = pt.user_id, p_user.last_name, r_user.last_name) as other_last_name
    FROM points_transactions pt
    LEFT JOIN requests r ON pt.request_id = r.request_id
    LEFT JOIN users p_user ON r.provider_id = p_user.user_id
    LEFT JOIN users r_user ON r.requester_id = r_user.user_id
    WHERE pt.user_id = ?
";

$filter = $_GET['filter'] ?? 'all';
if ($filter === 'in') {
    $sql = $base_sql . " AND pt.type IN ('earn', 'refund') ORDER BY pt.created_at DESC";
} elseif ($filter === 'out') {
    $sql = $base_sql . " AND pt.type = 'spend' ORDER BY pt.created_at DESC";
} else {
    $sql = $base_sql . " ORDER BY pt.created_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result();
$page_title = 'سجل النقاط - إثراء';
$page_css = '
    <link rel="stylesheet" href="../assets/css/account_style.css?v=' . filemtime(__DIR__ . '/../assets/css/account_style.css') . '">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
';

include '../includes/user_header.php';
include '../includes/user_navbar.php';
?>
<div class="container mb-5 wallet-history-page">
    <div class="account-container" style="min-height: 80vh;">
        
        <!-- Header & Balance Card Row -->
        <div class="row mb-5 align-items-center justify-content-between">
            <!-- Text Header -->
            <div class="col-md-6 mb-4 mb-md-0" style="text-align: right !important;">
                <h1 class="page-title fw-bold mb-2 dynamic-title" style="color: var(--dark-blue); font-size: 2rem; margin-right: 0 !important; text-align: right;">سجل النقاط</h1>
                <p class="text-muted mb-0" style="font-size: 1.1rem; text-align: right;">تابع جميع حركات رصيدك وعملياتك السابقة في مكان واحد بكل سهولة.</p>
            </div>
            
            <!-- Balance Card -->
            <div class="col-md-5">
                        <div class="card shadow border-0 me-md-auto ms-md-0 mx-auto" style="border-radius: 20px; background: linear-gradient(135deg, var(--dark-blue), #0b3dcf); color: white; position: relative; overflow: hidden; max-width: 350px;">
                            <!-- Decorative Circle -->
                            <div style="position: absolute; top: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                            
                            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title fw-normal mb-1" style="color: #e0e7ff; font-size: 0.95rem;">الرصيد المتاح</h6>
                                    <h1 class="fw-bold mb-0 display-5">
                                        <?php echo $nav_points; ?>
                                    </h1>
                                </div>
                                <div class="bg-white rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 60px; height: 60px;">
                                    <img src="../assets/images/coins.svg" width="35" height="35" alt="pts">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transactions List -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <h5 class="fw-bold mb-0 dynamic-title" style="color: var(--dark-blue);">سجل العمليات</h5>
        <div class="dropdown">
            <button class="btn wallet-filter-btn border border-secondary-subtle dropdown-toggle px-3 py-2 fw-bold" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 10px; font-size: 0.9rem; background-color: transparent; color: var(--dark-blue);">
                <i class="fa-solid fa-filter me-1"></i> 
                <?php 
                    if($filter == 'in') echo 'النقاط المكتسبة';
                    elseif($filter == 'out') echo 'النقاط المستخدمة';
                    else echo 'جميع العمليات';
                ?>
            </button>
            <ul class="dropdown-menu shadow border-0" style="border-radius: 10px;">
                <li><a class="dropdown-item <?php echo $filter == 'all' ? 'active bg-primary text-white' : ''; ?>" href="?filter=all">جميع العمليات</a></li>
                <li><a class="dropdown-item <?php echo $filter == 'in' ? 'active bg-primary text-white' : ''; ?>" href="?filter=in">اكتساب النقاط <i class="fa-solid fa-arrow-down text-success float-end mt-1"></i></a></li>
                <li><a class="dropdown-item <?php echo $filter == 'out' ? 'active bg-primary text-white' : ''; ?>" href="?filter=out">استخدام النقاط <i class="fa-solid fa-arrow-up text-danger float-end mt-1"></i></a></li>
            </ul>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <?php if($transactions->num_rows > 0): ?>
                <div class="list-group shadow-sm" style="border-radius: 12px;">
                    <?php while($row = $transactions->fetch_assoc()): ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3" style="border: none; border-bottom: 1px solid var(--bs-border-color-translucent);">
                            <div class="d-flex align-items-center flex-grow-1 pe-2">
                                <?php if($row['type'] == 'earn' || $row['type'] == 'refund'): ?>
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3 ms-3 shadow-sm flex-shrink-0" style="width: 45px; height: 45px; background-color: #d1e7dd; color: #0f5132;">
                                        <i class="fa-solid fa-arrow-down fs-5"></i>
                                    </div>
                                <?php else: ?>
                                    <div class="rounded-circle d-flex justify-content-center align-items-center me-3 ms-3 shadow-sm flex-shrink-0" style="width: 45px; height: 45px; background-color: #f8d7da; color: #842029;">
                                        <i class="fa-solid fa-arrow-up fs-5"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-wrap">
                                    <?php
                                        $desc = htmlspecialchars($row['description']);
                                        if ($row['request_id'] && $row['other_first_name']) {
                                            $full_name = htmlspecialchars($row['other_first_name'] . ' ' . $row['other_last_name']);
                                            if ($row['type'] == 'earn') {
                                                $desc .= ' لـ <a href="javascript:void(0)" onclick="goToCard('.$row['other_user_id'].')" style="color: #4a8bff; text-decoration: none; cursor: pointer;">' . $full_name . '</a>';
                                            } else {
                                                $desc .= ' من <a href="javascript:void(0)" onclick="goToCard('.$row['other_user_id'].')" style="color: #4a8bff; text-decoration: none; cursor: pointer;">' . $full_name . '</a>';
                                            }
                                        }
                                    ?>
                                    <h6 class="mb-1 fw-bold" style="font-size: 0.95rem; line-height: 1.4;"><?php echo $desc; ?></h6>
                                    <div class="text-muted" style="font-size: 0.75rem; text-align: right; direction: ltr; display: inline-block; width: 100%;">
                                        <?php echo date('Y-m-d h:i A', strtotime($row['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end flex-shrink-0 ps-2">
                                <?php if($row['type'] == 'earn' || $row['type'] == 'refund'): ?>
                                    <span class="fs-5 fw-bold text-success">+<?php echo $row['amount']; ?></span>
                                <?php else: ?>
                                    <span class="fs-5 fw-bold text-danger">-<?php echo $row['amount']; ?></span>
                                <?php endif; ?>
                                <span class="d-block text-muted" style="font-size: 0.8rem;">
                                    <?php 
                                        if($row['type'] == 'earn') echo 'اكتساب';
                                        if($row['type'] == 'spend') echo 'استخدام';
                                        if($row['type'] == 'refund') echo 'استرجاع';
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 shadow-sm empty-wallet-card" style="border-radius: 12px; border: 1px solid #dee2e6;">
                    <i class="fa-solid fa-receipt text-muted mb-3" style="font-size: 3rem;"></i>
                    <h5 class="text-muted">لا توجد حركات في سجلك حتى الآن.</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    </div> <!-- End account-container -->
</div> <!-- End container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goToCard(userId) {
    if(!userId) return;
    
    fetch('api_check_user_service.php?id=' + userId)
        .then(res => res.json())
        .then(data => {
            if(data.has_service) {
                window.location.href = 'services_list.php?highlight=' + userId;
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'عذراً',
                    text: 'هذا المستخدم لا يقدم أي خدمة في الوقت الحالي ولا يملك بطاقة.',
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: 'var(--dark-blue)'
                });
            }
        })
        .catch(err => {
            console.error(err);
        });
}
</script>
</body>
</html>
