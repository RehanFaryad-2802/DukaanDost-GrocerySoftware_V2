<?php
require_once 'config/database.php';

$stmt = $pdo->query("
    SELECT name 
    FROM products 
    WHERE status = 'active' 
    ORDER BY name ASC
");
$products = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Names - DukaanDost</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }

        h2 {
            color: #333;
            border-bottom: 2px solid #28a745;
            padding-bottom: 10px;
        }

        .product-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .product-item {
            padding: 8px 15px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
            border-right: 4px solid #28a745;
            font-size: 16px;
        }

        .count {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }

        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
        }

        .copy-btn:hover {
            background: #218838;
        }
    </style>
</head>

<body>

    <div class="product-list">
        <h2>📦 Product Names (<?php echo count($products); ?> items)</h2>

        <div id="productNames">
            <?php foreach ($products as $name): ?>
                <div class="product-item"><?php echo htmlspecialchars($name); ?></div>
            <?php endforeach; ?>
        </div>

        <div class="count">
            Total: <strong><?php echo count($products); ?></strong> products
        </div>

        <button class="copy-btn" onclick="copyProductNames()">
            📋 Copy All Names
        </button>
    </div>

    <script>
        function copyProductNames() {
            const names = [];
            document.querySelectorAll('.product-item').forEach(item => {
                names.push(item.textContent.trim());
            });

            const textToCopy = names.join('\n');

            navigator.clipboard.writeText(textToCopy).then(() => {
                alert('✅ ' + names.length + ' product names copied to clipboard!');
            }).catch(err => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = textToCopy;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('✅ ' + names.length + ' product names copied to clipboard!');
            });
        }
    </script>

</body>

</html>