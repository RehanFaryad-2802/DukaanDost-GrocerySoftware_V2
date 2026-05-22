<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── GET INVOICE ──────────────────────────────────────────────────────────────
if ($action === 'get_invoice') {
    $invoice_id = $data['invoice_id'] ?? 0;

    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        echo json_encode(['success' => false, 'error' => 'Invoice not found']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT product_id, product_name, quantity, unit_price, total_price, tier_info
        FROM invoice_items WHERE invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'invoice' => [
            'id' => $invoice['id'],
            'invoice_no' => $invoice['invoice_no'],
            'customer_name' => $invoice['customer_name'],
            'customer_phone' => $invoice['customer_phone'],
            'customer_type' => $invoice['customer_type'],
            'subtotal' => $invoice['subtotal'],
            'discount_amount' => $invoice['discount_amount'],
            'total_amount' => $invoice['total_amount'],
            'payment_method' => $invoice['payment_method'],
            'items' => $items
        ]
    ]);

    // ── UPDATE INVOICE (true in-place edit) ──────────────────────────────────────
} elseif ($action === 'update_invoice') {
    $old_invoice_id = intval($data['old_invoice_id'] ?? 0);

    if (!$old_invoice_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid invoice ID']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Restore stock from old items
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$old_invoice_id]);
        $old_items = $stmt->fetchAll();

        foreach ($old_items as $old) {
            if ($old['product_id']) {
                $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$old['quantity'], $old['product_id']]);
            }
        }

        // 2. Delete old items
        $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$old_invoice_id]);

        // 3. Update invoice header in place (keep same invoice_no)
        $stmt = $pdo->prepare("
            UPDATE invoices SET
                customer_name   = ?,
                customer_phone  = ?,
                customer_type   = ?,
                subtotal        = ?,
                discount_amount = ?,
                total_amount    = ?,
                edit_count      = COALESCE(edit_count, 0) + 1,
                last_edited     = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['customer_name'] ?? null,
            $data['customer_phone'] ?? null,
            $data['customer_type'],
            $data['subtotal'],
            $data['discount'],
            $data['total'],
            $old_invoice_id
        ]);

        // 4. Insert new items and deduct stock
        foreach ($data['items'] as $item) {
            $product_id = intval($item['product_id'] ?? 0) ?: null;
            $quantity = floatval($item['quantity'] ?? 0);

            $stmt = $pdo->prepare("
                INSERT INTO invoice_items 
                    (invoice_id, product_id, product_name, quantity, unit_price, total_price, tier_info)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $old_invoice_id,
                $product_id,
                $item['product_name'],
                $quantity,
                $item['unit_price'],
                $item['total_price'],
                $item['tier_info'] ?? null
            ]);

            if ($product_id) {
                $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$quantity, $product_id]);
            }
        }

        $pdo->commit();

        // Get invoice_no to return
        $stmt = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id = ?");
        $stmt->execute([$old_invoice_id]);
        $inv_no = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'invoice_no' => $inv_no,
            'invoice_id' => $old_invoice_id,
            'message' => 'Invoice ' . $inv_no . ' updated successfully!'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    // ── LIST EDITABLE INVOICES ───────────────────────────────────────────────────
} elseif ($action === 'list_editable') {
    $stmt = $pdo->query("
        SELECT 
            i.id, i.invoice_no, i.customer_name, i.customer_type,
            i.total_amount, i.created_at, i.edit_count,
            u.full_name as created_by
        FROM invoices i
        JOIN users u ON i.created_by = u.id
        WHERE i.payment_status = 'paid'
        ORDER BY i.created_at DESC
        LIMIT 999999999999
    ");
    echo json_encode(['success' => true, 'invoices' => $stmt->fetchAll()]);
}
?>