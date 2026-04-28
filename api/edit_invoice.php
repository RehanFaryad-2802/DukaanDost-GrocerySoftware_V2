<?php
require_once '../config/database.php';
checkAuth();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

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
        SELECT 
            product_id, 
            product_name, 
            quantity, 
            unit_price, 
            total_price,
            tier_info
        FROM invoice_items 
        WHERE invoice_id = ?
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

} elseif ($action === 'update_invoice') {
    // Update existing invoice (creates new version)
    $old_invoice_id = $data['old_invoice_id'] ?? 0;

    try {
        $pdo->beginTransaction();

        // Generate new invoice number
        $stmt = $pdo->query("SELECT generate_invoice_no() as invoice_no");
        $new_invoice_no = $stmt->fetch()['invoice_no'];

        // Insert new invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                invoice_no, customer_name, customer_phone, customer_type,
                subtotal, discount_amount, total_amount, payment_method,
                payment_status, created_by, edited_from, edit_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, 1)
        ");
        $stmt->execute([
            $new_invoice_no,
            $data['customer_name'] ?? null,
            $data['customer_phone'] ?? null,
            $data['customer_type'],
            $data['subtotal'],
            $data['discount'],
            $data['total'],
            $data['payment_method'] ?? 'cash',
            $_SESSION['user_id'],
            $old_invoice_id
        ]);

        $new_invoice_id = $pdo->lastInsertId();

        // Find this section in edit_invoice.php and update:

        foreach ($data['items'] as $item) {
            // Insert invoice item
            $stmt = $pdo->prepare("
        INSERT INTO invoice_items (invoice_id, product_id, product_name, quantity, unit_price, total_price, tier_info)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

            $product_id = intval($item['product_id'] ?? 0);
            $final_product_id = ($product_id > 0) ? $product_id : null;

            $stmt->execute([
                $new_invoice_id,
                $final_product_id,
                $item['product_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                $item['tier_info'] ?? null
            ]);

            // Update stock only for real products
            if ($final_product_id !== null) {
                $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $final_product_id]);
            }
        }

        // Mark old invoice as edited
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET edit_count = edit_count + 1, last_edited = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$old_invoice_id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'invoice_no' => $new_invoice_no,
            'invoice_id' => $new_invoice_id,
            'message' => 'Invoice updated successfully!'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

} elseif ($action === 'list_editable') {
    // List recent invoices for editing
    $stmt = $pdo->query("
        SELECT 
            i.id, i.invoice_no, i.customer_name, i.customer_type, 
            i.total_amount, i.created_at, i.edit_count,
            u.full_name as created_by
        FROM invoices i
        JOIN users u ON i.created_by = u.id
        WHERE i.payment_status = 'paid'
        ORDER BY i.created_at DESC
        LIMIT 50
    ");
    $invoices = $stmt->fetchAll();

    echo json_encode(['success' => true, 'invoices' => $invoices]);
}
?>