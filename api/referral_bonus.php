<?php
// api/referral_bonus.php - JSON API for referral data
header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin('../login.php');

$user_id = getCurrentUserId();
$action = $_GET['action'] ?? 'stats';

try {
    $pdo = getConnection();

    switch ($action) {
        case 'tree':
            // Get referral tree data for D3.js
            $stmt = $pdo->prepare("
                SELECT id, username, email, created_at, sponsor_id
                FROM users
                WHERE sponsor_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$user_id]);
            $direct_referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tree = [
                'name' => getCurrentUsername(),
                'children' => []
            ];

            foreach ($direct_referrals as $ref) {
                $child = [
                    'name' => $ref['username'],
                    'email' => $ref['email'],
                    'created_at' => $ref['created_at'],
                    'children' => []
                ];

                // Get 2nd level referrals
                $stmt = $pdo->prepare("
                    SELECT id, username, email, created_at
                    FROM users
                    WHERE sponsor_id = ?
                ");
                $stmt->execute([$ref['id']]);
                $level2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($level2 as $ref2) {
                    $child['children'][] = [
                        'name' => $ref2['username'],
                        'email' => $ref2['email'],
                        'created_at' => $ref2['created_at']
                    ];
                }

                $tree['children'][] = $child;
            }

            echo json_encode($tree);
            break;

        case 'stats':
            // Get referral statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_referrals,
                    SUM(CASE WHEN level = 2 THEN amount ELSE 0 END) as level2_bonus,
                    SUM(CASE WHEN level = 3 THEN amount ELSE 0 END) as level3_bonus,
                    SUM(CASE WHEN level = 4 THEN amount ELSE 0 END) as level4_bonus,
                    SUM(CASE WHEN level = 5 THEN amount ELSE 0 END) as level5_bonus
                FROM referral_bonuses
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'referrals':
            // Get detailed referral list
            $stmt = $pdo->prepare("
                SELECT 
                    u.username,
                    u.email,
                    u.created_at,
                    COUNT(DISTINCT r2.id) as level2_count,
                    COUNT(DISTINCT r3.id) as level3_count,
                    COUNT(DISTINCT r4.id) as level4_count,
                    COUNT(DISTINCT r5.id) as level5_count
                FROM users u
                LEFT JOIN users r2 ON r2.sponsor_id = u.id
                LEFT JOIN users r3 ON r3.sponsor_id = r2.id
                LEFT JOIN users r4 ON r4.sponsor_id = r3.id
                LEFT JOIN users r5 ON r5.sponsor_id = r4.id
                WHERE u.sponsor_id = ?
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'referrals' => $referrals]);
            break;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>