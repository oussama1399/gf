<?php
require_once 'include/database.php';

header('Content-Type: application/json');

if (isset($_GET['nd'])) {
    $nd = $_GET['nd'];
    try {
        $stmt = $pdo->prepare("SELECT nd, id_statut, id_operateur, id_type_abonnement, CD_PROV, CD_ETAB FROM all_flotte WHERE nd = ?");
        $stmt->execute([$nd]);
        $flotte = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($flotte);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'ND parameter missing']);
}
?>