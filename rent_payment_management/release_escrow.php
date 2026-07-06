<?php
/**
 * rent_payment_management/release_escrow.php
 * Releases escrow funds to the landlord's wallet.
 * Called by the tenant's move-in confirmation, or by an admin dispute verdict.
 * This file exposes a single reusable function: releaseEscrow().
 */

function releaseEscrow(PDO $pdo, int $escrowId): bool
{
    $stmt = $pdo->prepare("SELECT * FROM escrow WHERE escrow_id = :id AND status = 'HELD'");
    $stmt->execute([':id' => $escrowId]);
    $escrow = $stmt->fetch();

    if (!$escrow) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        // Credit the landlord's wallet (join through landlords -> users to get user_id).
        $landlordUser = $pdo->prepare(
            "SELECT u.user_id FROM landlords l JOIN users u ON u.user_id = l.user_id WHERE l.landlord_id = :lid"
        );
        $landlordUser->execute([':lid' => $escrow['landlord_id']]);
        $landlordUserId = $landlordUser->fetchColumn();

        $pdo->prepare(
            "UPDATE wallets SET balance = balance + :amt WHERE user_id = :uid"
        )->execute([':amt' => $escrow['amount'], ':uid' => $landlordUserId]);

        $pdo->prepare(
            "UPDATE escrow SET status = 'RELEASED', resolved_at = NOW() WHERE escrow_id = :id"
        )->execute([':id' => $escrowId]);

        notify($pdo, $landlordUserId, "Funds released",
            "Escrow funds of " . formatMoney($escrow['amount']) . " have been released to your wallet.", "PAYMENT");

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Escrow release failed: " . $e->getMessage());
        return false;
    }
}
